<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; MissionControlBot/1.0; +https://life-expat.com)';
    private const TIMEOUT = 10;
    private const MAX_REDIRECTS = 3;
    private const MAX_PAGES = 8;
    private const DELAY_BETWEEN_REQUESTS_MS = 2000;

    /**
     * Contact/about page paths to try after the main page.
     */
    private const CONTACT_PATHS = [
        '/contact',
        '/contact-us',
        '/contact.html',
        '/contact.php',
        '/about',
        '/about-us',
        '/about.html',
        '/a-propos',
        '/nous-contacter',
        '/contactez-nous',
        '/impressum',
        '/kontakt',
        '/info',
        '/information',
        '/team',
        '/staff',
        '/our-team',
        '/notre-equipe',
        '/equipe',
        '/direction',
        '/administration',
        '/coordonnees',
        '/footer',
    ];

    /**
     * Social media domains we should never scrape (handled differently).
     */
    private const SKIP_DOMAINS = [
        'youtube.com', 'youtu.be',
        'tiktok.com',
        'instagram.com',
        'facebook.com', 'fb.com',
        'x.com', 'twitter.com',
        'linkedin.com',
    ];

    /**
     * Binary/non-HTML content types to skip.
     */
    private const SKIP_CONTENT_TYPES = [
        'image/', 'video/', 'audio/', 'application/pdf',
        'application/zip', 'application/octet-stream',
    ];

    /**
     * Scrape a URL and extract contact information.
     *
     * @return array{emails: string[], phones: string[], social_links: array, addresses: string[], contact_persons: string[], scraped_pages: string[], success: bool, error?: string}
     */
    public function scrape(string $url): array
    {
        $result = [
            'emails'          => [],
            'phones'          => [],
            'social_links'    => [],
            'addresses'       => [],
            'contact_persons' => [],
            'scraped_pages'   => [],
            'success'         => false,
            'error'           => null,
        ];

        try {
            $parsed = parse_url($url);
            if (!$parsed || empty($parsed['host'])) {
                $result['error'] = 'Invalid URL';
                return $result;
            }

            $domain = strtolower($parsed['host']);

            // Skip social media platforms
            foreach (self::SKIP_DOMAINS as $skipDomain) {
                if (str_contains($domain, $skipDomain)) {
                    $result['error'] = "Skipped social platform: {$skipDomain}";
                    return $result;
                }
            }

            // Normalize base URL
            $scheme = $parsed['scheme'] ?? 'https';
            $baseUrl = "{$scheme}://{$domain}";

            // Check robots.txt
            if (!$this->isAllowedByRobotsTxt($baseUrl)) {
                $result['error'] = 'Blocked by robots.txt';
                return $result;
            }

            // 1. Scrape the main page (with HTTPS→HTTP fallback)
            $mainHtml = $this->fetchPageWithFallback($url, $baseUrl);
            if ($mainHtml !== null) {
                $result['scraped_pages'][] = $url;
                $this->extractFromHtml($mainHtml, $result);
            }

            // Also try the URL without /fr/ or /en/ prefix if present
            $altUrl = $this->stripLocalePrefix($url);
            if ($altUrl !== null && $this->normalizeUrl($altUrl) !== $this->normalizeUrl($url)) {
                $altHtml = $this->fetchPageWithFallback($altUrl, $baseUrl);
                if ($altHtml !== null && count($result['scraped_pages']) < self::MAX_PAGES) {
                    $result['scraped_pages'][] = $altUrl;
                    $this->extractFromHtml($altHtml, $result);
                }
            }

            // 2. Discover contact pages from links found in main page HTML
            $discoveredPages = [];
            if ($mainHtml !== null) {
                $discoveredPages = $this->discoverContactPages($mainHtml, $baseUrl);
            }

            // Track already-scraped normalized URLs to avoid duplicates
            $scrapedNormalized = array_map([$this, 'normalizeUrl'], $result['scraped_pages']);

            // 3. Try discovered pages FIRST (more likely to be real contact pages)
            foreach ($discoveredPages as $pageUrl) {
                if (count($result['scraped_pages']) >= self::MAX_PAGES) {
                    break;
                }

                $normalized = $this->normalizeUrl($pageUrl);
                if (in_array($normalized, $scrapedNormalized, true)) {
                    continue;
                }

                usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);

                $html = $this->fetchPage($pageUrl);
                if ($html !== null) {
                    $result['scraped_pages'][] = $pageUrl;
                    $scrapedNormalized[] = $normalized;
                    $this->extractFromHtml($html, $result);
                }
            }

            // 4. Try hardcoded contact/about pages (up to MAX_PAGES total)
            foreach (self::CONTACT_PATHS as $path) {
                if (count($result['scraped_pages']) >= self::MAX_PAGES) {
                    break;
                }

                $pageUrl = rtrim($baseUrl, '/') . $path;

                $normalized = $this->normalizeUrl($pageUrl);
                if (in_array($normalized, $scrapedNormalized, true)) {
                    continue;
                }

                // Rate limit between requests
                usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);

                $html = $this->fetchPage($pageUrl);
                if ($html !== null) {
                    $result['scraped_pages'][] = $pageUrl;
                    $scrapedNormalized[] = $normalized;
                    $this->extractFromHtml($html, $result);
                }
            }

            // Deduplicate results
            $result['emails']          = array_values(array_unique($result['emails']));
            $result['phones']          = array_values(array_unique($result['phones']));
            $result['social_links']    = $this->deduplicateSocialLinks($result['social_links']);
            $result['addresses']       = array_values(array_unique($result['addresses']));
            $result['contact_persons'] = array_values(array_unique($result['contact_persons']));
            $result['success']         = true;

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('WebScraper: unexpected error', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Fetch a single page, returning the HTML body or null on failure.
     */
    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'text/html,application/xhtml+xml',
                ])
                ->timeout(self::TIMEOUT)
                ->maxRedirects(self::MAX_REDIRECTS)
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            // Skip binary content
            $contentType = $response->header('Content-Type') ?? '';
            foreach (self::SKIP_CONTENT_TYPES as $skipType) {
                if (str_contains(strtolower($contentType), $skipType)) {
                    return null;
                }
            }

            $body = $response->body();

            // Sanity check: must look like HTML
            if (!str_contains(strtolower(substr($body, 0, 1000)), '<html')
                && !str_contains(strtolower(substr($body, 0, 1000)), '<!doctype')
                && !str_contains(strtolower($contentType), 'text/html')) {
                return null;
            }

            return $body;

        } catch (\Throwable $e) {
            Log::debug('WebScraper: failed to fetch page', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if our bot is allowed by the site's robots.txt.
     */
    private function isAllowedByRobotsTxt(string $baseUrl): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(5)
                ->get(rtrim($baseUrl, '/') . '/robots.txt');

            if (!$response->successful()) {
                // No robots.txt = assume allowed
                return true;
            }

            $body = strtolower($response->body());

            // Simple check: look for "user-agent: *" followed by "disallow: /"
            // This is intentionally simple — we respect full-site blocks only
            if (preg_match('/user-agent:\s*\*.*?disallow:\s*\/\s*$/m', $body)) {
                // Check if there's an explicit allow that overrides it
                if (!str_contains($body, 'allow: /')) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            // Can't fetch robots.txt = assume allowed
            return true;
        }
    }

    /**
     * Extract emails, phones, and social links from HTML content.
     */
    private function extractFromHtml(string $html, array &$result): void
    {
        // Strip scripts and styles first to avoid false positives
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $cleaned);

        // Extract from raw HTML (catches mailto: and href links)
        $this->extractEmails($html, $result['emails']);
        $this->extractEmails($cleaned, $result['emails']);

        // Strip HTML tags for text-based extraction
        $text = strip_tags($cleaned);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Extract from plain text
        $this->extractEmails($text, $result['emails']);
        $this->extractPhones($text, $result['phones']);
        $this->extractSocialLinks($html, $result['social_links'], $result['phones']);
        $this->extractAddresses($text, $result['addresses']);

        // Extract contact person names (wrapped in try/catch — regex can fail on exotic HTML)
        try {
            $persons = $this->extractContactPersons($text);
            foreach ($persons as $person) {
                $result['contact_persons'][] = $person;
            }
        } catch (\Throwable $e) {
            Log::debug('WebScraper: person extraction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Extract email addresses using regex.
     */
    private function extractEmails(string $text, array &$emails): void
    {
        // Match mailto: links
        if (preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Match email patterns in text
        if (preg_match_all('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }
    }

    /**
     * Extract phone numbers using regex (international formats).
     */
    private function extractPhones(string $text, array &$phones): void
    {
        // International formats: +33 1 23 45 67 89, +66-2-123-4567, (212) 555-1234, etc.
        $patterns = [
            '/(\+\d{1,3}[\s.\-]?\(?\d{1,4}\)?[\s.\-]?\d{1,4}[\s.\-]?\d{1,4}[\s.\-]?\d{0,4})/',
            '/(\(\d{2,4}\)\s?\d{3,4}[\s.\-]?\d{3,4})/',
            '/(\b0\d[\s.\-]?\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}\b)/',  // French: 01 23 45 67 89
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $phone) {
                    $phone = trim($phone);
                    if ($this->isValidPhone($phone)) {
                        $phones[] = $this->normalizePhone($phone);
                    }
                }
            }
        }
    }

    /**
     * Extract social media profile URLs from HTML href attributes.
     */
    private function extractSocialLinks(string $html, array &$socialLinks, array &$phones = []): void
    {
        $platforms = [
            'facebook'  => 'facebook\.com/[a-zA-Z0-9._\-]+',
            'linkedin'  => 'linkedin\.com/(in|company)/[a-zA-Z0-9._\-]+',
            'twitter'   => '(twitter\.com|x\.com)/[a-zA-Z0-9._\-]+',
            'instagram' => 'instagram\.com/[a-zA-Z0-9._\-]+',
            'tiktok'    => 'tiktok\.com/@[a-zA-Z0-9._\-]+',
            'youtube'   => 'youtube\.com/(@[a-zA-Z0-9._\-]+|c/[a-zA-Z0-9._\-]+|channel/[a-zA-Z0-9._\-]+)',
            'pinterest' => 'pinterest\.(com|fr|de|co\.uk)/[a-zA-Z0-9._\-]+',
            'telegram'  => 't\.me/[a-zA-Z0-9._\-]+',
            'skype'     => '(join\.skype\.com/[a-zA-Z0-9._\-]+)',
            'line'      => 'line\.me/(R/ti/p/|R/)?[a-zA-Z0-9._\-~@]+',
        ];

        foreach ($platforms as $platform => $pattern) {
            if (preg_match_all('/https?:\/\/(www\.)?' . $pattern . '/i', $html, $matches)) {
                foreach ($matches[0] as $url) {
                    $url = rtrim($url, '/');
                    // Skip generic pages (login, share, etc.)
                    if (preg_match('/(login|share|sharer|dialog|intent)/i', $url)) {
                        continue;
                    }
                    $socialLinks[$platform] = $socialLinks[$platform] ?? $url;
                }
            }
        }

        // WhatsApp: wa.me/XXXXX and api.whatsapp.com/send?phone=XXXXX
        if (preg_match_all('/https?:\/\/(www\.)?wa\.me\/(\d+)/i', $html, $matches)) {
            foreach ($matches[0] as $idx => $url) {
                $url = rtrim($url, '/');
                $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? $url;
                // Also extract the phone number
                $phoneNumber = '+' . $matches[2][$idx];
                if ($this->isValidPhone($phoneNumber)) {
                    $phones[] = $this->normalizePhone($phoneNumber);
                }
            }
        }

        if (preg_match_all('/https?:\/\/(www\.)?api\.whatsapp\.com\/send\?phone=(\d+)/i', $html, $matches)) {
            foreach ($matches[0] as $idx => $url) {
                $cleanUrl = preg_replace('/&.*$/', '', $url); // Keep only phone param
                $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? $cleanUrl;
                // Also extract the phone number
                $phoneNumber = '+' . $matches[2][$idx];
                if ($this->isValidPhone($phoneNumber)) {
                    $phones[] = $this->normalizePhone($phoneNumber);
                }
            }
        }

        // Skype: skype:username pattern (not a URL, found in href="skype:...")
        if (!isset($socialLinks['skype'])) {
            if (preg_match('/skype:([a-zA-Z0-9._\-]+)/i', $html, $match)) {
                $socialLinks['skype'] = 'skype:' . $match[1];
            }
        }

        // WeChat: detect common wechat ID patterns (often displayed as text/images)
        if (preg_match('/(?:wechat|weixin|微信)\s*(?:id|ID|Id)?\s*[:：]\s*([a-zA-Z0-9_\-]+)/i', $html, $match)) {
            $socialLinks['wechat'] = $socialLinks['wechat'] ?? 'wechat:' . $match[1];
        }
    }

    /**
     * Extract postal addresses from plain text.
     * Wrapped in try/catch because regex on arbitrary HTML can fail.
     */
    private function extractAddresses(string $text, array &$addresses): void
    {
        try {
            $this->doExtractAddresses($text, $addresses);
        } catch (\Throwable $e) {
            Log::debug('WebScraper: address extraction failed', ['error' => $e->getMessage()]);
        }
    }

    private function doExtractAddresses(string $text, array &$addresses): void
    {
        // Normalize whitespace for easier matching
        $text = preg_replace('/\s+/', ' ', $text);

        // Pattern 1: Street number + street name (French style: "12 Rue de la Paix", "123 Avenue des Champs")
        $streetPatterns = [
            '/\b(\d{1,5}\s*,?\s*(?:rue|avenue|boulevard|bvd|blvd|allée|impasse|chemin|place|cours|passage|route|voie)\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]{3,50}(?:\s*,\s*\d{4,5}\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]+)?)/iu',
            // English style: "123 Main Street", "456 Oak Avenue"
            '/\b(\d{1,5}\s+[A-Za-z\s\'\-]{2,30}\s+(?:street|st|road|rd|avenue|ave|boulevard|blvd|drive|dr|lane|ln|way|court|ct|place|pl|circle|cir|terrace|ter)\.?\b(?:\s*,\s*[A-Za-z\s]+(?:\s*,\s*[A-Z]{2}\s+\d{5}(?:-\d{4})?)?)?)/iu',
        ];

        foreach ($streetPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $address) {
                    $address = trim($address, " ,.");
                    if (mb_strlen($address) >= 10 && mb_strlen($address) <= 200) {
                        $addresses[] = $address;
                    }
                }
            }
        }

        // Pattern 2: BP / PO Box patterns
        if (preg_match_all('/\b((?:BP|B\.P\.|P\.?O\.?\s*Box)\s*\d{1,6}(?:\s*,\s*\d{4,5}\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]+)?)/iu', $text, $matches)) {
            foreach ($matches[1] as $address) {
                $address = trim($address, " ,.");
                if (mb_strlen($address) >= 5) {
                    $addresses[] = $address;
                }
            }
        }

        // Pattern 3: Zip code + city near address keywords
        $keywords = 'adresse|address|siège|siege|localisation|location|bureau|office|headquarter|sitz';
        // Look for content near address keywords: capture up to 150 chars after keyword
        if (preg_match_all('/(?:' . $keywords . ')\s*[:：]?\s*(.{10,150})/iu', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                // Must contain a zip code pattern (4-5 digits) to be considered an address
                if (preg_match('/\b\d{4,5}\b/', $candidate)) {
                    // Trim at sentence boundary
                    $candidate = preg_replace('/[.!?|].*$/', '', $candidate);
                    $candidate = trim($candidate, " ,.\t\n\r");
                    if (mb_strlen($candidate) >= 10 && mb_strlen($candidate) <= 200) {
                        $addresses[] = $candidate;
                    }
                }
            }
        }
    }

    /**
     * Validate an email address.
     */
    private function isValidEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Skip technical/spam/system emails
        $skipPatterns = [
            'noreply@', 'no-reply@', 'mailer-daemon@',
            'postmaster@', 'webmaster@', 'hostmaster@', 'abuse@',
            '@example.', '@test.', '@localhost',
            '@sentry', '@wixpress', '@wix.com', '@squarespace',
            '@wordpress', '@cloudflare', '@google', '@gstatic',
            '@googleapis', '@jquery', '@bootstrap',
            '@github', '@sentry.io', '@sentry-next',
            '.png', '.jpg', '.gif', '.svg', '.webp', '.css', '.js',
            'donotreply', 'do-not-reply', 'unsubscribe',
            'tracking@', 'pixel@', 'analytics@',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains(strtolower($email), $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a phone number (at least 8 digits after stripping formatting).
     */
    private function isValidPhone(string $phone): bool
    {
        $digitsOnly = preg_replace('/\D/', '', $phone);
        return strlen($digitsOnly) >= 8 && strlen($digitsOnly) <= 15;
    }

    /**
     * Normalize a phone number (keep + prefix, strip excess formatting).
     */
    private function normalizePhone(string $phone): string
    {
        // Preserve leading +, strip everything except digits and +
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/[^\d]/', '', $phone);

        return $hasPlus ? "+{$digits}" : $digits;
    }

    /**
     * Normalize a URL for comparison purposes.
     */
    private function normalizeUrl(string $url): string
    {
        $url = strtolower(rtrim($url, '/'));
        $url = preg_replace('/^https?:\/\/(www\.)?/', '', $url);
        return $url;
    }

    /**
     * Fetch a page with HTTPS→HTTP fallback.
     */
    private function fetchPageWithFallback(string $url, string $baseUrl): ?string
    {
        $html = $this->fetchPage($url);
        if ($html !== null) {
            return $html;
        }

        // If HTTPS failed, try HTTP
        if (str_starts_with($url, 'https://')) {
            $httpUrl = preg_replace('/^https:/', 'http:', $url);
            $html = $this->fetchPage($httpUrl);
            if ($html !== null) {
                return $html;
            }
        }

        return null;
    }

    /**
     * Strip locale prefix from URL path (e.g., /fr/, /en/, /de/).
     * Returns the modified URL or null if no locale prefix found.
     */
    private function stripLocalePrefix(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        // Match common locale prefixes: /fr/, /en/, /de/, /es/, /it/, /pt/, /nl/, /pl/, /ru/, etc.
        if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/.*)?$#i', $path, $matches)) {
            $locale = strtolower($matches[1]);
            $rest = $matches[2] ?? '/';
            if (in_array($locale, ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'pl', 'ru', 'ar', 'zh', 'ja', 'ko', 'th', 'vi', 'tr'])) {
                $scheme = $parsed['scheme'] ?? 'https';
                $host = $parsed['host'] ?? '';
                return "{$scheme}://{$host}{$rest}";
            }
        }

        return null;
    }

    /**
     * Discover contact/about pages from links found in page HTML.
     * Returns an array of full URLs to try.
     */
    private function discoverContactPages(string $html, string $baseUrl): array
    {
        $pages = [];

        if (preg_match_all('/href=["\']([^"\']+)["\']/', $html, $matches)) {
            $contactKeywords = [
                'contact', 'about', 'team', 'staff', 'equipe', 'direction',
                'admin', 'info', 'coordonn', 'nous-contacter', 'a-propos',
                'impressum', 'kontakt', 'who-we-are', 'our-people',
                'notre-equipe', 'our-team', 'contactez', 'joindre',
            ];

            $baseHost = parse_url($baseUrl, PHP_URL_HOST);

            foreach ($matches[1] as $href) {
                $hrefLower = strtolower($href);

                // Skip anchors, javascript, mailto, tel
                if (str_starts_with($hrefLower, '#') || str_starts_with($hrefLower, 'javascript:')
                    || str_starts_with($hrefLower, 'mailto:') || str_starts_with($hrefLower, 'tel:')) {
                    continue;
                }

                // Check if it contains a contact keyword
                foreach ($contactKeywords as $keyword) {
                    if (str_contains($hrefLower, $keyword)) {
                        // Resolve to full URL
                        if (str_starts_with($href, '/')) {
                            $pages[] = rtrim($baseUrl, '/') . $href;
                        } elseif (str_starts_with($hrefLower, 'http')) {
                            $hrefHost = parse_url($href, PHP_URL_HOST);
                            if ($hrefHost && str_contains($hrefHost, $baseHost)) {
                                $pages[] = $href;
                            }
                        }
                        break;
                    }
                }
            }
        }

        return array_unique($pages);
    }

    /**
     * Extract contact person names found near titles/roles or email addresses.
     */
    private function extractContactPersons(string $text): array
    {
        $persons = [];

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Pattern 1: Title/role followed by a name
        // e.g., "Director: John Smith", "Directeur : Marie Dupont", "President - Jane Doe"
        $titlePatterns = [
            // English titles
            'director', 'principal', 'president', 'chairman', 'chairwoman', 'chairperson',
            'ceo', 'cto', 'cfo', 'coo', 'founder', 'co-founder', 'cofounder',
            'manager', 'head of', 'chief', 'lead', 'coordinator', 'supervisor',
            'owner', 'partner', 'editor', 'publisher', 'secretary', 'treasurer',
            // French titles
            'directeur', 'directrice', 'président', 'présidente', 'responsable',
            'gérant', 'gérante', 'fondateur', 'fondatrice', 'rédacteur', 'rédactrice',
            'coordinateur', 'coordinatrice', 'secrétaire', 'trésorier', 'trésorière',
            'chef', 'patron', 'patronne',
            // German titles
            'geschäftsführer', 'geschäftsführerin', 'leiter', 'leiterin', 'inhaber', 'inhaberin',
            'vorsitzender', 'vorsitzende',
        ];

        $titlesRegex = implode('|', array_map(fn($t) => preg_quote($t, '/'), $titlePatterns));

        // Match: Title [separator] Firstname Lastname
        // Name pattern: uppercase letter followed by lowercase, e.g., "Jean-Pierre Dupont"
        $namePattern = '([A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+(?:[\-\s][A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+){1,3})';

        if (preg_match_all('/(?:' . $titlesRegex . ')\s*[:：\-–—,]?\s*' . $namePattern . '/iu', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        // Pattern 2: "Contact: Name" or "Contact : Name"
        if (preg_match_all('/contact\s*[:：]\s*' . $namePattern . '/iu', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        // Pattern 3: Name followed by email (e.g., "Marie Dupont marie@example.com")
        if (preg_match_all('/' . $namePattern . '\s+[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        return $persons;
    }

    /**
     * Remove duplicate social links (keep first found per platform).
     */
    private function deduplicateSocialLinks(array $links): array
    {
        // Already keyed by platform, so just return as-is
        return $links;
    }
}
