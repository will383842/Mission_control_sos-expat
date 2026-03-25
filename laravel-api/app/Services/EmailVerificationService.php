<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\Influenceur;
use Illuminate\Support\Facades\Log;

class EmailVerificationService
{
    /**
     * Verify a single email: MX check + optional SMTP check.
     */
    public function verify(Influenceur $inf): ?EmailVerification
    {
        if (!$inf->email) return null;

        $email = strtolower(trim($inf->email));
        $domain = substr($email, strpos($email, '@') + 1);

        // MX check
        $mxValid = false;
        $mxDomain = null;
        try {
            $mxRecords = [];
            if (dns_get_record($domain, DNS_MX, $_, $mxRecords) && count($mxRecords) > 0) {
                $mxValid = true;
                $mxDomain = $mxRecords[0]['target'] ?? null;
            } else {
                // Fallback: check A record (some domains don't have MX but accept mail)
                $aRecords = dns_get_record($domain, DNS_A);
                if (!empty($aRecords)) {
                    $mxValid = true;
                    $mxDomain = $domain . ' (A record)';
                }
            }
        } catch (\Throwable $e) {
            Log::debug('MX check failed', ['email' => $email, 'error' => $e->getMessage()]);
        }

        // SMTP check (only if MX is valid)
        $smtpValid = null;
        $smtpResponse = null;
        if ($mxValid && $mxDomain && !str_contains($mxDomain, 'A record')) {
            try {
                $result = $this->smtpCheck($email, $mxDomain);
                $smtpValid = $result['valid'];
                $smtpResponse = $result['response'];
            } catch (\Throwable $e) {
                $smtpResponse = 'SMTP check error: ' . $e->getMessage();
                Log::debug('SMTP check failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        // Determine status
        $status = $this->determineStatus($mxValid, $smtpValid, $smtpResponse);

        // Save or update
        $verification = EmailVerification::updateOrCreate(
            ['influenceur_id' => $inf->id],
            [
                'email'         => $email,
                'mx_valid'      => $mxValid,
                'mx_domain'     => $mxDomain,
                'smtp_valid'    => $smtpValid,
                'smtp_response' => $smtpResponse,
                'status'        => $status,
                'checked_at'    => now(),
            ]
        );

        // Update influenceur
        $inf->update([
            'email_verified_status' => $status,
            'email_verified_at'     => now(),
        ]);

        return $verification;
    }

    /**
     * Batch verify emails. Only processes unverified contacts.
     */
    public function batchVerify(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'verified' => 0, 'invalid' => 0, 'risky' => 0, 'errors' => 0];

        $contacts = Influenceur::whereNotNull('email')
            ->where('email_verified_status', 'unverified')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($contacts as $inf) {
            try {
                $result = $this->verify($inf);
                $stats['processed']++;
                if ($result) {
                    match ($result->status) {
                        'verified'  => $stats['verified']++,
                        'invalid'   => $stats['invalid']++,
                        'risky', 'catch_all' => $stats['risky']++,
                        default     => null,
                    };
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('Email verification failed', ['id' => $inf->id, 'error' => $e->getMessage()]);
            }

            // Rate limit: pause 2s between SMTP checks
            usleep(2_000_000);
        }

        return $stats;
    }

    /**
     * SMTP mailbox check without sending an email.
     */
    private function smtpCheck(string $email, string $mxHost): array
    {
        $response = '';
        $valid = false;

        $socket = @fsockopen($mxHost, 25, $errno, $errstr, 10);
        if (!$socket) {
            // Try port 587
            $socket = @fsockopen($mxHost, 587, $errno, $errstr, 10);
        }
        if (!$socket) {
            return ['valid' => null, 'response' => "Connection failed: $errstr ($errno)"];
        }

        stream_set_timeout($socket, 10);

        try {
            $response .= fgets($socket, 1024);

            fputs($socket, "EHLO verify.sos-expat.com\r\n");
            $response .= fgets($socket, 1024);

            fputs($socket, "MAIL FROM:<verify@sos-expat.com>\r\n");
            $response .= $line = fgets($socket, 1024);

            fputs($socket, "RCPT TO:<{$email}>\r\n");
            $response .= $line = fgets($socket, 1024);

            // 250 = valid, 550/551/552/553 = invalid, 450/451 = temp error (risky)
            $code = (int) substr(trim($line), 0, 3);
            $valid = $code === 250;

            fputs($socket, "QUIT\r\n");
        } finally {
            @fclose($socket);
        }

        return ['valid' => $valid, 'response' => trim($response)];
    }

    private function determineStatus(bool $mxValid, ?bool $smtpValid, ?string $smtpResponse): string
    {
        if (!$mxValid) return 'invalid';

        if ($smtpValid === true) return 'verified';
        if ($smtpValid === false) {
            // Check if it's a catch-all
            if ($smtpResponse && str_contains(strtolower($smtpResponse), 'catch')) {
                return 'catch_all';
            }
            return 'invalid';
        }

        // SMTP not checked or inconclusive
        if ($smtpResponse && preg_match('/^4\d\d/', trim($smtpResponse))) {
            return 'risky'; // Temporary rejection
        }

        return $mxValid ? 'risky' : 'unknown';
    }
}
