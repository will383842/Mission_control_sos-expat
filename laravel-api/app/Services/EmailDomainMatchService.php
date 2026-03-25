<?php

namespace App\Services;

use App\Models\Influenceur;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that a contact's email domain matches their website domain.
 * If mismatch detected AND scraper found a better email on the actual site → auto-correct.
 */
class EmailDomainMatchService
{
    // Emails that are clearly junk / dev / placeholder
    private const JUNK_EMAIL_PATTERNS = [
        'flywheel.local', 'localhost', 'example.com', 'example.org',
        'test.com', 'test.org', 'domain.com', 'email.com',
        'monsite.fr', 'yoursite.com', 'sentry.io', 'wixpress.com',
        'wix.com', 'squarespace.com', 'wordpress.com',
        'mailinator.com', 'guerrillamail.com', 'tempmail.com',
    ];

    // Generic email providers (not mismatches — someone can use gmail for a school)
    private const GENERIC_PROVIDERS = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr',
        'outlook.com', 'outlook.fr', 'live.com', 'live.fr',
        'icloud.com', 'protonmail.com', 'proton.me',
        'free.fr', 'orange.fr', 'sfr.fr', 'laposte.net',
        'aol.com', 'mail.com', 'gmx.com', 'gmx.fr', 'zoho.com',
    ];

    /**
     * Run email/site match check and auto-correction on a batch of contacts.
     */
    public function runBatch(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'junk_cleaned' => 0, 'mismatches' => 0, 'auto_corrected' => 0];

        // Step 1: Clean junk emails
        $junkContacts = Influenceur::whereNotNull('email')
            ->where(function ($q) {
                foreach (self::JUNK_EMAIL_PATTERNS as $pattern) {
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
            $result = $this->checkMatch($inf);

            if ($result === 'mismatch_corrected') {
                $stats['auto_corrected']++;
            } elseif ($result === 'mismatch_flagged') {
                $stats['mismatches']++;
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

        // Generic providers are never mismatches
        if (in_array($emailDomain, self::GENERIC_PROVIDERS)) return 'generic';

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
            // Auto-correct: replace with email that matches the site
            $oldEmail = $inf->email;
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
        foreach (self::JUNK_EMAIL_PATTERNS as $pattern) {
            if (str_contains($domain, $pattern)) return true;
        }
        // Reject emails starting with noreply, dpo, etc.
        $local = strtolower(substr($email, 0, strpos($email, '@')));
        if (in_array($local, ['noreply', 'no-reply', 'donotreply', 'postmaster', 'webmaster', 'dpo', 'abuse', 'spam', 'signalement'])) {
            return true;
        }
        return false;
    }
}
