<?php

namespace App\Services\Scraping;

use App\Models\RssBlogFeed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Option D2 — Scraper prudent pour annuaires de blogs (FeedSpot, AllTop).
 *
 * Stratégie ULTRA PRUDENT anti-ban :
 * - UA rotation (6 navigateurs modernes)
 * - Delays 15-30s aléatoires entre requêtes
 * - Max 50 requêtes par run
 * - Circuit breaker : 2× HTTP 403/429 consécutifs → abort + pause 24h
 * - 1 run par jour max par directory source (via cache lock)
 * - Log détaillé pour audit
 *
 * Pour chaque page de catégorie d'annuaire :
 *   1. Fetch URL (respecte delay + throttle)
 *   2. Extrait les liens externes vers blogs (pas internal)
 *   3. Pour chaque blog : auto-detect RSS
 *   4. Si RSS + pas connu : insert dans rss_blog_feeds
 */
class FeedDirectoryScraperService
{
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0',
    ];

    private const MIN_DELAY_MS = 15_000_000; // 15s
    private const MAX_DELAY_MS = 30_000_000; // 30s
    private const MAX_REQUESTS_PER_RUN = 50;
    private const MAX_CONSECUTIVE_ERRORS = 2;
    private const CIRCUIT_BREAKER_TTL_HOURS = 24;
    private const DAILY_RUN_LOCK_HOURS = 24;

    private float $lastRequestTime = 0;
    private int $requestCount = 0;
    private int $consecutiveErrors = 0;

    public function __construct(
        private readonly RssAutoDetectService $autoDetect,
    ) {}

    /**
     * Scrape une liste de catégories depuis un annuaire donné.
     *
     * @param string $sourceName Identifiant unique (ex: 'feedspot', 'alltop')
     * @param array<string,string> $categories Map ['category_slug' => 'full_url_of_category_page']
     * @return array{categories_scraped:int, new_feeds:int, blogs_found:int, skipped_lock:bool}
     */
    public function scrapeCategories(string $sourceName, array $categories): array
    {
        // Lock quotidien : 1 run max par jour par source
        $lockKey = "scraper:directory:{$sourceName}:daily_lock";
        if (Cache::has($lockKey)) {
            Log::info("FeedDirectoryScraper ({$sourceName}): daily lock active, skip run");
            return [
                'categories_scraped' => 0,
                'new_feeds' => 0,
                'blogs_found' => 0,
                'skipped_lock' => true,
            ];
        }

        // Circuit breaker actif ?
        $circuitKey = "scraper:directory:{$sourceName}:circuit_open";
        if (Cache::has($circuitKey)) {
            Log::warning("FeedDirectoryScraper ({$sourceName}): circuit breaker OPEN, skip run");
            return [
                'categories_scraped' => 0,
                'new_feeds' => 0,
                'blogs_found' => 0,
                'skipped_lock' => true,
            ];
        }

        $newFeeds = 0;
        $blogsFound = 0;
        $categoriesScraped = 0;

        foreach ($categories as $categorySlug => $url) {
            if ($this->requestCount >= self::MAX_REQUESTS_PER_RUN) {
                Log::info("FeedDirectoryScraper ({$sourceName}): max requests reached, stopping");
                break;
            }

            try {
                $blogs = $this->scrapeCategory($url);
                $blogsFound += count($blogs);
                $categoriesScraped++;

                foreach ($blogs as $blogUrl) {
                    if ($this->requestCount >= self::MAX_REQUESTS_PER_RUN) break;

                    // Déjà connu via domaine ?
                    $domain = parse_url($blogUrl, PHP_URL_HOST);
                    if (!$domain) continue;
                    if (RssBlogFeed::where('base_url', 'LIKE', "%{$domain}%")->exists()) continue;

                    // Auto-detect RSS (2 requêtes max supplémentaires)
                    $feedUrl = $this->autoDetect->detectFeedUrl($blogUrl);
                    $this->requestCount++; // compte comme 1 requête externe
                    $this->sleepRandom();

                    if (!$feedUrl) continue;
                    if (RssBlogFeed::where('url', $feedUrl)->exists()) continue;

                    try {
                        RssBlogFeed::create([
                            'name'     => ucfirst(preg_replace('/^www\./', '', $domain)),
                            'url'      => $feedUrl,
                            'base_url' => $blogUrl,
                            'language' => 'fr', // à affiner plus tard par categorie
                            'category' => "{$sourceName}_{$categorySlug}",
                            'active'   => true,
                            'fetch_about' => true,
                            'fetch_pattern_inference' => false,
                            'fetch_interval_hours' => 24,
                            'notes'    => "Découvert via {$sourceName} / {$categorySlug}",
                        ]);
                        $newFeeds++;
                    } catch (\Throwable $e) {
                        Log::debug("FeedDirectoryScraper ({$sourceName}): insert skipped", ['url' => $feedUrl]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("FeedDirectoryScraper ({$sourceName}): category failed", [
                    'category' => $categorySlug,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Lock 24h
        Cache::put($lockKey, now()->toIso8601String(), now()->addHours(self::DAILY_RUN_LOCK_HOURS));

        Log::info("FeedDirectoryScraper ({$sourceName}): done", [
            'categories_scraped' => $categoriesScraped,
            'blogs_found' => $blogsFound,
            'new_feeds' => $newFeeds,
            'total_requests' => $this->requestCount,
        ]);

        return [
            'categories_scraped' => $categoriesScraped,
            'new_feeds' => $newFeeds,
            'blogs_found' => $blogsFound,
            'skipped_lock' => false,
        ];
    }

    /**
     * Scrape une seule page de catégorie et retourne les URLs de blogs
     * détectées (liens externes, filtrés).
     *
     * @return array<int,string>
     */
    private function scrapeCategory(string $url): array
    {
        $this->sleepRandom();
        $html = $this->safeGet($url);
        if (!$html) return [];

        // Extraire tous les <a href> externes
        preg_match_all('/<a[^>]+href=["\'](https?:\/\/[^"\'#?\s]+)["\']/i', $html, $matches);
        $urls = array_unique($matches[1] ?? []);

        $sourceDomain = parse_url($url, PHP_URL_HOST);
        $blogs = [];

        foreach ($urls as $candidate) {
            $domain = parse_url($candidate, PHP_URL_HOST);
            if (!$domain || $domain === $sourceDomain) continue;

            // Filtrer domaines utilitaires (social, big tech, internal)
            $cleanDomain = preg_replace('/^www\./', '', $domain);
            if ($this->isUtilityDomain($cleanDomain)) continue;

            // Normaliser root : https://domain.com
            $scheme = parse_url($candidate, PHP_URL_SCHEME) ?: 'https';
            $rootUrl = "{$scheme}://{$domain}";

            // Dédup par domaine
            if (!in_array($rootUrl, $blogs, true)) {
                $blogs[] = $rootUrl;
            }
        }

        return $blogs;
    }

    /**
     * HTTP GET avec UA rotation, timeout, gestion circuit breaker.
     * Retourne null en cas d'échec (403, 429, 5xx, timeout).
     */
    private function safeGet(string $url): ?string
    {
        $this->requestCount++;

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ])
                ->get($url);

            $status = $response->status();

            // 403/429 → circuit breaker
            if (in_array($status, [403, 429], true)) {
                $this->consecutiveErrors++;
                Log::warning('FeedDirectoryScraper: HTTP ' . $status . " sur {$url}", [
                    'consecutive_errors' => $this->consecutiveErrors,
                ]);
                if ($this->consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                    $this->openCircuitBreaker($url);
                    throw new \RuntimeException("Circuit breaker OPEN ({$status}× {$this->consecutiveErrors})");
                }
                return null;
            }

            if (!$response->successful()) {
                Log::warning("FeedDirectoryScraper: HTTP {$status} sur {$url}");
                return null;
            }

            // Reset errors counter sur succès
            $this->consecutiveErrors = 0;
            return $response->body();
        } catch (\RuntimeException $e) {
            // Re-throw circuit breaker
            throw $e;
        } catch (\Throwable $e) {
            Log::warning("FeedDirectoryScraper: exception {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Ouvre le circuit breaker 24h (stop tous runs pendant ce temps).
     */
    private function openCircuitBreaker(string $lastUrl): void
    {
        $sourceDomain = parse_url($lastUrl, PHP_URL_HOST) ?? 'unknown';
        $cleanDomain = preg_replace('/^www\./', '', $sourceDomain);
        // Détecter quel source (feedspot/alltop) via domain
        $sourceName = str_contains($cleanDomain, 'feedspot') ? 'feedspot'
                    : (str_contains($cleanDomain, 'alltop') ? 'alltop' : 'unknown');
        Cache::put(
            "scraper:directory:{$sourceName}:circuit_open",
            now()->toIso8601String(),
            now()->addHours(self::CIRCUIT_BREAKER_TTL_HOURS)
        );
        Log::critical("FeedDirectoryScraper: CIRCUIT BREAKER ouvert pour {$sourceName} 24h");
    }

    /**
     * Délai aléatoire 15-30s entre requêtes.
     */
    private function sleepRandom(): void
    {
        $delay = random_int(self::MIN_DELAY_MS, self::MAX_DELAY_MS);
        $elapsed = (microtime(true) - $this->lastRequestTime) * 1_000_000;
        if ($elapsed < $delay) {
            usleep((int) ($delay - $elapsed));
        }
        $this->lastRequestTime = microtime(true);
    }

    private function isUtilityDomain(string $cleanDomain): bool
    {
        $utilities = [
            'feedspot.com', 'alltop.com', 'bloglovin.com',
            'twitter.com', 'x.com', 'facebook.com', 'linkedin.com',
            'instagram.com', 'youtube.com', 'youtu.be', 'tiktok.com',
            'pinterest.com', 'amazon.com', 'amazon.fr',
            'google.com', 'google.fr', 'bing.com', 'duckduckgo.com',
            'wikipedia.org', 'wordpress.com', 'wordpress.org', 'wix.com',
            'blogger.com', 'medium.com', 'substack.com',
            'booking.com', 'airbnb.com', 'tripadvisor.com',
            'schema.org', 'w3.org', 'creativecommons.org',
            'gravatar.com', 'gstatic.com', 'googleusercontent.com',
            'bit.ly', 't.co', 'goo.gl',
        ];
        foreach ($utilities as $u) {
            if ($cleanDomain === $u || str_ends_with($cleanDomain, '.' . $u)) return true;
        }
        return false;
    }

    public function resetCircuitBreaker(string $sourceName): void
    {
        Cache::forget("scraper:directory:{$sourceName}:circuit_open");
    }
}
