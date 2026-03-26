<?php

namespace App\Jobs;

use App\Models\ContentQuestion;
use App\Models\ContentSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeForumQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h
    public int $tries = 1;

    private const RATE_LIMIT = 2;
    private const MAX_PAGES_PER_COUNTRY = 50; // Max 50 pages x ~30 topics = ~1500 topics per country
    private const BASE_URL = 'https://www.expat.com';

    private float $lastRequestTime = 0;

    public function __construct(
        private int $sourceId,
        private string $language = 'fr',
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-forum-' . $this->sourceId . '-' . $this->language))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $langPrefix = $this->language === 'fr' ? '/fr' : '/en';

        Log::info('ScrapeForumQuestionsJob: starting', [
            'source'   => $source->slug,
            'language' => $this->language,
        ]);

        // Step 1: Discover country forums
        $forumUrl = self::BASE_URL . $langPrefix . '/forum/';
        $html = $this->fetchPage($forumUrl);
        if (!$html) {
            Log::error('ScrapeForumQuestionsJob: failed to fetch forum index');
            return;
        }

        $countryForums = $this->discoverCountryForums($html, $langPrefix);
        Log::info('ScrapeForumQuestionsJob: found country forums', ['count' => count($countryForums)]);

        // Pre-load existing
        $existingHashes = ContentQuestion::where('source_id', $source->id)
            ->pluck('url_hash')
            ->flip()
            ->toArray();

        $totalScraped = 0;
        $totalSkipped = 0;

        // Step 2: For each country, scrape topic listings
        foreach ($countryForums as $forum) {
            $countryScraped = 0;

            for ($page = 1; $page <= self::MAX_PAGES_PER_COUNTRY; $page++) {
                $this->rateLimitSleep();

                $pageUrl = $page === 1
                    ? $forum['url']
                    : $forum['url'] . $page . '/';

                $pageHtml = $this->fetchPage($pageUrl);
                if (!$pageHtml) break;

                $topics = $this->extractTopics($pageHtml);
                if (empty($topics)) break;

                $newOnPage = 0;
                foreach ($topics as $topic) {
                    $urlHash = hash('sha256', $topic['url']);

                    if (isset($existingHashes[$urlHash])) {
                        $totalSkipped++;
                        continue;
                    }

                    ContentQuestion::create([
                        'source_id'        => $source->id,
                        'title'            => mb_substr($topic['title'], 0, 500),
                        'url'              => $topic['url'],
                        'url_hash'         => $urlHash,
                        'country'          => $forum['country'],
                        'country_slug'     => $forum['country_slug'],
                        'continent'        => $forum['continent'],
                        'replies'          => $topic['replies'],
                        'views'            => $topic['views'],
                        'is_sticky'        => $topic['sticky'],
                        'is_closed'        => $topic['closed'],
                        'last_post_date'   => $topic['last_post_date'],
                        'last_post_author' => $topic['last_post_author'],
                        'language'         => $this->language,
                        'scraped_at'       => now(),
                    ]);

                    $existingHashes[$urlHash] = true;
                    $countryScraped++;
                    $newOnPage++;
                }

                $totalScraped += $newOnPage;

                // If no new topics on this page, all remaining are already scraped
                if ($newOnPage === 0 && $totalSkipped > 0) break;
            }

            if ($countryScraped > 0 || $totalScraped % 500 === 0) {
                Log::info('ScrapeForumQuestionsJob: country done', [
                    'country' => $forum['country'],
                    'scraped' => $countryScraped,
                    'total'   => $totalScraped,
                ]);
            }

            if ($totalScraped % 200 === 0) gc_collect_cycles();
        }

        Log::info('ScrapeForumQuestionsJob: completed', [
            'total_scraped' => $totalScraped,
            'total_skipped' => $totalSkipped,
        ]);
    }

    private function discoverCountryForums(string $html, string $langPrefix): array
    {
        $forums = [];
        $unescaped = str_replace('\\/', '/', $html);

        // Extract from JSON embedded data
        $forumPattern = $this->language === 'fr'
            ? '#"id"\s*:\s*"https?://[^"]*?/fr/forum/([a-z-]+)/([a-z-]+)/"\s*,\s*"text"\s*:\s*"([^"]+)"#'
            : '#"id"\s*:\s*"https?://[^"]*?/en/forum/([a-z-]+)/([a-z-]+)/"\s*,\s*"text"\s*:\s*"([^"]+)"#';

        if (preg_match_all($forumPattern, $unescaped, $matches, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($matches as $m) {
                $slug = $m[2];
                if (isset($seen[$slug])) continue;
                $seen[$slug] = true;

                $forums[] = [
                    'url'          => self::BASE_URL . $langPrefix . '/forum/' . $m[1] . '/' . $m[2] . '/',
                    'country'      => html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'),
                    'country_slug' => $m[2],
                    'continent'    => $this->normalizeContinent($m[1]),
                ];
            }
        }

        // Fallback: parse <a> links
        if (empty($forums)) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            $pattern = $langPrefix . '/forum/';
            $links = $xpath->query('//a[contains(@href, "' . $pattern . '")]');
            $seen = [];

            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);

                if (preg_match('#' . preg_quote($langPrefix, '#') . '/forum/([a-z-]+)/([a-z-]+)/?$#', $href, $m)) {
                    $slug = $m[2];
                    if (isset($seen[$slug])) continue;
                    $seen[$slug] = true;

                    $forums[] = [
                        'url'          => self::BASE_URL . $href,
                        'country'      => $text ?: ucfirst(str_replace('-', ' ', $slug)),
                        'country_slug' => $slug,
                        'continent'    => $this->normalizeContinent($m[1]),
                    ];
                }
            }
            unset($xpath, $dom);
        }

        return $forums;
    }

    private function extractTopics(string $html): array
    {
        $topics = [];

        // Extract from var topics = [...] JSON
        if (preg_match('/var\s+topics\s*=\s*(\[[\s\S]*?\])\s*;/', $html, $m)) {
            $json = preg_replace('/,\s*([\}\]])/', '$1', $m[1]); // Clean trailing commas
            $data = @json_decode($json, true);

            if (is_array($data)) {
                foreach ($data as $topic) {
                    $title = $topic['title'] ?? '';
                    $url = $topic['link'] ?? '';
                    if (!$title || !$url) continue;

                    // Resolve relative URLs
                    if (!str_starts_with($url, 'http')) {
                        $url = self::BASE_URL . $url;
                    }

                    // Parse last post info
                    $lastPostLabel = $topic['lastPost']['label'] ?? '';
                    $lastPostDate = null;
                    $lastPostAuthor = null;
                    if (preg_match('/^(.+?)\s+by\s+(.+)$/i', $lastPostLabel, $lm)) {
                        $lastPostDate = trim($lm[1]);
                        $lastPostAuthor = trim($lm[2]);
                    } elseif (preg_match('/^(.+?)\s+par\s+(.+)$/i', $lastPostLabel, $lm)) {
                        $lastPostDate = trim($lm[1]);
                        $lastPostAuthor = trim($lm[2]);
                    }

                    $topics[] = [
                        'title'            => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                        'url'              => $url,
                        'replies'          => (int) ($topic['replies'] ?? 0),
                        'views'            => (int) ($topic['views'] ?? 0),
                        'sticky'           => $topic['sticky'] ?? false,
                        'closed'           => $topic['closed'] ?? false,
                        'last_post_date'   => $lastPostDate,
                        'last_post_author' => $lastPostAuthor,
                    ];
                }
            }
        }

        return $topics;
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

    private function rateLimitSleep(): void
    {
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT) {
            usleep((int) ((self::RATE_LIMIT - $elapsed) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);
    }

    private function normalizeContinent(string $slug): string
    {
        return match ($slug) {
            'afrique', 'africa'                        => 'Afrique',
            'amerique-du-nord', 'north-america'        => 'Amerique du Nord',
            'amerique-du-sud', 'south-america'         => 'Amerique du Sud',
            'amerique-centrale', 'central-america'     => 'Amerique Centrale',
            'asie', 'asia'                             => 'Asie',
            'europe'                                   => 'Europe',
            'moyen-orient', 'middle-east'              => 'Moyen-Orient',
            'oceanie', 'oceania'                       => 'Oceanie',
            default                                    => ucfirst(str_replace('-', ' ', $slug)),
        };
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeForumQuestionsJob: failed', ['error' => $e->getMessage()]);
    }
}
