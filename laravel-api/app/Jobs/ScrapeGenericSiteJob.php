<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentContact;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic site scraper — works with any site that has XML sitemaps.
 * Discovers articles from sitemaps, scrapes content + external links.
 * Also extracts contact emails from contact/about pages.
 */
class ScrapeGenericSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-generic-' . $this->sourceId))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $source->update(['status' => 'scraping']);
        $baseUrl = rtrim($source->base_url, '/');

        Log::info('ScrapeGenericSiteJob: starting', ['source' => $source->slug, 'url' => $baseUrl]);

        try {
            // Step 1: Try to extract contacts from common contact pages
            $this->scrapeContacts($source, $baseUrl);

            // Step 2: Discover all article URLs from sitemaps
            $articleUrls = $this->discoverFromSitemaps($baseUrl);

            if (empty($articleUrls)) {
                // Fallback: try to crawl from the homepage
                $articleUrls = $this->discoverFromHomepage($baseUrl, $scraper);
            }

            Log::info('ScrapeGenericSiteJob: discovered URLs', [
                'source' => $source->slug,
                'total'  => count($articleUrls),
            ]);

            // Pre-load existing
            $existingUrls = ContentArticle::where('source_id', $source->id)
                ->pluck('url')->flip()->toArray();
            $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
                ->pluck('url_hash')->flip()->toArray();

            $scrapedCount = 0;
            $skippedCount = 0;
            $consecutiveFailures = 0;

            foreach ($articleUrls as $articleData) {
                if (isset($existingUrls[$articleData['url']])) {
                    $skippedCount++;
                    continue;
                }

                try {
                    $scraper->rateLimitSleep();
                    $content = $scraper->scrapeArticle($articleData['url']);
                    if (!$content || $content['word_count'] < 30) {
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= 20) {
                            Log::warning('ScrapeGenericSiteJob: stopping after 20 failures', ['source' => $source->slug]);
                            break;
                        }
                        continue;
                    }

                    $urlHash = hash('sha256', $articleData['url']);
                    $language = $this->detectLanguage($articleData['url'], $content['language'] ?? 'en');

                    $article = ContentArticle::create([
                        'source_id'        => $source->id,
                        'title'            => $content['title'] ?: $articleData['title'] ?? '',
                        'slug'             => $content['slug'] ?: substr($urlHash, 0, 12),
                        'url'              => $articleData['url'],
                        'url_hash'         => $urlHash,
                        'category'         => $articleData['category'] ?? null,
                        'section'          => $articleData['section'] ?? 'guide',
                        'content_text'     => $content['content_text'],
                        'content_html'     => $content['content_html'],
                        'word_count'       => $content['word_count'],
                        'language'         => $language,
                        'external_links'   => $content['external_links'],
                        'ads_and_sponsors' => $content['ads_and_sponsors'],
                        'images'           => $content['images'],
                        'meta_title'       => $content['meta_title'],
                        'meta_description' => $content['meta_description'],
                        'is_guide'         => $this->isGuideUrl($articleData['url']),
                        'scraped_at'       => now(),
                    ]);

                    $existingUrls[$articleData['url']] = true;

                    foreach ($content['external_links'] as $link) {
                        $linkHash = hash('sha256', $link['url']);
                        if (!isset($existingLinkHashes[$linkHash])) {
                            ContentExternalLink::create([
                                'source_id'    => $source->id,
                                'article_id'   => $article->id,
                                'url'          => $link['url'],
                                'url_hash'     => $linkHash,
                                'original_url' => $link['original_url'],
                                'domain'       => $link['domain'],
                                'anchor_text'  => $link['anchor_text'],
                                'context'      => $link['context'],
                                'link_type'    => $link['link_type'],
                                'is_affiliate' => $link['is_affiliate'],
                                'language'     => $language,
                            ]);
                            $existingLinkHashes[$linkHash] = true;
                        }
                    }

                    $scrapedCount++;
                    $consecutiveFailures = 0;

                    if ($scrapedCount % 50 === 0) {
                        gc_collect_cycles();
                        Log::info('ScrapeGenericSiteJob: progress', [
                            'source'  => $source->slug,
                            'scraped' => $scrapedCount,
                            'skipped' => $skippedCount,
                        ]);
                    }

                } catch (\Throwable $e) {
                    $consecutiveFailures++;
                    if ($consecutiveFailures >= 20) break;
                }
            }

            $source->update([
                'status'          => 'completed',
                'total_articles'  => $source->articles()->count(),
                'total_links'     => $source->externalLinks()->count(),
                'last_scraped_at' => now(),
            ]);

            Log::info('ScrapeGenericSiteJob: completed', [
                'source'  => $source->slug,
                'scraped' => $scrapedCount,
                'skipped' => $skippedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeGenericSiteJob: failed', ['source' => $source->slug, 'error' => $e->getMessage()]);
            $source->update(['status' => 'pending']);
        }
    }

    /**
     * Discover article URLs from XML sitemaps (works with WordPress, Drupal, Yoast, etc.)
     */
    private function discoverFromSitemaps(string $baseUrl): array
    {
        $articles = [];
        $seen = [];

        // Try common sitemap locations
        $sitemapUrls = [
            $baseUrl . '/sitemap_index.xml',
            $baseUrl . '/sitemap.xml',
            $baseUrl . '/sitemaps.xml',
            $baseUrl . '/sitemap-index.xml',
        ];

        $subSitemaps = [];

        foreach ($sitemapUrls as $sitemapUrl) {
            $xml = $this->fetchSitemap($sitemapUrl);
            if (!$xml) continue;

            // Check if this is a sitemap index (contains <sitemap> tags)
            $children = $xml->children();
            $isIndex = false;

            foreach ($children as $child) {
                if ($child->getName() === 'sitemap' || isset($child->loc)) {
                    $loc = (string) ($child->loc ?? '');
                    if ($loc && str_contains($loc, 'sitemap') && str_ends_with($loc, '.xml')) {
                        // It's a sitemap index — collect sub-sitemaps
                        $subSitemaps[] = $loc;
                        $isIndex = true;
                    } elseif ($loc && !str_contains($loc, 'sitemap')) {
                        // It's a regular URL entry
                        $this->addArticleUrl($loc, $baseUrl, $articles, $seen);
                    }
                }
            }

            if ($isIndex || !empty($subSitemaps)) break; // Found the sitemap index
            if (!empty($articles)) break; // Found URLs directly
        }

        // Process sub-sitemaps (skip image, video, category, tag, author sitemaps)
        foreach ($subSitemaps as $subUrl) {
            // Only scrape post/page/content sitemaps, skip others
            $lower = strtolower($subUrl);
            if (str_contains($lower, 'image') || str_contains($lower, 'video')
                || str_contains($lower, 'author') || str_contains($lower, 'tag-sitemap')
                || str_contains($lower, 'category-sitemap')) {
                continue;
            }

            $xml = $this->fetchSitemap($subUrl);
            if (!$xml) continue;

            $count = 0;
            foreach ($xml->children() as $url) {
                $loc = (string) ($url->loc ?? '');
                if ($loc) {
                    $this->addArticleUrl($loc, $baseUrl, $articles, $seen);
                    $count++;
                }
            }

            Log::info('ScrapeGenericSiteJob: sitemap parsed', [
                'url'  => basename($subUrl),
                'urls' => $count,
            ]);
        }

        return $articles;
    }

    private function addArticleUrl(string $url, string $baseUrl, array &$articles, array &$seen): void
    {
        if (isset($seen[$url])) return;
        if (empty($url) || $url === $baseUrl || $url === $baseUrl . '/') return;

        // Skip non-content URLs
        $lower = strtolower($url);
        if (str_contains($lower, '/wp-admin') || str_contains($lower, '/wp-login')
            || str_contains($lower, '/cart') || str_contains($lower, '/checkout')
            || str_contains($lower, '/my-account') || str_contains($lower, '/feed/')
            || str_contains($lower, '/attachment/') || str_contains($lower, '#')) {
            return;
        }

        $seen[$url] = true;

        $articles[] = [
            'url'      => $url,
            'title'    => '',
            'category' => $this->extractCategoryFromUrl($url),
            'section'  => $this->isGuideUrl($url) ? 'guide' : 'magazine',
        ];
    }

    /**
     * Fallback: discover URLs from homepage links.
     */
    private function discoverFromHomepage(string $baseUrl, ContentScraperService $scraper): array
    {
        $html = $this->fetchPage($baseUrl);
        if (!$html) return [];

        $articles = [];
        $seen = [];
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//a[@href]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (!$href || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) continue;

            $fullUrl = str_starts_with($href, 'http') ? $href : $baseUrl . $href;
            $linkDomain = parse_url($fullUrl, PHP_URL_HOST);

            // Only internal links
            if ($linkDomain !== $baseDomain) continue;

            $this->addArticleUrl($fullUrl, $baseUrl, $articles, $seen);
        }

        unset($xpath, $dom);
        return array_slice($articles, 0, 500); // Limit homepage crawl
    }

    /**
     * Extract contacts from common contact/about pages.
     */
    private function scrapeContacts(ContentSource $source, string $baseUrl): void
    {
        $contactPages = [
            '/contact', '/contact/', '/contactez-nous/', '/nous-contacter/',
            '/about', '/about/', '/a-propos/', '/qui-sommes-nous/',
            '/contact-us', '/contact-us/',
        ];

        $emailsFound = [];

        foreach ($contactPages as $page) {
            $html = $this->fetchPage($baseUrl . $page);
            if (!$html) continue;

            // Extract all emails
            if (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $html, $matches)) {
                foreach ($matches[1] as $email) {
                    $email = strtolower(trim($email));
                    // Skip common non-useful emails
                    if (str_ends_with($email, '.png') || str_ends_with($email, '.jpg')
                        || str_contains($email, 'example.com') || str_contains($email, 'wixpress')
                        || str_contains($email, 'sentry.io') || str_contains($email, 'schema.org')) {
                        continue;
                    }
                    $emailsFound[$email] = $baseUrl . $page;
                }
            }
        }

        foreach ($emailsFound as $email => $pageUrl) {
            $domain = explode('@', $email)[1] ?? '';
            ContentContact::updateOrCreate(
                ['email' => $email, 'source_id' => $source->id],
                [
                    'name'        => ucwords(str_replace('.', ' ', explode('@', $email)[0])),
                    'role'        => 'Contact',
                    'company'     => $source->name,
                    'company_url' => $baseUrl,
                    'sector'      => $this->detectSector($baseUrl),
                    'page_url'    => $pageUrl,
                    'language'    => $this->detectLanguage($baseUrl, 'fr'),
                    'scraped_at'  => now(),
                ]
            );
        }

        if (!empty($emailsFound)) {
            Log::info('ScrapeGenericSiteJob: contacts found', [
                'source' => $source->slug,
                'count'  => count($emailsFound),
            ]);
        }
    }

    private function detectLanguage(string $url, string $default = 'en'): string
    {
        if (str_contains($url, '/fr/') || str_ends_with(parse_url($url, PHP_URL_HOST) ?? '', '.fr')) return 'fr';
        if (str_contains($url, '/en/') || str_ends_with(parse_url($url, PHP_URL_HOST) ?? '', '.com')) return $default;
        if (str_contains($url, '/de/') || str_ends_with(parse_url($url, PHP_URL_HOST) ?? '', '.de')) return 'de';
        if (str_contains($url, '/es/') || str_ends_with(parse_url($url, PHP_URL_HOST) ?? '', '.es')) return 'es';
        return $default;
    }

    private function detectSector(string $url): string
    {
        $lower = strtolower($url);
        if (str_contains($lower, 'assur') || str_contains($lower, 'sante') || str_contains($lower, 'health')) return 'assurance';
        if (str_contains($lower, 'visa') || str_contains($lower, 'immigration')) return 'visa';
        if (str_contains($lower, 'employ') || str_contains($lower, 'job') || str_contains($lower, 'work')) return 'emploi';
        if (str_contains($lower, 'immobil') || str_contains($lower, 'housing') || str_contains($lower, 'property')) return 'immobilier';
        return 'media';
    }

    private function isGuideUrl(string $url): bool
    {
        return (bool) preg_match('#/(guide|destination|country|pays|living|move|visa|cost-of-living)#i', $url);
    }

    private function extractCategoryFromUrl(string $url): ?string
    {
        if (preg_match('#/(visa|health|sante|education|employment|emploi|housing|logement|finance|tax|transport|culture|retirement|retraite)#i', $url, $m)) {
            return ucfirst(strtolower($m[1]));
        }
        return null;
    }

    private function fetchSitemap(string $url): ?\SimpleXMLElement
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)'])
                ->get($url);
            if (!$response->successful()) return null;
            return @simplexml_load_string($response->body()) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)'])
                ->get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        $source = ContentSource::find($this->sourceId);
        if ($source) $source->update(['status' => 'pending']);
        Log::error('ScrapeGenericSiteJob: job failed', ['sourceId' => $this->sourceId, 'error' => $e->getMessage()]);
    }
}
