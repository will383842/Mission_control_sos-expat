<?php

namespace App\Services;

use App\Models\OutreachEmail;
use App\Models\WarmupState;
use Illuminate\Support\Facades\Log;

/**
 * Handles actual email sending via PMTA SMTP.
 * Adds tracking pixel + unsubscribe link before sending.
 */
class EmailSendingService
{
    // PMTA connection (from email_sos-expat_transactionnel config)
    private const PMTA_HOST = '46.62.168.55';
    private const PMTA_PORT = 2525;
    private const PMTA_USER = 'admin@ulixai-expat.com';
    private const PMTA_PASS = 'WJullin1974/*%$';

    // Tracking base URL (public endpoint on influenceurs tracker)
    private string $trackingBaseUrl;

    public function __construct()
    {
        $this->trackingBaseUrl = rtrim(config('app.url', 'https://influenceurs.life-expat.com'), '/');
    }

    /**
     * Send a single outreach email via PMTA.
     */
    public function send(OutreachEmail $email): bool
    {
        // Check warmup limit
        $warmup = WarmupState::getFor($email->from_email);
        if (!$warmup->canSend()) {
            Log::info('EmailSending: warmup limit reached', ['from' => $email->from_email, 'limit' => $warmup->current_daily_limit]);
            return false;
        }

        // Build the final HTML with tracking
        $html = $this->injectTracking($email);

        // Build raw email message
        $to = $email->influenceur->email;
        if (!$to) {
            $email->update(['status' => 'failed', 'error_message' => 'No recipient email']);
            return false;
        }

        $headers = $this->buildHeaders($email, $to);
        $rawMessage = $headers . "\r\n" . $html;

        try {
            $email->update(['status' => 'sending']);

            $success = $this->sendViaSMTP($email->from_email, $to, $rawMessage);

            if ($success) {
                $email->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
                $warmup->recordSent();

                // Update influenceur pipeline status
                $this->updatePipelineStatus($email);

                Log::info('Email sent', ['id' => $email->id, 'to' => $to, 'step' => $email->step]);
                return true;
            } else {
                $email->update(['status' => 'failed', 'error_message' => 'SMTP send failed']);
                return false;
            }

        } catch (\Throwable $e) {
            $email->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('Email send failed', ['id' => $email->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Inject tracking pixel and unsubscribe link into HTML body.
     */
    private function injectTracking(OutreachEmail $email): string
    {
        $html = $email->body_html;
        $apiBase = $this->trackingBaseUrl . '/api';

        // Tracking pixel (1x1 transparent gif)
        $pixelUrl = $apiBase . '/track/open/' . $email->tracking_id;
        $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none" alt="" />';

        // Unsubscribe link
        $unsubUrl = $apiBase . '/unsubscribe/' . $email->unsubscribe_token;
        $unsubLink = '<p style="font-size:11px;color:#999;margin-top:30px;text-align:center;">'
            . '<a href="' . $unsubUrl . '" style="color:#999;text-decoration:underline;">Se desinscrire</a>'
            . '</p>';

        // Inject before closing body/html tag or at the end
        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $pixel . $unsubLink . '</body>', $html);
        } else {
            $html .= $pixel . $unsubLink;
        }

        return $html;
    }

    /**
     * Build email headers (MIME format).
     */
    private function buildHeaders(OutreachEmail $email, string $to): string
    {
        $boundary = md5(uniqid());

        $headers = "From: {$email->from_name} <{$email->from_email}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$email->subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: SOS-Expat-Outreach/1.0\r\n";
        $headers .= "List-Unsubscribe: <{$this->trackingBaseUrl}/api/unsubscribe/{$email->unsubscribe_token}>\r\n";
        $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        $headers .= "\r\n";

        // Plain text part
        $headers .= "--{$boundary}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $headers .= quoted_printable_encode($email->body_text) . "\r\n\r\n";

        // HTML part
        $headers .= "--{$boundary}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";

        return $headers;
    }

    /**
     * Send via PMTA SMTP (port 2525 with auth).
     */
    private function sendViaSMTP(string $from, string $to, string $message): bool
    {
        $socket = @fsockopen(self::PMTA_HOST, self::PMTA_PORT, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("PMTA connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 15);

        try {
            $this->readResponse($socket); // Banner
            $this->sendCommand($socket, "EHLO outreach.sos-expat.com");

            // AUTH LOGIN
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode(self::PMTA_USER));
            $this->sendCommand($socket, base64_encode(self::PMTA_PASS));

            $this->sendCommand($socket, "MAIL FROM:<{$from}>");
            $this->sendCommand($socket, "RCPT TO:<{$to}>");
            $this->sendCommand($socket, "DATA");

            fputs($socket, $message . "\r\n.\r\n");
            $response = $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");

            return str_starts_with(trim($response), '250');

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
            // Multi-line response: if 4th char is space, it's the last line
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }

    /**
     * Update influenceur pipeline status based on step sent.
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
            $inf->update([
                'status'          => $newStatus,
                'last_contact_at' => now(),
            ]);
        }
    }
}
