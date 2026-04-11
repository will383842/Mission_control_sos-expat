<?php

namespace App\Services;

use App\Models\Influenceur;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that a contact's email domain matches their website domain.
 * If mismatch detected AND scraper found a better email on the actual site → auto-correct.
 *
 * Generic (consumer) and junk email domains are loaded from
 * config/email_providers.php so the lists can be extended without code changes.
 * A minimal hardcoded fallback is kept in this class in case the config is
 * missing (e.g. right after a deploy before cache is rebuilt).
 */
class EmailDomainMatchService
{
    // Fallback junk list — used only if config/email_providers.php is missing.
    // The real source of truth is config('email_providers.junk').
    private const JUNK_EMAIL_PATTERNS_FALLBACK = [
        'flywheel.local', 'localhost', 'example.com', 'example.org',
        'test.com', 'test.org', 'domain.com', 'email.com',
        'monsite.fr', 'yoursite.com', 'sentry.io', 'wixpress.com',
        'wix.com', 'squarespace.com', 'wordpress.com',
        'mailinator.com', 'guerrillamail.com', 'tempmail.com',
    ];

    // Fallback generic providers list — used only if config is missing.
    // The real source of truth is config('email_providers.generic').
    private const GENERIC_PROVIDERS_FALLBACK = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr',
        'outlook.com', 'outlook.fr', 'live.com', 'live.fr',
        'icloud.com', 'protonmail.com', 'proton.me',
        'free.fr', 'orange.fr', 'sfr.fr', 'laposte.net',
        'aol.com', 'mail.com', 'gmx.com', 'gmx.fr', 'zoho.com',
    ];

    /**
     * Return the list of generic consumer email providers.
     * Reads from config/email_providers.php with a hardcoded fallback.
     *
     * @return array<int, string>
     */
    private function genericProviders(): array
    {
        $fromConfig = Config::get('email_providers.generic');
        if (is_array($fromConfig) && count($fromConfig) > 0) {
            return $fromConfig;
        }
        return self::GENERIC_PROVIDERS_FALLBACK;
    }

    /**
     * Return the list of junk/disposable/placeholder email domains/patterns.
     *
     * @return array<int, string>
     */
    private function junkPatterns(): array
    {
        $fromConfig = Config::get('email_providers.junk');
        if (is_array($fromConfig) && count($fromConfig) > 0) {
            return $fromConfig;
        }
        return self::JUNK_EMAIL_PATTERNS_FALLBACK;
    }

    /**
     * Run email/site match check and auto-correction on a batch of contacts.
     */
    public function runBatch(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'junk_cleaned' => 0, 'generic_skipped' => 0, 'mismatches' => 0, 'auto_corrected' => 0];

        $junkPatterns = $this->junkPatterns();

        // Step 1: Clean junk emails
        $junkContacts = Influenceur::whereNotNull('email')
            ->where(function ($q) use ($junkPatterns) {
                foreach ($junkPatterns as $pattern) {
                    $q->orWhere('email', 'LIKE', '%' . $pattern);
                }
            })
            ->limit($limit)
            ->get();

        foreach ($junkContacts as $inf) {
            Log::info('EmailDomainMatch: cleaning junk email', ['id' => $inf->id, 'email' => $inf->email]);
            $inf->update(['email' => null, 'email_verified_status' => 'unverified']);
            $stats['junk_cleaned']++;
        }

        // Step 2: Check email vs site domain match
        $contacts = Influenceur::whereNotNull('email')
            ->whereNotNull('website_url')
            ->where('quality_score', '>', 0) // Already scored = not brand new
            ->limit($limit)
            ->get();

        foreach ($contacts as $inf) {
            $stats['checked']++;
            try {
                $result = $this->checkMatch($inf);

                if ($result === 'mismatch_corrected') {
                    $stats['auto_corrected']++;
                } elseif ($result === 'mismatch_flagged') {
                    $stats['mismatches']++;
                } elseif ($result === 'generic') {
                    $stats['generic_skipped']++;
                }
            } catch (\Throwable $e) {
                // Never let a single contact crash the whole batch.
                Log::error('EmailDomainMatch: unexpected error on contact', [
                    'id'    => $inf->id,
                    'email' => $inf->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Check if a contact's email domain matches their website domain.
     * Returns: 'ok' | 'generic' | 'mismatch_corrected' | 'mismatch_flagged'
     */
    public function checkMatch(Influenceur $inf): string
    {
        if (!$inf->email || !$inf->website_url) return 'ok';

        $emailDomain = $this->getEmailDomain($inf->email);
        $siteDomain = $this->getSiteDomain($inf->website_url);

        if (!$emailDomain || !$siteDomain) return 'ok';

        // Generic providers (gmail, yahoo, orange.fr, qq.com, etc.) are never
        // considered mismatches — an SMB, school or freelance can legitimately
        // use a free consumer email alongside a professional website.
        if (in_array($emailDomain, $this->genericProviders(), true)) {
            Log::debug('EmailDomainMatch: skipping generic provider', [
                'id'           => $inf->id,
                'email_domain' => $emailDomain,
            ]);
            return 'generic';
        }

        // Check if domains match (including subdomains)
        if ($this->domainsMatch($emailDomain, $siteDomain)) return 'ok';

        // MISMATCH detected — email domain doesn't match site domain
        Log::info('EmailDomainMatch: mismatch detected', [
            'id'           => $inf->id,
            'name'         => $inf->name,
            'email'        => $inf->email,
            'email_domain' => $emailDomain,
            'site_domain'  => $siteDomain,
        ]);

        // Try to find a better email from scraped data
        $scrapedEmails = $inf->scraped_emails ?? [];
        $bestEmail = $this->findBestEmailForSite($scrapedEmails, $siteDomain);

        if ($bestEmail) {
            // Safety check: do not auto-correct to an email already used by another
            // contact — the `influenceurs_email_unique` constraint would throw and
            // crash the whole batch. Fall through to the flag-for-review path instead.
            $conflict = Influenceur::whereRaw('LOWER(email) = ?', [strtolower($bestEmail)])
                ->where('id', '!=', $inf->id)
                ->exists();

            if ($conflict) {
                Log::info('EmailDomainMatch: cannot auto-correct — target email already used by another contact', [
                    'id'             => $inf->id,
                    'current_email'  => $inf->email,
                    'proposed_email' => $bestEmail,
                ]);
                // fall through to flag-for-review
            } else {
                // Auto-correct: replace with email that matches the site
                $oldEmail = $inf->email;
                try {
                    $inf->update([
                        'email'                 => $bestEmail,
                        'email_verified_status' => 'unverified', // Needs re-verification
                    ]);
                    Log::info('EmailDomainMatch: auto-corrected', [
                        'id'        => $inf->id,
                        'old_email' => $oldEmail,
                        'new_email' => $bestEmail,
                    ]);
                    return 'mismatch_corrected';
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Race condition: another process inserted the same email between
                    // our check and update. Fall through to flag-for-review rather
                    // than crashing the batch.
                    Log::warning('EmailDomainMatch: race condition on auto-correct, flagging instead', [
                        'id'             => $inf->id,
                        'proposed_email' => $bestEmail,
                    ]);
                }
            }
        }

        // No better email found — flag for review
        \App\Models\TypeVerificationFlag::firstOrCreate(
            ['influenceur_id' => $inf->id, 'reason' => 'email_domain_mismatch'],
            [
                'current_type'   => $inf->contact_type instanceof \App\Enums\ContactType ? $inf->contact_type->value : $inf->contact_type,
                'suggested_type' => null,
                'details'        => [
                    'email'        => $inf->email,
                    'email_domain' => $emailDomain,
                    'site_domain'  => $siteDomain,
                    'site_url'     => $inf->website_url,
                ],
                'status' => 'pending',
            ]
        );
        return 'mismatch_flagged';
    }

    /**
     * From a list of scraped emails, find the best one matching the site domain.
     */
    private function findBestEmailForSite(array $scrapedEmails, string $siteDomain): ?string
    {
        // Priority: exact domain match first, then subdomain match
        foreach ($scrapedEmails as $email) {
            $domain = $this->getEmailDomain($email);
            if ($domain && $this->domainsMatch($domain, $siteDomain)) {
                // Validate it's not a junk email
                if (!$this->isJunkEmail($email)) {
                    return strtolower(trim($email));
                }
            }
        }
        return null;
    }

    private function domainsMatch(string $a, string $b): bool
    {
        $a = strtolower($a);
        $b = strtolower($b);

        // Exact match
        if ($a === $b) return true;

        // One is subdomain of the other (mail.example.com matches example.com)
        if (str_ends_with($a, '.' . $b) || str_ends_with($b, '.' . $a)) return true;

        // Root domain match (extract root: sub.example.co.uk → example.co.uk)
        $rootA = $this->getRootDomain($a);
        $rootB = $this->getRootDomain($b);
        if ($rootA && $rootB && $rootA === $rootB) return true;

        return false;
    }

    private function getRootDomain(string $domain): string
    {
        $parts = explode('.', $domain);
        $count = count($parts);
        if ($count <= 2) return $domain;

        // Handle two-part TLDs: co.uk, ac.th, com.au, etc.
        $twoPartTlds = ['co.uk', 'co.jp', 'co.kr', 'co.th', 'ac.th', 'ac.uk', 'ac.jp',
            'com.au', 'com.br', 'com.mx', 'org.uk', 'net.au', 'co.za', 'co.in',
            'com.sg', 'com.my', 'com.ph', 'co.nz', 'or.jp', 'or.kr', 'asso.fr'];
        $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];
        if (in_array($lastTwo, $twoPartTlds) && $count > 2) {
            return $parts[$count - 3] . '.' . $lastTwo;
        }

        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }

    private function getEmailDomain(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) return null;
        return strtolower(substr($email, strpos($email, '@') + 1));
    }

    private function getSiteDomain(?string $url): ?string
    {
        if (!$url) return null;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        return preg_replace('/^www\./', '', strtolower($host));
    }

    private function isJunkEmail(string $email): bool
    {
        $domain = $this->getEmailDomain($email);
        if (!$domain) return true;
        foreach ($this->junkPatterns() as $pattern) {
            if (str_contains($domain, $pattern)) return true;
        }
        // Reject emails starting with noreply, dpo, etc.
        $local = strtolower(substr($email, 0, strpos($email, '@')));
        if (in_array($local, ['noreply', 'no-reply', 'donotreply', 'postmaster', 'webmaster', 'dpo', 'abuse', 'spam', 'signalement'], true)) {
            return true;
        }
        return false;
    }
}
