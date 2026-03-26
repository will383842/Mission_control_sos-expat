<?php

namespace App\Services;

use App\Models\ContentArticle;
use App\Models\ContentCountry;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentScraperService
{
    private const RATE_LIMIT_SECONDS = 2;
    private const MAX_PAGE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)';

    // Affiliate URL parameters to strip
    private const AFFILIATE_PARAMS = [
        // Generic affiliate/tracking
        'ref', 'tag', 'aff', 'partner', 'click_id', 'subid', 'subid1', 'subid2', 'subid3',
        'affiliate_id', 'affid', 'aff_id', 'aff_sub', 'aff_sub2', 'tracking_id',
        'offer_id', 'transaction_id', 'clickref', 'clickRef',
        // UTM (all)
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        // Google Ads
        'gclid', 'gbraid', 'wbraid', 'dclid',
        // Social ads
        'fbclid', 'msclkid', 'twclid', 'ttclid', 'li_fat_id',
        // Affiliate networks
        'awc', 'cjevent', 'cjdata', 'irclickid', 'zanpid', 'tduid',
        // Booking/travel
        'aid', 'sid', 'label',
        // Amazon
        'ascsubtag', 'linkCode', 'linkId',
        // Email marketing
        '_hsenc', '_hsmi', 'mc_cid', 'mc_eid',
        // Analytics cross-domain
        '_ga', '_gl',
    ];

    // Known URL shortener domains (always mark as affiliate)
    private const SHORTENER_DOMAINS = [
        'bit.ly', 'tinyurl.com', 't.co', 'ow.ly', 'goo.gl', 'is.gd',
        'cutt.ly', 'short.io', 'rebrand.ly', 'amzn.to', 'amzn.eu',
    ];

    private float $lastRequestTime = 0;

    /**
     * Scrape the full country list for a source (expat.com).
     * Scrapes the main page AND all 8 continent sub-pages to get all ~219 countries.
     */
    public function scrapeCountryList(ContentSource $source): array
    {
        $countries = [];

        // Step 1: Scrape main guide page to discover continent URLs + some countries
        $html = $this->fetchPage($source->base_url);
        if (!$html) {
            Log::error('ContentScraper: failed to fetch country list', ['url' => $source->base_url]);
            return [];
        }

        $continentUrls = [];
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // Extract country links from main page (static + JSON)
        $this->extractCountryLinksFromPage($xpath, $source->base_url, $countries, $html);

        // Discover continent page URLs (e.g. /fr/guide/europe/, /fr/guide/afrique/)
        $allLinks = $xpath->query('//a[contains(@href, "/fr/guide/")]');
        foreach ($allLinks as $link) {
            $href = $link->getAttribute('href');
            // Match continent URLs: /fr/guide/{continent}/ (exactly one segment after /guide/)
            if (preg_match('#/fr/guide/([a-z-]+)/?$#', $href, $m)) {
                $slug = $m[1];
                // Skip known non-continent slugs
                if (in_array($slug, ['guide', 'villes'])) continue;
                $url = $this->resolveUrl($href, $source->base_url);
                $continentUrls[$slug] = $url;
            }
        }
        unset($xpath, $dom);

        // Known continents for expat.com (fallback if not all discovered)
        $knownContinents = [
            'afrique', 'asie', 'moyen-orient', 'europe',
            'amerique-du-nord', 'amerique-du-sud', 'amerique-centrale', 'oceanie',
        ];
        foreach ($knownContinents as $c) {
            if (!isset($continentUrls[$c])) {
                $continentUrls[$c] = rtrim($source->base_url, '/') . '/' . $c . '/';
            }
        }

        Log::info('ContentScraper: discovered continents', [
            'source'     => $source->slug,
            'continents' => array_keys($continentUrls),
        ]);

        // Step 2: Scrape each continent page for the full country list
        foreach ($continentUrls as $continentSlug => $continentUrl) {
            $this->rateLimitSleep();
            $cHtml = $this->fetchPage($continentUrl);
            if (!$cHtml) continue;

            $cDom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $cDom->loadHTML('<?xml encoding="utf-8" ?>' . $cHtml);
            libxml_clear_errors();
            $cXpath = new \DOMXPath($cDom);

            $this->extractCountryLinksFromPage($cXpath, $continentUrl, $countries, $cHtml);
            unset($cXpath, $cDom);
        }

        // Deduplicate by slug
        $seen = [];
        $unique = [];
        foreach ($countries as $c) {
            if (!isset($seen[$c['slug']])) {
                $seen[$c['slug']] = true;
                $unique[] = $c;
            }
        }

        Log::info('ContentScraper: found countries', [
            'source' => $source->slug,
            'count'  => count($unique),
        ]);

        return $unique;
    }

    /**
     * Extract country links from a parsed HTML page (main guide or continent page).
     * Uses both static <a> links AND embedded JSON (baseLevel.filter) for JS-rendered pages.
     */
    private function extractCountryLinksFromPage(\DOMXPath $xpath, string $baseUrl, array &$countries, ?string $rawHtml = null): void
    {
        // Strategy 1: Static <a> links
        $links = $xpath->query('//a[contains(@href, "/fr/guide/")]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);

            if (preg_match('#/fr/guide/([^/]+)/([^/]+)/?$#', $href, $m)) {
                $continent = $this->normalizeContinent($m[1]);
                $countrySlug = $m[2];
                if ($countrySlug === 'guide') continue;
                // Skip URLs that end in .html (these are articles, not countries)
                if (str_ends_with($countrySlug, '.html')) continue;

                $fullUrl = $this->resolveUrl($href, $baseUrl);
                $countries[] = [
                    'name'      => $text ?: ucfirst(str_replace('-', ' ', $countrySlug)),
                    'slug'      => $countrySlug,
                    'continent' => $continent,
                    'guide_url' => $fullUrl,
                ];
            }
        }

        // Strategy 2: Extract from embedded JSON (expat.com uses baseLevel.filter with country options)
        if ($rawHtml) {
            $this->extractCountriesFromEmbeddedJson($rawHtml, $baseUrl, $countries);
        }
    }

    /**
     * Extract countries from embedded JavaScript JSON data.
     * Expat.com format: {"id":"https:\/\/www.expat.com\/fr\/guide\/afrique\/senegal\/","text":"Sénégal","children":[...]}
     * The JSON uses escaped slashes (\/) and absolute URLs.
     */
    private function extractCountriesFromEmbeddedJson(string $html, string $baseUrl, array &$countries): void
    {
        // First unescape JSON slashes so we can parse clean URLs
        // Look for the filter data block containing country options
        $unescaped = str_replace('\\/', '/', $html);

        // Match: "id":"https://www.expat.com/fr/guide/{continent}/{country}/","text":"{Name}"
        // We must NOT match city URLs (3 segments: continent/country/city)
        $pattern = '/"id"\s*:\s*"https?:\/\/[^"]*\/fr\/guide\/([a-z-]+)\/([a-z-]+)\/"\s*,\s*"text"\s*:\s*"([^"]+)"/';

        if (preg_match_all($pattern, $unescaped, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $continentSlug = $m[1]; // afrique
                $countrySlug = $m[2];   // senegal
                $countryName = $m[3];   // Sénégal

                // Skip if continent and country are the same (malformed)
                if ($countrySlug === $continentSlug) continue;

                $fullUrl = 'https://www.expat.com/fr/guide/' . $continentSlug . '/' . $countrySlug . '/';
                $countries[] = [
                    'name'      => html_entity_decode($countryName, ENT_QUOTES, 'UTF-8'),
                    'slug'      => $countrySlug,
                    'continent' => $this->normalizeContinent($continentSlug),
                    'guide_url' => $fullUrl,
                ];
            }

            Log::info('ContentScraper: extracted countries from JSON', ['count' => count($matches)]);
        }
    }

    /**
     * Scrape all article links from a country's guide page.
     * First tries to extract from embedded JSON (JS-rendered pages), then falls back to DOM.
     */
    public function scrapeCountryArticles(ContentCountry $country): array
    {
        $html = $this->fetchPage($country->guide_url);
        if (!$html) {
            Log::warning('ContentScraper: failed to fetch country page', [
                'country' => $country->slug,
                'url'     => $country->guide_url,
            ]);
            return [];
        }

        $articles = [];

        // Strategy 1: Extract from embedded JSON (expat.com renders articles via JS)
        $articles = $this->extractArticlesFromJson($html, $country);

        // Strategy 2: Fallback to DOM parsing
        if (empty($articles)) {
            $articles = $this->extractArticlesFromDom($html, $country);
        }

        // Strategy 3: Extract from JSON-LD structured data
        if (empty($articles)) {
            $articles = $this->extractArticlesFromJsonLd($html, $country);
        }

        // Deduplicate by URL
        $seen = [];
        $unique = [];
        foreach ($articles as $a) {
            $normalized = rtrim($a['url'], '/');
            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $unique[] = $a;
            }
        }

        Log::info('ContentScraper: found articles for country', [
            'country'  => $country->slug,
            'count'    => count($unique),
            'strategy' => empty($articles) ? 'none' : 'ok',
        ]);

        return $unique;
    }

    /**
     * Scrape a single article page: content, links, images, meta.
     */
    public function scrapeArticle(string $url): ?array
    {
        $html = $this->fetchPage($url);
        if (!$html) {
            Log::warning('ContentScraper: failed to fetch article', ['url' => $url]);
            return null;
        }

        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            // Extract title
            $titleNode = $xpath->query('//h1');
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
            if (!$title) {
                $titleTag = $xpath->query('//title');
                $title = $titleTag->length > 0 ? trim($titleTag->item(0)->textContent) : '';
            }

            // Meta title & description
            $metaTitle = $this->getMetaContent($xpath, 'og:title')
                ?: $this->getMetaContent($xpath, 'title', 'name')
                ?: $title;
            $metaDescription = $this->getMetaContent($xpath, 'og:description')
                ?: $this->getMetaContent($xpath, 'description', 'name');

            // Try to extract content from JSON-LD articleBody first
            $contentText = $this->extractArticleBodyFromJsonLd($html);
            $contentHtml = '';

            // Fallback: DOM content selectors (expat.com specific first)
            if (!$contentText) {
                // Selectors ordered: most specific (expat.com) first, generic last.
                // For expat.com, "article-content--intro" matches "article-content" but only captures intro.
                // We need the PARENT that wraps all article sections.
                $contentSelectors = [
                    '//*[contains(@class, "article-content") and not(contains(@class, "article-content--"))]',
                    '//*[contains(@class, "article__body")]',
                    '//*[contains(@class, "article-text")]',
                    '//article',
                    '//*[contains(@class, "guide-content")]',
                    '//*[contains(@class, "content-body")]',
                    '//*[contains(@class, "entry-content")]',
                    '//main',
                ];

                foreach ($contentSelectors as $selector) {
                    $nodes = $xpath->query($selector);
                    if ($nodes->length > 0) {
                        $contentNode = $nodes->item(0);
                        $contentHtml = $dom->saveHTML($contentNode);
                        $contentText = $this->extractText($contentNode->cloneNode(true));
                        // Only accept if we got substantial content (>100 chars)
                        if (strlen($contentText) > 100) break;
                    }
                }
            }

            // Fallback: use body
            if (!$contentText) {
                $body = $xpath->query('//body');
                if ($body->length > 0) {
                    $contentHtml = $dom->saveHTML($body->item(0));
                    $contentText = $this->extractText($body->item(0)->cloneNode(true));
                }
            }

            // Extract external links (on original DOM, not mutated)
            $externalLinks = $this->extractExternalLinks($dom, $xpath, $url);

            // Extract images
            $images = [];
            $imgNodes = $xpath->query('//img[@src]');
            foreach ($imgNodes as $img) {
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt');
                if ($src && !str_contains($src, 'data:image')) {
                    $images[] = [
                        'url' => $this->resolveUrl($src, $url),
                        'alt' => $alt,
                    ];
                }
            }

            // Detect ads/sponsors
            $ads = $this->detectAdsAndSponsors($xpath);

            $wordCount = count(preg_split('/\s+/', trim(strip_tags($contentText)), -1, PREG_SPLIT_NO_EMPTY));

            // Limit content size to prevent DB bloat
            $contentText = mb_substr($contentText, 0, 100000);
            $contentHtml = mb_substr($contentHtml, 0, 500000);

            unset($xpath, $dom);

            return [
                'title'            => $title,
                'slug'             => Str::slug($title) ?: Str::slug(basename(parse_url($url, PHP_URL_PATH) ?? '')),
                'url'              => $url,
                'content_text'     => $contentText,
                'content_html'     => $contentHtml,
                'word_count'       => $wordCount,
                'language'         => 'fr',
                'external_links'   => $externalLinks,
                'images'           => array_slice($images, 0, 50),
                'ads_and_sponsors' => $ads,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDescription,
            ];
        } catch (\Throwable $e) {
            Log::error('ContentScraper: article parsing failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract all external links from a page, deduplicated.
     */
    public function extractExternalLinks(\DOMDocument $dom, \DOMXPath $xpath, string $pageUrl): array
    {
        $baseDomain = parse_url($pageUrl, PHP_URL_HOST);
        $links = [];

        $anchors = $xpath->query('//a[@href]');
        foreach ($anchors as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if (!$href || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            $fullUrl = $this->resolveUrl($href, $pageUrl);
            $linkDomain = parse_url($fullUrl, PHP_URL_HOST);
            if (!$linkDomain) continue;

            // Check if same-domain redirect (e.g. expat.com/out/?url=...) BEFORE skipping
            if ($linkDomain === $baseDomain || str_ends_with($linkDomain, '.' . $baseDomain)) {
                $redirectTarget = $this->extractRedirectTarget($fullUrl, $baseDomain);
                if ($redirectTarget) {
                    // This is a redirect link — treat the target as external
                    $cleanedUrl = $this->cleanAffiliateUrl($redirectTarget);
                    $targetDomain = parse_url($cleanedUrl, PHP_URL_HOST);
                    if (!$targetDomain || $this->isSkippableLink($targetDomain)) continue;

                    $anchorText = trim($anchor->textContent);
                    $relAttr = strtolower($anchor->getAttribute('rel') ?? '');

                    $links[] = [
                        'url'          => $cleanedUrl,
                        'original_url' => $fullUrl,
                        'domain'       => $targetDomain,
                        'anchor_text'  => mb_substr($anchorText, 0, 500),
                        'context'      => mb_substr($this->extractLinkContext($anchor), 0, 1000),
                        'link_type'    => $this->classifyLinkType($cleanedUrl, $targetDomain, $anchorText),
                        'is_affiliate' => true, // Redirect = always affiliate
                    ];
                }
                continue; // Skip other internal links
            }

            if ($this->isSkippableLink($linkDomain)) continue;

            $anchorText = trim($anchor->textContent);
            $context = $this->extractLinkContext($anchor);

            // Detect rel="sponsored" or rel="nofollow" on the anchor
            $relAttr = strtolower($anchor->getAttribute('rel') ?? '');
            $isSponsoredRel = str_contains($relAttr, 'sponsored') || str_contains($relAttr, 'nofollow');

            $originalUrl = $fullUrl;
            $cleanedUrl = $this->cleanAffiliateUrl($fullUrl);
            $isAffiliate = $cleanedUrl !== $originalUrl
                || $this->detectAffiliateRedirect($fullUrl)
                || $this->isShortenerDomain($linkDomain)
                || $isSponsoredRel;

            $finalUrl = $this->extractRedirectTarget($cleanedUrl, $baseDomain);
            if ($finalUrl) {
                $cleanedUrl = $this->cleanAffiliateUrl($finalUrl);
                $isAffiliate = true;
            }

            $links[] = [
                'url'          => $cleanedUrl,
                'original_url' => $originalUrl,
                'domain'       => parse_url($cleanedUrl, PHP_URL_HOST) ?: $linkDomain,
                'anchor_text'  => mb_substr($anchorText, 0, 500),
                'context'      => mb_substr($context, 0, 1000),
                'link_type'    => $this->classifyLinkType($cleanedUrl, parse_url($cleanedUrl, PHP_URL_HOST) ?: $linkDomain, $anchorText),
                'is_affiliate' => $isAffiliate,
            ];
        }

        // Deduplicate and count occurrences within the same page
        $deduplicated = [];
        foreach ($links as $link) {
            $key = $link['url'];
            if (isset($deduplicated[$key])) {
                $deduplicated[$key]['occurrences'] = ($deduplicated[$key]['occurrences'] ?? 1) + 1;
            } else {
                $link['occurrences'] = 1;
                $deduplicated[$key] = $link;
            }
        }

        return array_values($deduplicated);
    }

    /**
     * Clean affiliate parameters from a URL.
     */
    public function cleanAffiliateUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return $url;
        }
        if (!isset($parsed['query'])) return $url;

        parse_str($parsed['query'], $params);

        $cleaned = [];
        foreach ($params as $key => $value) {
            $lowerKey = strtolower($key);
            $isAffiliate = false;
            foreach (self::AFFILIATE_PARAMS as $ap) {
                if ($lowerKey === strtolower($ap) || str_starts_with($lowerKey, 'utm_')) {
                    $isAffiliate = true;
                    break;
                }
            }
            if (!$isAffiliate) {
                $cleaned[$key] = $value;
            }
        }

        $base = $parsed['scheme'] . '://' . $parsed['host']
            . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
            . ($parsed['path'] ?? '/');

        if (!empty($cleaned)) {
            $base .= '?' . http_build_query($cleaned);
        }
        if (isset($parsed['fragment'])) {
            $base .= '#' . $parsed['fragment'];
        }

        return $base;
    }

    /**
     * Rate-limited sleep between requests.
     */
    public function rateLimitSleep(): void
    {
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int) ((self::RATE_LIMIT_SECONDS - $elapsed) * 1_000_000));
        }
    }

    // ──── Private helpers ──────────────────────────────────────

    private function fetchPage(string $url): ?string
    {
        // SSRF protection: block private/reserved IPs
        if (!$this->isAllowedUrl($url)) {
            Log::warning('ContentScraper: blocked URL (private/reserved)', ['url' => $url]);
            return null;
        }

        // Rate limit enforced here automatically
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int) ((self::RATE_LIMIT_SECONDS - $elapsed) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => true],
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('ContentScraper: HTTP error', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            // Check Content-Type
            $contentType = $response->header('Content-Type') ?? '';
            if ($contentType && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
                Log::info('ContentScraper: skipping non-HTML', ['url' => $url, 'type' => $contentType]);
                return null;
            }

            $body = $response->body();

            // Size limit
            if (strlen($body) > self::MAX_PAGE_SIZE) {
                Log::warning('ContentScraper: page too large', ['url' => $url, 'size' => strlen($body)]);
                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error('ContentScraper: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * SSRF protection: only allow public IPs.
     */
    private function isAllowedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;

        $ip = gethostbyname($host);
        // If DNS resolution fails, gethostbyname returns the hostname
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        $parsed = parse_url($baseUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) $base .= ':' . $parsed['port'];

        if (str_starts_with($href, '/')) {
            return $base . $href;
        }

        // Relative path: resolve against base directory
        $path = $parsed['path'] ?? '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1);
        $resolved = $dir . $href;

        // Normalize ../ and ./
        $parts = explode('/', $resolved);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($normalized);
            } elseif ($part !== '.' && $part !== '') {
                $normalized[] = $part;
            }
        }

        return $base . '/' . implode('/', $normalized);
    }

    private function normalizeContinent(string $slug): string
    {
        return match ($slug) {
            'afrique'            => 'Afrique',
            'amerique-du-nord'   => 'Amerique du Nord',
            'amerique-du-sud'    => 'Amerique du Sud',
            'amerique-centrale'  => 'Amerique Centrale',
            'asie'               => 'Asie',
            'europe'             => 'Europe',
            'moyen-orient'       => 'Moyen-Orient',
            'oceanie'            => 'Oceanie',
            default              => ucfirst(str_replace('-', ' ', $slug)),
        };
    }

    /**
     * Detect category using scoring (best match wins) with accent normalization.
     */
    private function detectCategory(string $url, string $text): ?string
    {
        // Normalize accents if intl extension available
        $combined = strtolower($url . ' ' . $text);
        if (function_exists('transliterator_transliterate')) {
            $combined = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $combined);
        }

        $categories = [
            'visa'      => ['visa', 'permis', 'residence', 'sejour', 'immigration'],
            'logement'  => ['logement', 'louer', 'immobilier', 'appartement', 'maison', 'hebergement'],
            'sante'     => ['sante', 'assurance', 'hopital', 'medecin', 'vaccination', 'maladie'],
            'emploi'    => ['emploi', 'travail', 'job', 'carriere', 'entreprise', 'business'],
            'transport' => ['transport', 'conduire', 'voiture', 'permis de conduire', 'metro', 'bus'],
            'education' => ['education', 'ecole', 'universite', 'etude', 'scolarite', 'enfant'],
            'banque'    => ['banque', 'finance', 'compte', 'argent', 'impot', 'fiscal'],
            'culture'   => ['culture', 'langue', 'tradition', 'gastronomie', 'loisir'],
            'demarches' => ['demarche', 'administration', 'consulat', 'ambassade', 'papier'],
            'telecom'   => ['telephone', 'internet', 'telecom', 'mobile', 'communication'],
        ];

        $scores = [];
        foreach ($categories as $cat => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($combined, $kw)) $score++;
            }
            if ($score > 0) $scores[$cat] = $score;
        }

        if (empty($scores)) return null;
        arsort($scores);
        return array_key_first($scores);
    }

    private function getMetaContent(\DOMXPath $xpath, string $name, string $attr = 'property'): ?string
    {
        $node = $xpath->query("//meta[@{$attr}='{$name}']")->item(0);
        return $node ? $node->getAttribute('content') : null;
    }

    /**
     * Extract clean text from a DOM node (WORKS ON A CLONE to avoid mutating original DOM).
     */
    private function extractText(\DOMNode $node): string
    {
        $tagsToRemove = ['script', 'style', 'nav', 'footer', 'header', 'aside', 'form', 'noscript', 'svg'];
        $remove = [];
        foreach ($tagsToRemove as $tag) {
            foreach ($node->getElementsByTagName($tag) as $el) {
                $remove[] = $el;
            }
        }
        foreach ($remove as $el) {
            if ($el->parentNode) $el->parentNode->removeChild($el);
        }

        $text = $node->textContent;
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function extractLinkContext(\DOMNode $anchor): string
    {
        $parent = $anchor->parentNode;
        if ($parent) {
            $text = trim($parent->textContent);
            if (strlen($text) > 20 && strlen($text) < 1000) {
                return $text;
            }
        }
        return '';
    }

    private function isSkippableLink(string $domain): bool
    {
        $skip = [
            'facebook.com', 'twitter.com', 'x.com', 'instagram.com',
            'linkedin.com', 'youtube.com', 'pinterest.com', 'tiktok.com',
            'reddit.com', 'threads.net', 'whatsapp.com', 'snapchat.com',
            'telegram.org', 't.me', 'vimeo.com', 'dailymotion.com',
            'tumblr.com', 'flickr.com', 'gravatar.com',
            'apps.apple.com', 'itunes.apple.com', 'play.google.com',
            'maps.google.com', 'fonts.googleapis.com',
            'w3.org', 'schema.org', 'creativecommons.org',
            'cdn.ampproject.org', 'wp.com',
        ];
        foreach ($skip as $s) {
            if ($domain === $s || str_ends_with($domain, '.' . $s)) return true;
        }
        return false;
    }

    private function isShortenerDomain(string $domain): bool
    {
        foreach (self::SHORTENER_DOMAINS as $s) {
            if ($domain === $s || str_ends_with($domain, '.' . $s)) return true;
        }
        return false;
    }

    private function detectAffiliateRedirect(string $url): bool
    {
        return (bool) preg_match('/\/(out|redirect|go|click|track|aff|partner|link)\/?(\?|$)/i', $url);
    }

    private function extractRedirectTarget(string $url, string $sourceDomain): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        if ($host === $sourceDomain || str_ends_with($host, '.' . $sourceDomain)) {
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
                foreach (['url', 'redirect', 'target', 'goto', 'dest', 'destination', 'link'] as $key) {
                    if (!empty($params[$key])) {
                        $decoded = urldecode($params[$key]);
                        if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                            return $decoded;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function classifyLinkType(string $url, string $domain, string $anchorText): string
    {
        // Official government / institution
        if (preg_match('/\.(gov|gouv|gob|edu|ac|org)\.[a-z]{2}$/i', $domain)
            || str_ends_with($domain, '.gov')
            || str_ends_with($domain, '.edu')
            || str_ends_with($domain, '.int')) {
            return 'official';
        }

        // Known official domains & embassy/consulate patterns
        $officialDomains = [
            'service-public.fr', 'ameli.fr', 'caf.fr', 'pole-emploi.fr', 'ants.gouv.fr',
        ];
        foreach ($officialDomains as $od) {
            if ($domain === $od || str_ends_with($domain, '.' . $od)) return 'official';
        }
        if (str_contains($domain, 'embassy') || str_contains($domain, 'ambas')
            || str_contains($domain, 'consulat') || str_contains($domain, 'consulate')) {
            return 'official';
        }

        // News / media
        $newsDomains = [
            'reuters.com', 'bbc.com', 'lemonde.fr', 'lefigaro.fr', 'theguardian.com',
            'france24.com', 'rfi.fr', 'liberation.fr', 'leparisien.fr', 'euronews.com',
            'dw.com', 'elpais.com', '20minutes.fr',
        ];
        foreach ($newsDomains as $nd) {
            if ($domain === $nd || str_ends_with($domain, '.' . $nd)) return 'news';
        }

        // Resource / reference (check before service to avoid wikipedia being classified as service)
        if (str_ends_with($domain, '.org') || str_ends_with($domain, '.wiki')) {
            return 'resource';
        }

        // Service / commercial
        if (preg_match('/\.(com|io|co|app|net)$/i', $domain)) {
            return 'service';
        }

        return 'other';
    }

    private function detectAdsAndSponsors(\DOMXPath $xpath): array
    {
        $ads = [];
        $adSelectors = [
            '//*[contains(@class, "ad-")]',
            '//*[contains(@class, "sponsor")]',
            '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "pub-")]',
            '//*[contains(@class, "banner-partner")]',
            '//*[@rel="sponsored"]',
            '//*[contains(@id, "ad-")]',
            '//ins[@class="adsbygoogle"]',
            '//iframe[contains(@src, "doubleclick")]',
            '//iframe[contains(@src, "googlesyndication")]',
        ];

        foreach ($adSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $ads[] = [
                    'type'  => 'ad_container',
                    'class' => $node->getAttribute('class') ?? '',
                    'id'    => $node->getAttribute('id') ?? '',
                ];
            }
        }

        return array_slice($ads, 0, 20);
    }

    // ──── JSON extraction strategies for JS-rendered pages ──────

    /**
     * Extract articles from embedded JavaScript data (expat.com renders articles via JS).
     */
    private function extractArticlesFromJson(string $html, ContentCountry $country): array
    {
        $articles = [];

        // Look for JSON data in script tags (common patterns: __INITIAL_STATE__, __NEXT_DATA__, inline JSON arrays)
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $script) {
                // Look for article URLs in the script content
                $countryPattern = preg_quote($country->slug, '/');
                if (preg_match_all('/\/fr\/guide\/[^"\']+\/' . $countryPattern . '\/(\d+-[^"\']+\.html)/', $script, $urlMatches)) {
                    foreach ($urlMatches[0] as $path) {
                        $fullUrl = $this->resolveUrl($path, $country->guide_url);

                        // Try to extract title from nearby JSON context
                        $title = '';
                        $titlePattern = '/"(?:title|name|label)"\s*:\s*"([^"]+)".*?' . preg_quote(basename($path), '/') . '/si';
                        if (preg_match($titlePattern, $script, $tm)) {
                            $title = $tm[1];
                        }

                        $articles[] = [
                            'url'      => $fullUrl,
                            'title'    => $title,
                            'category' => $this->detectCategory($path, $title),
                            'is_guide' => false,
                        ];
                    }
                }
            }
        }

        return $articles;
    }

    /**
     * Fallback: extract article links from DOM.
     */
    private function extractArticlesFromDom(string $html, ContentCountry $country): array
    {
        $articles = [];
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $countryPattern = preg_quote($country->slug, '#');
        $links = $xpath->query('//a[contains(@href, ".html")]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);

            if (preg_match('#/fr/guide/[^/]+/' . $countryPattern . '/.*\.html#', $href)) {
                $fullUrl = $this->resolveUrl($href, $country->guide_url);
                $articles[] = [
                    'url'      => $fullUrl,
                    'title'    => $text,
                    'category' => $this->detectCategory($href, $text),
                    'is_guide' => false,
                ];
            }
        }

        unset($xpath, $dom);
        return $articles;
    }

    /**
     * Extract articles from JSON-LD structured data.
     */
    private function extractArticlesFromJsonLd(string $html, ContentCountry $country): array
    {
        $articles = [];

        if (preg_match_all('/<script\s+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = @json_decode(trim($jsonStr), true);
                if (!$data) continue;

                // Check for itemListElement (common for guide pages)
                $items = $data['itemListElement'] ?? $data['hasPart'] ?? [];
                foreach ($items as $item) {
                    $itemUrl = $item['url'] ?? $item['item']['url'] ?? null;
                    $itemName = $item['name'] ?? $item['item']['name'] ?? '';
                    if ($itemUrl && str_contains($itemUrl, $country->slug)) {
                        $articles[] = [
                            'url'      => $this->resolveUrl($itemUrl, $country->guide_url),
                            'title'    => $itemName,
                            'category' => $this->detectCategory($itemUrl, $itemName),
                            'is_guide' => false,
                        ];
                    }
                }
            }
        }

        return $articles;
    }

    /**
     * Extract articleBody from JSON-LD (more reliable than DOM for JS-rendered pages).
     */
    private function extractArticleBodyFromJsonLd(string $html): ?string
    {
        if (preg_match_all('/<script\s+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = @json_decode(trim($jsonStr), true);
                if (!$data) continue;
                if (!empty($data['articleBody'])) {
                    return $data['articleBody'];
                }
            }
        }
        return null;
    }
}
