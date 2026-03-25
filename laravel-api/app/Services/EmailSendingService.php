<?php

namespace App\Services;

use App\Models\OutreachConfig;
use App\Models\OutreachEmail;
use App\Models\OutreachSequence;
use App\Models\WarmupState;
use Illuminate\Support\Facades\Log;

/**
 * Sends outreach emails via PMTA SMTP.
 *
 * PLAIN TEXT ONLY — no HTML, no images, no pixel tracking.
 * Maximizes deliverability: simple text/plain + List-Unsubscribe header.
 * Tracking via click redirects on links (Calendly, site).
 */
class EmailSendingService
{
    private string $pmtaHost;
    private int $pmtaPort;
    private string $trackingBaseUrl;

    public function __construct()
    {
        $this->pmtaHost = config('outreach.pmta_host', '127.0.0.1');
        $this->pmtaPort = (int) config('outreach.pmta_port', 2525);
        $this->trackingBaseUrl = rtrim(config('app.url', 'https://influenceurs.life-expat.com'), '/');
    }

    /**
     * Send a single outreach email via PMTA.
     * Returns: 'sent' | 'warmup_limit' | 'failed'
     */
    public function send(OutreachEmail $email): string
    {
        $to = $email->influenceur?->email;
        if (!$to) {
            $email->update(['status' => 'failed', 'error_message' => 'Pas d\'email destinataire']);
            return 'failed';
        }

        // Check warmup limit
        $warmup = WarmupState::getFor($email->from_email);
        if (!$warmup->canSend()) {
            // Re-queue for tomorrow 8am instead of failing
            $email->update(['send_after' => now()->addDay()->startOfDay()->addHours(8)]);
            Log::info('EmailSending: warmup limit reached, rescheduled', [
                'id'    => $email->id,
                'from'  => $email->from_email,
                'limit' => $warmup->current_daily_limit,
            ]);
            return 'warmup_limit';
        }

        // Build the plain text message
        $message = $this->buildMessage($email, $to);

        try {
            $email->update(['status' => 'sending']);

            $success = $this->sendViaSMTP($email->from_email, $to, $message);

            if ($success) {
                $email->update(['status' => 'sent', 'sent_at' => now()]);
                $warmup->recordSent();
                $this->updatePipelineStatus($email);
                $this->initializeSequence($email);

                Log::info('Email sent', [
                    'id'   => $email->id,
                    'to'   => $to,
                    'step' => $email->step,
                    'from' => $email->from_email,
                ]);
                return 'sent';
            }

            $email->update(['status' => 'failed', 'error_message' => 'SMTP rejected']);
            return 'failed';

        } catch (\Throwable $e) {
            $email->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            Log::error('Email send failed', ['id' => $email->id, 'error' => $e->getMessage()]);
            return 'failed';
        }
    }

    /**
     * Build a complete plain text email message (headers + body).
     * NO HTML — maximizes deliverability.
     */
    private function buildMessage(OutreachEmail $email, string $to): string
    {
        $unsubUrl = $this->trackingBaseUrl . '/api/unsubscribe/' . $email->unsubscribe_token;

        // Add unsubscribe footer to body
        $body = $email->body_text . "\n\n---\nPour ne plus recevoir de messages : " . $unsubUrl;

        // Wrap links for click tracking
        $body = $this->wrapLinksForTracking($body, $email->tracking_id);

        // Build headers + body as one raw message
        $msg  = "From: {$email->from_name} <{$email->from_email}>\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($email->subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $msg .= "List-Unsubscribe: <{$unsubUrl}>\r\n";
        $msg .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        $msg .= "Message-ID: <" . $email->tracking_id . "@" . substr($email->from_email, strpos($email->from_email, '@') + 1) . ">\r\n";
        $msg .= "\r\n";
        $msg .= quoted_printable_encode($body);

        return $msg;
    }

    /**
     * Replace URLs in body with tracking redirects.
     * Only wraps http/https URLs, not the unsubscribe link.
     */
    private function wrapLinksForTracking(string $body, string $trackingId): string
    {
        $baseUrl = $this->trackingBaseUrl . '/api/track/click/' . $trackingId;

        return preg_replace_callback(
            '#(https?://[^\s<>]+)#',
            function ($matches) use ($baseUrl) {
                $url = $matches[1];
                // Don't wrap unsubscribe links or tracking links
                if (str_contains($url, '/unsubscribe/') || str_contains($url, '/track/')) {
                    return $url;
                }
                return $baseUrl . '?url=' . urlencode($url);
            },
            $body
        );
    }

    /**
     * Send via PMTA SMTP (port 2525, no auth — IP whitelisted).
     */
    private function sendViaSMTP(string $from, string $to, string $message): bool
    {
        $socket = @fsockopen($this->pmtaHost, $this->pmtaPort, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("PMTA connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 15);

        try {
            $this->readResponse($socket); // Banner
            $this->sendCommand($socket, "EHLO outreach.sos-expat.com");
            $this->sendCommand($socket, "MAIL FROM:<{$from}>");

            $rcptResponse = $this->sendCommand($socket, "RCPT TO:<{$to}>");
            if (!str_starts_with(trim($rcptResponse), '250')) {
                Log::warning('SMTP RCPT rejected', ['to' => $to, 'response' => $rcptResponse]);
                fputs($socket, "QUIT\r\n");
                return false;
            }

            $this->sendCommand($socket, "DATA");
            fputs($socket, $message . "\r\n.\r\n");
            $dataResponse = $this->readResponse($socket);

            fputs($socket, "QUIT\r\n");

            return str_starts_with(trim($dataResponse), '250');

        } finally {
            @fclose($socket);
        }
    }

    private function sendCommand($socket, string $command): string
    {
        fputs($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }

    /**
     * Update influenceur pipeline status after sending.
     */
    private function updatePipelineStatus(OutreachEmail $email): void
    {
        $inf = $email->influenceur;
        if (!$inf) return;

        $newStatus = match ($email->step) {
            1 => 'contacted1',
            2 => 'contacted2',
            3 => 'contacted3',
            default => null,
        };

        if ($newStatus && in_array($inf->status, ['new', 'prospect', 'contacted1', 'contacted2'])) {
            $inf->update(['status' => $newStatus, 'last_contact_at' => now()]);
        }
    }

    /**
     * Create outreach sequence when step 1 is sent successfully.
     */
    private function initializeSequence(OutreachEmail $email): void
    {
        if ($email->step !== 1) return;

        $inf = $email->influenceur;
        if (!$inf) return;

        $config = OutreachConfig::getFor($inf->contact_type);

        OutreachSequence::firstOrCreate(
            ['influenceur_id' => $inf->id],
            [
                'current_step' => 1,
                'status'       => 'active',
                'started_at'   => now(),
                'next_send_at' => $config->max_steps > 1
                    ? now()->addDays($config->getStepDelay(2))
                    : null,
            ]
        );
    }
}
