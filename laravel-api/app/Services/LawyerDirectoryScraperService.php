<?php

namespace App\Services;

use App\Models\Lawyer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LawyerDirectoryScraperService
{
    private const MIN_DELAY = 1.5;
    private const MAX_DELAY = 3;
    private const TIMEOUT = 20;
    private const MAX_REQUESTS_PER_DOMAIN = 20; // per minute

    private float $lastRequestTime = 0;
    private array $domainRequestCounts = [];
    private int $requestCounter = 0;

    // Rotating user agents to avoid detection
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 OPR/114.0.0.0',
    ];

    // UK regions to skip (not real countries)
    private const UK_REGION_SLUGS = [
        'east-anglia','east-midlands','london','north','north-west','northern-ireland',
        'scotland','south-east','south-west','wales','west-midlands','yorkshire',
        'guernsey','isle-of-man','jersey','london-bar','regional-bar',
        'middle-east-the-english-bar','regional-international-arbitration-the-bar','scottish-bar',
    ];

    // Team/people page paths to find individual lawyers on firm websites
    private const TEAM_PATHS = [
        '/team', '/our-team', '/people', '/lawyers', '/attorneys', '/professionals',
        '/about/team', '/about/people', '/about-us/team', '/about-us/our-people',
        '/en/team', '/en/people', '/en/lawyers', '/our-people', '/staff',
        '/equipo', '/abogados', '/nosotros', '/rechtsanwaelte', '/anwaelte',
        '/advogados', '/equipe', '/chi-siamo', '/avvocati',
    ];

    public const COUNTRY_CODES = [
        'afghanistan'=>'AF','albania'=>'AL','algeria'=>'DZ','angola'=>'AO','argentina'=>'AR',
        'armenia'=>'AM','australia'=>'AU','austria'=>'AT','azerbaijan'=>'AZ','bahamas'=>'BS',
        'bahrain'=>'BH','bangladesh'=>'BD','barbados'=>'BB','belgium'=>'BE','benin'=>'BJ',
        'bermuda'=>'BM','bolivia'=>'BO','bosnia-and-herzegovina'=>'BA','botswana'=>'BW',
        'brazil'=>'BR','brunei'=>'BN','bulgaria'=>'BG','burkina-faso'=>'BF','burundi'=>'BI',
        'cambodia'=>'KH','cameroon'=>'CM','canada'=>'CA','cape-verde'=>'CV','cayman-islands'=>'KY',
        'chad'=>'TD','chile'=>'CL','china'=>'CN','colombia'=>'CO','congo'=>'CG',
        'costa-rica'=>'CR','croatia'=>'HR','cuba'=>'CU','cyprus'=>'CY','czech-republic'=>'CZ',
        'democratic-republic-of-the-congo'=>'CD','denmark'=>'DK','dominican-republic'=>'DO',
        'east-anglia'=>'GB','ecuador'=>'EC','egypt'=>'EG','el-salvador'=>'SV',
        'equatorial-guinea'=>'GQ','estonia'=>'EE','ethiopia'=>'ET','finland'=>'FI',
        'france'=>'FR','gabon'=>'GA','gambia'=>'GM','georgia'=>'GE','germany'=>'DE',
        'ghana'=>'GH','gibraltar'=>'GI','greece'=>'GR','greenland'=>'GL','grenada'=>'GD',
        'guatemala'=>'GT','guernsey'=>'GG','guinea'=>'GN','honduras'=>'HN','hong-kong'=>'HK',
        'hungary'=>'HU','iceland'=>'IS','india'=>'IN','indonesia'=>'ID','iran'=>'IR',
        'iraq'=>'IQ','ireland'=>'IE','isle-of-man'=>'IM','israel'=>'IL','italy'=>'IT',
        'ivory-coast'=>'CI','jamaica'=>'JM','japan'=>'JP','jersey'=>'JE','jordan'=>'JO',
        'kazakhstan'=>'KZ','kenya'=>'KE','kosovo'=>'XK','kuwait'=>'KW','kyrgyzstan'=>'KG',
        'laos'=>'LA','latvia'=>'LV','lebanon'=>'LB','lesotho'=>'LS','libya'=>'LY',
        'liechtenstein'=>'LI','lithuania'=>'LT','london'=>'GB','luxembourg'=>'LU',
        'macau'=>'MO','madagascar'=>'MG','malawi'=>'MW','malaysia'=>'MY','maldives'=>'MV',
        'mali'=>'ML','malta'=>'MT','mauritania'=>'MR','mauritius'=>'MU','mexico'=>'MX',
        'moldova'=>'MD','monaco'=>'MC','mongolia'=>'MN','montenegro'=>'ME','morocco'=>'MA',
        'mozambique'=>'MZ','myanmar'=>'MM','namibia'=>'NA','nepal'=>'NP','netherlands'=>'NL',
        'new-zealand'=>'NZ','nicaragua'=>'NI','niger'=>'NE','nigeria'=>'NG',
        'north-macedonia'=>'MK','north'=>'GB','north-west'=>'GB','northern-ireland'=>'GB',
        'norway'=>'NO','oman'=>'OM','pakistan'=>'PK','panama'=>'PA','papua-new-guinea'=>'PG',
        'paraguay'=>'PY','peru'=>'PE','philippines'=>'PH','poland'=>'PL','portugal'=>'PT',
        'puerto-rico'=>'PR','qatar'=>'QA','romania'=>'RO','rwanda'=>'RW',
        'saudi-arabia'=>'SA','scotland'=>'GB','senegal'=>'SN','serbia'=>'RS',
        'seychelles'=>'SC','sierra-leone'=>'SL','singapore'=>'SG','slovakia'=>'SK',
        'slovenia'=>'SI','south-africa'=>'ZA','south-east'=>'GB','south-korea'=>'KR',
        'south-west'=>'GB','spain'=>'ES','sri-lanka'=>'LK','sudan'=>'SD',
        'swaziland'=>'SZ','sweden'=>'SE','switzerland'=>'CH','syria'=>'SY',
        'taiwan'=>'TW','tajikistan'=>'TJ','tanzania'=>'TZ','thailand'=>'TH',
        'tunisia'=>'TN','turkey'=>'TR','turkmenistan'=>'TM','turks-and-caicos-islands'=>'TC',
        'uganda'=>'UG','ukraine'=>'UA','united-arab-emirates'=>'AE','united-kingdom'=>'GB',
        'united-states'=>'US','uruguay'=>'UY','uzbekistan'=>'UZ','venezuela'=>'VE',
        'vietnam'=>'VN','wales'=>'GB','west-midlands'=>'GB','yemen'=>'YE',
        'yorkshire'=>'GB','zambia'=>'ZM','zimbabwe'=>'ZW',
    ];

    /**
     * Smart rate limiting: random delay 3-8s + per-domain throttle.
     */
    public function rateLimitSleep(?string $domain = null): void
    {
        // Random delay between MIN and MAX
        $delay = self::MIN_DELAY + (mt_rand(0, 100) / 100) * (self::MAX_DELAY - self::MIN_DELAY);
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < $delay) {
            usleep((int)(($delay - $elapsed) * 1_000_000));
        }

        // Per-domain throttle: max 20 req/min per domain
        if ($domain) {
            $now = time();
            $minute = intdiv($now, 60);
            $key = $domain . ':' . $minute;
            $this->domainRequestCounts[$key] = ($this->domainRequestCounts[$key] ?? 0) + 1;

            if ($this->domainRequestCounts[$key] > self::MAX_REQUESTS_PER_DOMAIN) {
                Log::info('LawyerScraper: domain throttle, sleeping 60s', ['domain' => $domain]);
                sleep(60);
                $this->domainRequestCounts = []; // reset
            }
        }

        // Every 200 requests, take a short break (15-25s)
        $this->requestCounter++;
        if ($this->requestCounter % 200 === 0) {
            $pause = mt_rand(15, 25);
            Log::info('LawyerScraper: cooldown pause', ['requests' => $this->requestCounter, 'pause' => $pause]);
            sleep($pause);
        }

        $this->lastRequestTime = microtime(true);
    }

    private function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }

    private function getDomain(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? 'unknown';
    }

    public function fetchPage(string $url): ?string
    {
        try {
            $domain = $this->getDomain($url);
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent'      => $this->getRandomUserAgent(),
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language'  => 'en-US,en;q=0.9,de;q=0.8,es;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection'      => 'keep-alive',
                    'Cache-Control'   => 'no-cache',
                    'Referer'         => 'https://www.google.com/',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            // Don't log 404s (expected for many URLs)
            if ($response->status() !== 404) {
                Log::warning('LawyerScraper: HTTP error', ['url' => $url, 'status' => $response->status()]);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('LawyerScraper: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function extractEmails(string $html): array
    {
        $emails = [];

        // mailto: links
        preg_match_all('/href=["\']mailto:([^"\'?]+)/i', $html, $m);
        foreach ($m[1] as $email) {
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $emails[] = $email;
        }

        // Plain text emails
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $html, $m);
        foreach ($m[0] as $email) {
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !str_ends_with($email, '.png') && !str_ends_with($email, '.jpg')) {
                $emails[] = $email;
            }
        }

        // CloudFlare email protection decode
        preg_match_all('/data-cfemail="([a-f0-9]+)"/i', $html, $m);
        foreach ($m[1] as $encoded) {
            $decoded = $this->decodeCfEmail($encoded);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($decoded);
            }
        }

        $emails = array_unique($emails);
        $emails = array_filter($emails, fn($e) =>
            !str_contains($e, 'noreply') && !str_contains($e, 'no-reply') &&
            !str_contains($e, 'example.com') && !str_contains($e, 'sentry.io') &&
            !str_contains($e, 'wixpress.com') && !str_contains($e, 'googletagmanager') &&
            !str_contains($e, 'jquery') && !str_contains($e, '.js') &&
            !str_contains($e, 'schema.org') && !str_contains($e, 'w3.org') &&
            !str_contains($e, 'cloudflare') && !str_contains($e, 'cookie')
        );

        return array_values($emails);
    }

    /**
     * Check if a URL is a valid firm website (not a social media, CDN, etc.)
     */
    private function isValidFirmWebsite(?string $url): bool
    {
        if (!$url || strlen($url) < 10) return false;
        $skipDomains = [
            'legal500.com', 'google.com', 'facebook.com', 'linkedin.com', 'twitter.com',
            'youtube.com', 'instagram.com', 'googletagmanager.com', 'gstatic.com',
            'cloudflare.com', 'amazonaws.com', 'wp.com', 'wordpress.com',
        ];
        $domain = $this->getDomain($url);
        foreach ($skipDomains as $skip) {
            if (str_contains($domain, $skip)) return false;
        }
        return true;
    }

    private function decodeCfEmail(string $encoded): ?string
    {
        try {
            $key = hexdec(substr($encoded, 0, 2));
            $email = '';
            for ($i = 2; $i < strlen($encoded); $i += 2) {
                $email .= chr(hexdec(substr($encoded, $i, 2)) ^ $key);
            }
            return $email;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getCountryCode(string $countrySlug): ?string
    {
        return self::COUNTRY_CODES[strtolower($countrySlug)] ?? null;
    }

    // ═══════════════════════════════════════════════════
    //  LEGAL500 — 200+ countries
    // ═══════════════════════════════════════════════════

    public function legal500DiscoverCountries(): array
    {
        $html = $this->fetchPage('https://www.legal500.com/rankings');
        if (!$html) return [];

        $countries = [];
        preg_match_all('#href="/c/([a-z][a-z0-9\-]+)"[^>]*>([^<]+)<#i', $html, $matches, PREG_SET_ORDER);

        $seen = [];
        foreach ($matches as $m) {
            $slug = $m[1];
            $name = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');

            // Skip worldwide, bar entries, and UK regions
            if (str_starts_with($slug, 'worldwide')) continue;
            if (in_array($slug, self::UK_REGION_SLUGS)) continue;
            if (isset($seen[$slug])) continue;
            $seen[$slug] = true;

            $countryCode = $this->getCountryCode($slug);
            if ($countryCode && Lawyer::isFrancophone($countryCode)) continue;

            $countries[] = [
                'slug' => $slug, 'name' => $name, 'country_code' => $countryCode,
                'url' => "https://www.legal500.com/c/{$slug}/",
            ];
        }

        Log::info('Legal500: discovered countries', ['count' => count($countries)]);
        return $countries;
    }

    public function legal500ScrapeFirms(string $countrySlug, string $countryName, ?string $countryCode): array
    {
        $url = "https://www.legal500.com/c/{$countrySlug}/";
        $html = $this->fetchPage($url);
        if (!$html) return [];

        $firms = [];
        // Legal500 uses complex HTML — just extract firm IDs and slugs from href
        preg_match_all('#href="/firms/(\d+)-([^"]+)/c-' . preg_quote($countrySlug, '#') . '"#i', $html, $matches, PREG_SET_ORDER);

        $seen = [];
        foreach ($matches as $m) {
            $firmId = $m[1]; $firmSlug = $m[2];
            if (isset($seen[$firmId])) continue;
            $seen[$firmId] = true;

            // Derive name from slug (will be overridden if found on contact page)
            $firmName = ucwords(str_replace(['-', ' Llp', ' Llc'], [' ', ' LLP', ' LLC'], $firmSlug));

            $firms[] = [
                'firm_id' => $firmId, 'firm_slug' => $firmSlug, 'firm_name' => $firmName,
                'country' => $countryName, 'country_slug' => $countrySlug, 'country_code' => $countryCode,
                'contact_url' => "https://www.legal500.com/firms/{$firmId}-{$firmSlug}/c-{$countrySlug}/contact",
                'lawyers_url' => "https://www.legal500.com/firms/{$firmId}-{$firmSlug}/c-{$countrySlug}/lawyers",
            ];
        }

        return $firms;
    }

    /**
     * Domains to skip when extracting firm website from Legal500
     */
    private const SKIP_WEBSITE_DOMAINS = [
        'legal500.com', 'legalbusiness.co.uk', 'screenloop.com',
        'cookieyes.com', 'typekit.net', 'googletagmanager.com',
        'google.com', 'gstatic.com', 'facebook.com', 'linkedin.com',
        'twitter.com', 'instagram.com', 'xing.com', 'youtube.com',
        'cloudflare.com', 'jsdelivr.net', 'unpkg.com',
    ];

    public function legal500ScrapeFirmContact(array $firm): array
    {
        $lawyers = [];

        // Step 1: Scrape Legal500 contact page for emails + find firm website
        $this->rateLimitSleep('www.legal500.com');
        $contactHtml = $this->fetchPage($firm['contact_url']);
        if (!$contactHtml) return [];

        $emails = $this->extractEmails($contactHtml);
        $phones = [];
        $website = null;

        // Extract phone numbers
        preg_match_all('/(?:\+|00)[1-9]\d{0,3}[\s\-.]?\(?\d{1,4}\)?[\s\-.]?\d{2,4}[\s\-.]?\d{2,4}[\s\-.]?\d{0,4}/', $contactHtml, $phoneMatches);
        foreach ($phoneMatches[0] as $phone) {
            $clean = preg_replace('/[^\d+]/', '', $phone);
            if (strlen($clean) >= 8) $phones[] = $phone;
        }

        // Extract REAL firm website (filter out CDN/social/tracking domains)
        preg_match_all('#href="(https?://[^"]+)"#i', $contactHtml, $allLinks);
        foreach ($allLinks[1] as $link) {
            $linkDomain = $this->getDomain($link);
            $skip = false;
            foreach (self::SKIP_WEBSITE_DOMAINS as $skipDomain) {
                if (str_contains($linkDomain, $skipDomain)) { $skip = true; break; }
            }
            if (!$skip && str_contains($link, '.') && !str_contains($link, '.js') && !str_contains($link, '.css')) {
                $website = $link;
                break;
            }
        }

        // Step 2: Add emails found on Legal500 contact page
        foreach ($emails as $email) {
            $nameParts = $this->guessNameFromEmail($email);
            $lawyers[] = [
                'full_name' => $nameParts['full'] ?? $firm['firm_name'],
                'first_name' => $nameParts['first'], 'last_name' => $nameParts['last'],
                'email' => $email, 'phone' => $phones[0] ?? null,
                'firm_name' => $firm['firm_name'], 'website' => $website,
                'country' => $firm['country'], 'country_code' => $firm['country_code'],
                'source_url' => $firm['contact_url'],
            ];
        }

        // Step 3: Visit actual firm website for MORE emails (the big multiplier)
        if ($website && $this->isValidFirmWebsite($website)) {
            $domain = $this->getDomain($website);
            $base = rtrim($website, '/');

            // Homepage
            $this->rateLimitSleep($domain);
            $siteHtml = $this->fetchPage($website);
            if ($siteHtml) {
                $siteEmails = $this->extractEmails($siteHtml);
                foreach ($siteEmails as $email) {
                    if (!in_array($email, array_column($lawyers, 'email'))) {
                        $nameParts = $this->guessNameFromEmail($email);
                        $lawyers[] = [
                            'full_name' => $nameParts['full'] ?? $firm['firm_name'],
                            'first_name' => $nameParts['first'], 'last_name' => $nameParts['last'],
                            'email' => $email, 'phone' => null,
                            'firm_name' => $firm['firm_name'], 'website' => $website,
                            'country' => $firm['country'], 'country_code' => $firm['country_code'],
                            'source_url' => $website,
                        ];
                    }
                }
            }

            // Team/people pages (where individual lawyer emails live)
            foreach (self::TEAM_PATHS as $path) {
                $this->rateLimitSleep($domain);
                $teamHtml = $this->fetchPage($base . $path);
                if ($teamHtml && strlen($teamHtml) > 500) {
                    $teamEmails = $this->extractEmails($teamHtml);
                    $existingEmails = array_column($lawyers, 'email');
                    $newCount = 0;

                    foreach ($teamEmails as $email) {
                        if (in_array($email, $existingEmails)) continue;
                        $nameParts = $this->guessNameFromEmail($email);
                        $lawyers[] = [
                            'full_name' => $nameParts['full'] ?? $firm['firm_name'],
                            'first_name' => $nameParts['first'], 'last_name' => $nameParts['last'],
                            'email' => $email, 'phone' => null,
                            'firm_name' => $firm['firm_name'], 'website' => $website,
                            'country' => $firm['country'], 'country_code' => $firm['country_code'],
                            'source_url' => $base . $path,
                        ];
                        $newCount++;
                    }

                    if ($newCount > 0) break; // Found team page with emails, stop looking
                }
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  LAWYER.COM — US
    // ═══════════════════════════════════════════════════

    public function lawyerComScrapeByState(string $state): array
    {
        $url = "https://www.lawyer.com/{$state}-lawyer.htm";
        $html = $this->fetchPage($url);
        if (!$html) return [];

        $lawyers = [];
        preg_match_all('#href="(/[a-z\-]+-\d+\.html)"#i', $html, $matches);

        foreach (array_unique($matches[1]) as $profilePath) {
            $this->rateLimitSleep();
            $profileUrl = "https://www.lawyer.com{$profilePath}";
            $profileHtml = $this->fetchPage($profileUrl);
            if (!$profileHtml) continue;

            $lawyer = $this->parseLawyerComProfile($profileHtml, $profileUrl);
            if ($lawyer && !empty($lawyer['email'])) $lawyers[] = $lawyer;
        }

        return $lawyers;
    }

    private function parseLawyerComProfile(string $html, string $url): ?array
    {
        $name = null;
        if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $html, $m)) {
            $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if (!$name) return null;

        $emails = $this->extractEmails($html);
        if (empty($emails)) return null;

        $phone = null;
        if (preg_match('/(?:tel:|phone)[^"]*"?([+\d\s\-().]{7,20})/i', $html, $m)) $phone = trim($m[1]);

        $city = $state = null;
        if (preg_match('/(\w[\w\s]+),\s*([A-Z]{2})\s+\d{5}/', $html, $m)) {
            $city = trim($m[1]); $state = trim($m[2]);
        }

        $specialty = null;
        if (preg_match('#(?:Practice Area|Specialty)[^<]*<[^>]*>([^<]+)#i', $html, $m)) {
            $specialty = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $bar = null;
        if (preg_match('/Bar Admission[^<]*<[^>]*>([^<]+)/i', $html, $m)) {
            $bar = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $nameParts = $this->parseName($name);
        return [
            'full_name' => $name, 'first_name' => $nameParts['first'], 'last_name' => $nameParts['last'],
            'email' => $emails[0], 'phone' => $phone, 'country' => 'United States',
            'country_code' => 'US', 'city' => $city, 'region' => $state,
            'specialty' => $specialty, 'bar_association' => $bar, 'language' => 'en',
            'source_url' => $url,
        ];
    }

    // ═══════════════════════════════════════════════════
    //  ABOGADOS.COM.AR — Argentina
    // ═══════════════════════════════════════════════════

    public function abogadosArScrape(): array
    {
        $html = $this->fetchPage('https://abogados.com.ar/directorio');
        if (!$html) return [];

        $lawyers = [];
        preg_match_all('#href="(https?://abogados\.com\.ar/directorio/[^"]+)"#i', $html, $matches);

        foreach (array_unique($matches[1]) as $firmUrl) {
            $this->rateLimitSleep();
            $firmHtml = $this->fetchPage($firmUrl);
            if (!$firmHtml) continue;

            $firmName = null;
            if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $firmHtml, $m)) {
                $firmName = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            }

            $emails = $this->extractEmails($firmHtml);
            if (empty($emails)) continue;

            foreach ($emails as $email) {
                $lawyers[] = [
                    'full_name' => $firmName ?? 'Unknown', 'email' => $email,
                    'firm_name' => $firmName, 'country' => 'Argentina', 'country_code' => 'AR',
                    'city' => 'Buenos Aires', 'language' => 'es', 'source_url' => $firmUrl,
                ];
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  RECHTSANWALT.COM — DE/AT/CH
    // ═══════════════════════════════════════════════════

    public function rechtsanwaltComScrape(): array
    {
        $lawyers = [];
        $cities = [
            'berlin','hamburg','muenchen','koeln','frankfurt','stuttgart','duesseldorf',
            'dortmund','essen','leipzig','bremen','dresden','hannover','nuernberg',
            'wien','graz','linz','zuerich','bern','basel',
        ];

        foreach ($cities as $city) {
            $this->rateLimitSleep();
            $url = "https://www.rechtsanwalt.com/anwalt/{$city}/";
            $html = $this->fetchPage($url);
            if (!$html) continue;

            preg_match_all('#href="(https?://www\.rechtsanwalt\.com/anwalt/[^"]+/[^"]+)"#i', $html, $matches);
            foreach (array_unique($matches[1]) as $profileUrl) {
                if ($profileUrl === $url) continue;
                $this->rateLimitSleep();
                $profileHtml = $this->fetchPage($profileUrl);
                if (!$profileHtml) continue;

                $emails = $this->extractEmails($profileHtml);
                if (empty($emails)) continue;

                $name = null;
                if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $profileHtml, $m)) {
                    $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                }

                $phone = null;
                if (preg_match('/(?:Tel|Telefon)[.:]*\s*([+\d\s\-\/()]{7,25})/i', $profileHtml, $m)) $phone = trim($m[1]);

                $specialty = null;
                if (preg_match('#Rechtsgebiet[^<]*<[^>]*>([^<]+)#i', $profileHtml, $m)) {
                    $specialty = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                }

                $countryCode = 'DE';
                if (in_array($city, ['wien','graz','linz'])) $countryCode = 'AT';
                if (in_array($city, ['zuerich','bern','basel'])) $countryCode = 'CH';

                $nameParts = $this->parseName($name ?? $emails[0]);
                $lawyers[] = [
                    'full_name' => $name ?? $emails[0], 'first_name' => $nameParts['first'],
                    'last_name' => $nameParts['last'], 'email' => $emails[0], 'phone' => $phone,
                    'country' => $countryCode === 'DE' ? 'Germany' : ($countryCode === 'AT' ? 'Austria' : 'Switzerland'),
                    'country_code' => $countryCode, 'city' => ucfirst($city), 'language' => 'de',
                    'specialty' => $specialty, 'source_url' => $profileUrl,
                ];
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  ENRICHMENT — visit firm websites (MASSIVE)
    // ═══════════════════════════════════════════════════

    public function enrichFromWebsite(string $website): array
    {
        if (!$this->isValidFirmWebsite($website)) return [];

        $emails = [];
        $domain = $this->getDomain($website);
        $base = rtrim($website, '/');

        // 1. Homepage
        $html = $this->fetchPage($website);
        if ($html) $emails = array_merge($emails, $this->extractEmails($html));

        // 2. Try TEAM/PEOPLE pages (where individual lawyers are listed)
        foreach (self::TEAM_PATHS as $path) {
            $this->rateLimitSleep($domain);
            $teamHtml = $this->fetchPage($base . $path);
            if ($teamHtml && strlen($teamHtml) > 1000) {
                $teamEmails = $this->extractEmails($teamHtml);
                $emails = array_merge($emails, $teamEmails);

                // If we found a team page, also look for individual profile links
                $profileLinks = $this->extractProfileLinks($teamHtml, $base);
                foreach (array_slice($profileLinks, 0, 50) as $profileUrl) { // Max 50 profiles per firm
                    $this->rateLimitSleep($domain);
                    $profileHtml = $this->fetchPage($profileUrl);
                    if ($profileHtml) {
                        $emails = array_merge($emails, $this->extractEmails($profileHtml));
                    }
                }

                if (count($emails) > 3) break; // Got enough from team page
            }
        }

        // 3. Contact page as fallback
        if (count($emails) < 2) {
            foreach (['/contact', '/contact-us', '/about/contact', '/en/contact'] as $path) {
                $this->rateLimitSleep($domain);
                $cHtml = $this->fetchPage($base . $path);
                if ($cHtml) {
                    $emails = array_merge($emails, $this->extractEmails($cHtml));
                    if (!empty($emails)) break;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Extract individual lawyer profile links from a team/people page.
     */
    private function extractProfileLinks(string $html, string $baseUrl): array
    {
        $links = [];
        $domain = $this->getDomain($baseUrl);

        // Match internal links that look like lawyer profiles
        preg_match_all('#href="(/[^"]*(?:team|people|lawyer|attorney|partner|associate|professional|counsel|member|staff|bio|profile|avvocati|abogado|rechtsanwalt|advokat)/[^"]+)"#i', $html, $m);
        foreach ($m[1] as $path) {
            $links[] = rtrim($baseUrl, '/') . $path;
        }

        // Also match full URLs on same domain
        preg_match_all('#href="(https?://[^"]*' . preg_quote($domain, '#') . '[^"]*(?:team|people|lawyer|attorney|partner|professional)/[^"]+)"#i', $html, $m);
        foreach ($m[1] as $url) {
            $links[] = $url;
        }

        return array_unique($links);
    }

    /**
     * MASSIVE ENRICHMENT: Visit ALL existing lawyers' firm websites
     * to extract individual lawyer emails from /team /people pages.
     * This multiplies the email count by 10-20x.
     */
    public function enrichExistingLawyers(): int
    {
        $totalNew = 0;

        // Get all unique firm websites from lawyers table
        $websites = Lawyer::whereNotNull('website')
            ->where('website', '!=', '')
            ->where('detail_scraped', false)
            ->groupBy('website')
            ->pluck('website');

        Log::info('LawyerScraper: enriching firm websites', ['count' => $websites->count()]);

        foreach ($websites as $website) {
            if (!$this->isValidFirmWebsite($website)) continue;

            $this->rateLimitSleep($this->getDomain($website));

            try {
                $emails = $this->enrichFromWebsite($website);

                // Get the original lawyer record to inherit country/language
                $original = Lawyer::where('website', $website)->first();
                if (!$original) continue;

                foreach ($emails as $email) {
                    // Skip if already exists
                    if (Lawyer::where('email', strtolower($email))->exists()) continue;

                    // Try to guess name from email (firstname.lastname@...)
                    $nameParts = $this->guessNameFromEmail($email);

                    $saved = $this->saveLawyer([
                        'full_name'    => $nameParts['full'] ?? $original->firm_name,
                        'first_name'   => $nameParts['first'],
                        'last_name'    => $nameParts['last'],
                        'email'        => $email,
                        'phone'        => null,
                        'firm_name'    => $original->firm_name,
                        'website'      => $website,
                        'country'      => $original->country,
                        'country_code' => $original->country_code,
                        'city'         => $original->city,
                        'language'     => $original->language,
                        'specialty'    => $original->specialty,
                        'source_url'   => $website,
                    ], 'website-enrichment');

                    if ($saved) $totalNew++;
                }

                // Mark all lawyers from this website as enriched
                Lawyer::where('website', $website)->update(['detail_scraped' => true]);

            } catch (\Throwable $e) {
                Log::warning('LawyerScraper: enrich failed', ['website' => $website, 'error' => $e->getMessage()]);
            }

            if ($totalNew > 0 && $totalNew % 50 === 0) {
                Log::info('LawyerScraper: enrichment progress', ['new_lawyers' => $totalNew]);
                gc_collect_cycles();
            }
        }

        return $totalNew;
    }

    /**
     * Try to extract a name from an email like john.smith@firm.com
     */
    private function guessNameFromEmail(string $email): array
    {
        $local = explode('@', $email)[0] ?? '';
        $parts = preg_split('/[._\-]/', $local);
        $parts = array_filter($parts, fn($p) => strlen($p) > 1 && !is_numeric($p));

        if (count($parts) >= 2) {
            $first = ucfirst($parts[0]);
            $last = ucfirst(end($parts));
            return ['first' => $first, 'last' => $last, 'full' => "$first $last"];
        }

        return ['first' => null, 'last' => null, 'full' => null];
    }

    // ═══════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════

    public function parseName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        if (count($parts) <= 1) return ['first' => $fullName, 'last' => null];
        $last = array_pop($parts);
        return ['first' => implode(' ', $parts), 'last' => $last];
    }

    public function saveLawyer(array $data, string $sourceSlug): bool
    {
        if (empty($data['email'])) return false;

        $countryCode = $data['country_code'] ?? null;
        if ($countryCode && Lawyer::isFrancophone($countryCode, $data['language'] ?? null, $data['city'] ?? null)) {
            return false;
        }

        $urlHash = hash('sha256', $data['source_url'] . '|' . $data['email']);

        try {
            Lawyer::updateOrCreate(
                ['url_hash' => $urlHash],
                [
                    'source_slug' => $sourceSlug, 'source_url' => $data['source_url'],
                    'full_name' => $data['full_name'],
                    'first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null,
                    'firm_name' => $data['firm_name'] ?? null, 'title' => $data['title'] ?? null,
                    'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null,
                    'website' => $data['website'] ?? null, 'country' => $data['country'] ?? null,
                    'country_code' => $countryCode, 'city' => $data['city'] ?? null,
                    'region' => $data['region'] ?? null, 'address' => $data['address'] ?? null,
                    'specialty' => $data['specialty'] ?? null,
                    'bar_association' => $data['bar_association'] ?? null,
                    'bar_number' => $data['bar_number'] ?? null,
                    'language' => $data['language'] ?? 'en',
                    'is_immigration_lawyer' => Lawyer::isImmigrationSpecialty($data['specialty'] ?? null),
                    'is_francophone' => false, 'scraped_at' => now(),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('LawyerScraper: save failed', ['email' => $data['email'], 'error' => $e->getMessage()]);
            return false;
        }
    }
}
