<?php

namespace App\Jobs;

use App\Models\RssBlogFeed;
use App\Services\Scraping\RssAutoDetectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Option D2 — Job qui crawle les homepages des feeds existants pour
 * découvrir de nouveaux feeds via les "blogrolls" (liens vers autres
 * blogs de la même thématique).
 *
 * Zero risque ban :
 * - 1 fetch homepage par feed existant (pas de crawl profond)
 * - Délai 3-6s aléatoire entre chaque feed
 * - UA déclaré SOS-Expat-BlogDiscovery/1.0
 * - Timeout 15s par requête
 *
 * Pour chaque feed dans rss_blog_feeds :
 *   1. Fetch homepage (base_url)
 *   2. Extrait tous les <a href> externes (domaine différent)
 *   3. Pour chaque lien externe unique, tente autodetect RSS
 *   4. Si feed detecté + pas déjà en DB → ajoute avec active=true
 *
 * Permet de passer de ~78 feeds à ~300-800 feeds en 1 run complet.
 */
class CrawlFeedsBlogrollJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600; // 1h max
    public int $tries = 1;

    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOS-Expat-BlogDiscovery/1.0; +https://sos-expat.com/bot)';
    private const TIMEOUT_SECONDS = 15;
    private const MAX_LINKS_PER_HOMEPAGE = 30;

    /** Domaines "hubs" à ignorer (pas des blogrolls perso) */
    private const IGNORED_DOMAINS = [
        'twitter.com', 'x.com', 'facebook.com', 'linkedin.com', 'instagram.com',
        'youtube.com', 'youtu.be', 'tiktok.com', 'pinterest.com',
        'amazon.com', 'amazon.fr', 'google.com', 'bing.com',
        'wikipedia.org', 'wordpress.com', 'wordpress.org', 'wix.com',
        'booking.com', 'airbnb.com', 'tripadvisor.com',
        'w3.org', 'schema.org',
    ];

    public function __construct(private readonly ?int $feedId = null)
    {
        $this->onQueue('scraper');
    }

    public function handle(RssAutoDetectService $autoDetect): void
    {
        $query = RssBlogFeed::active();
        if ($this->feedId !== null) {
            $query->where('id', $this->feedId);
        }
        // Limiter aux feeds avec base_url renseigne
        $query->whereNotNull('base_url');

        $feeds = $query->get();
        Log::info('CrawlFeedsBlogrollJob: start', ['count' => $feeds->count()]);

        $totalDiscovered = 0;
        $totalAdded = 0;
        $seenDomains = []; // éviter de retraiter le même domaine 2 fois dans 1 run

        foreach ($feeds as $feed) {
            try {
                $externalLinks = $this->extractExternalLinks($feed->resolvedBaseUrl(), $seenDomains);
                Log::debug("CrawlFeedsBlogrollJob: {$feed->name} → ".count($externalLinks).' liens externes');

                foreach ($externalLinks as $link) {
                    $totalDiscovered++;

                    // Skip si domaine déjà connu
                    $domain = parse_url($link, PHP_URL_HOST);
                    if (!$domain) continue;
                    if (isset($seenDomains[$domain])) continue;
                    $seenDomains[$domain] = true;

                    // Skip si déjà dans rss_blog_feeds (via base_url ou url)
                    if ($this->alreadyKnown($link)) continue;

                    // Tentative auto-detect RSS
                    $feedUrl = $autoDetect->detectFeedUrl($link);
                    if (!$feedUrl) continue;

                    // Déjà connu via feedUrl ?
                    if (RssBlogFeed::where('url', $feedUrl)->exists()) continue;

                    // Créer le nouveau feed (name = domaine nettoyé)
                    try {
                        $cleanDomain = preg_replace('/^www\./', '', $domain);
                        RssBlogFeed::create([
                            'name'     => ucfirst($cleanDomain),
                            'url'      => $feedUrl,
                            'base_url' => $link,
                            'language' => $feed->language,
                            'country'  => $feed->country,
                            'category' => 'discovered_blogroll',
                            'active'   => true,
                            'fetch_about' => true,
                            'fetch_pattern_inference' => false,
                            'fetch_interval_hours' => 24, // blogs découverts : 1×/jour
                            'notes'    => "Découvert via blogroll de {$feed->name}",
                        ]);
                        $totalAdded++;
                    } catch (\Throwable $e) {
                        Log::debug('CrawlFeedsBlogrollJob: insert failed (dup)', ['url' => $feedUrl]);
                    }

                    // Anti-ban : délai entre auto-detects
                    usleep(random_int(500_000, 1_500_000)); // 0.5-1.5s
                }

                // Délai plus long entre feeds parents
                usleep(random_int(3_000_000, 6_000_000)); // 3-6s
            } catch (\Throwable $e) {
                Log::warning('CrawlFeedsBlogrollJob: feed crawl failed', [
                    'feed_id' => $feed->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('CrawlFeedsBlogrollJob: done', [
            'feeds_processed' => $feeds->count(),
            'links_discovered' => $totalDiscovered,
            'new_feeds_added' => $totalAdded,
        ]);
    }

    /**
     * Extrait les liens externes (domaine différent) depuis la homepage du feed.
     * Limite à MAX_LINKS_PER_HOMEPAGE pour éviter d'exploser.
     */
    private function extractExternalLinks(?string $homepageUrl, array $seenDomains): array
    {
        if (!$homepageUrl) return [];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($homepageUrl);
            if (!$response->successful()) return [];
            $html = $response->body();
        } catch (\Throwable $e) {
            return [];
        }

        $sourceDomain = parse_url($homepageUrl, PHP_URL_HOST);
        if (!$sourceDomain) return [];

        // Extraire tous les href http/https
        preg_match_all('/<a\s[^>]*href=["\'](https?:\/\/[^"\'#?]+)["\']/i', $html, $matches);
        $urls = array_unique($matches[1] ?? []);

        $externals = [];
        foreach ($urls as $url) {
            $domain = parse_url($url, PHP_URL_HOST);
            if (!$domain) continue;
            if ($domain === $sourceDomain) continue; // lien interne
            $cleanDomain = preg_replace('/^www\./', '', $domain);
            if (in_array($cleanDomain, self::IGNORED_DOMAINS, true)) continue;

            // Normaliser : garder juste le root du site (https://domain.com)
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
            $rootUrl = "{$scheme}://{$domain}";

            if (isset($seenDomains[$domain])) continue;
            $externals[$domain] = $rootUrl; // dedup par domaine

            if (count($externals) >= self::MAX_LINKS_PER_HOMEPAGE) break;
        }

        return array_values($externals);
    }

    /**
     * Check si un URL de site est déjà connu (via base_url ou url).
     */
    private function alreadyKnown(string $siteUrl): bool
    {
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        if (!$domain) return true;

        return RssBlogFeed::where('base_url', 'LIKE', "%{$domain}%")
            ->orWhere('url', 'LIKE', "%{$domain}%")
            ->exists();
    }
}
