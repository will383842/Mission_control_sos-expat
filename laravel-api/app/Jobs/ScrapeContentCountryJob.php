<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentCountry;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeContentCountryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        private int $countryId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-country-' . $this->countryId))
                ->releaseAfter(7200)
                ->expireAfter(7200),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $country = ContentCountry::with('source')->find($this->countryId);
        if (!$country || !$country->source) {
            Log::warning('ScrapeContentCountryJob: country not found', ['id' => $this->countryId]);
            return;
        }

        $source = $country->source;

        if (in_array($source->status, ['paused', 'pending'])) {
            Log::info('ScrapeContentCountryJob: source paused/stopped, skipping', ['country' => $country->slug]);
            return;
        }

        Log::info('ScrapeContentCountryJob: starting', [
            'country' => $country->slug,
            'source'  => $source->slug,
        ]);

        // Pre-load existing data to avoid N+1
        $existingArticleUrls = ContentArticle::where('country_id', $country->id)
            ->pluck('url')
            ->flip()
            ->toArray();

        $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
            ->pluck('url_hash')
            ->flip()
            ->toArray();

        $scrapedCount = 0;
        $failedCount = 0;
        $consecutiveFailures = 0;

        try {
            $articleLinks = $scraper->scrapeCountryArticles($country);
            $scraper->rateLimitSleep();

            // If no individual articles found, scrape the country guide page itself (monolithic page)
            if (empty($articleLinks)) {
                $guideUrl = $country->guide_url;
                if (!isset($existingArticleUrls[$guideUrl])) {
                    $guideContent = $scraper->scrapeArticle($guideUrl);
                    if ($guideContent && $guideContent['word_count'] > 50) {
                        $urlHash = hash('sha256', $guideUrl);
                        ContentArticle::create([
                            'source_id'        => $source->id,
                            'country_id'       => $country->id,
                            'title'            => $guideContent['title'] ?: "Guide {$country->name}",
                            'slug'             => $guideContent['slug'] ?: $country->slug,
                            'url'              => $guideUrl,
                            'url_hash'         => $urlHash,
                            'category'         => null,
                            'content_text'     => $guideContent['content_text'],
                            'content_html'     => $guideContent['content_html'],
                            'word_count'       => $guideContent['word_count'],
                            'language'         => $guideContent['language'],
                            'external_links'   => $guideContent['external_links'],
                            'ads_and_sponsors' => $guideContent['ads_and_sponsors'],
                            'images'           => $guideContent['images'],
                            'meta_title'       => $guideContent['meta_title'],
                            'meta_description' => $guideContent['meta_description'],
                            'is_guide'         => true,
                            'scraped_at'       => now(),
                        ]);

                        // Save external links
                        foreach ($guideContent['external_links'] as $link) {
                            $linkHash = hash('sha256', $link['url']);
                            if (!isset($existingLinkHashes[$linkHash])) {
                                ContentExternalLink::create([
                                    'source_id'    => $source->id,
                                    'article_id'   => ContentArticle::where('url_hash', $urlHash)->value('id'),
                                    'url'          => $link['url'],
                                    'url_hash'     => $linkHash,
                                    'original_url' => $link['original_url'],
                                    'domain'       => $link['domain'],
                                    'anchor_text'  => $link['anchor_text'],
                                    'context'      => $link['context'],
                                    'country_id'   => $country->id,
                                    'link_type'    => $link['link_type'],
                                    'is_affiliate' => $link['is_affiliate'],
                                    'language'     => $guideContent['language'] ?? 'fr',
                                ]);
                                $existingLinkHashes[$linkHash] = true;
                            }
                        }

                        $scrapedCount++;
                        Log::info('ScrapeContentCountryJob: scraped monolithic guide page', [
                            'country'    => $country->slug,
                            'word_count' => $guideContent['word_count'],
                        ]);
                    }
                }
            }

            foreach ($articleLinks as $articleData) {
                if (isset($existingArticleUrls[$articleData['url']])) {
                    $scrapedCount++;
                    continue;
                }

                try {
                    $articleContent = $scraper->scrapeArticle($articleData['url']);
                    if (!$articleContent) {
                        $failedCount++;
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= 10) {
                            Log::error('ScrapeContentCountryJob: aborting after 10 consecutive failures', [
                                'country' => $country->slug,
                            ]);
                            break;
                        }
                        $scraper->rateLimitSleep();
                        continue;
                    }

                    $urlHash = hash('sha256', $articleData['url']);

                    $article = ContentArticle::create([
                        'source_id'        => $source->id,
                        'country_id'       => $country->id,
                        'title'            => $articleContent['title'] ?: $articleData['title'],
                        'slug'             => $articleContent['slug'] ?: substr($urlHash, 0, 12),
                        'url'              => $articleData['url'],
                        'url_hash'         => $urlHash,
                        'category'         => $articleData['category'],
                        'content_text'     => $articleContent['content_text'],
                        'content_html'     => $articleContent['content_html'],
                        'word_count'       => $articleContent['word_count'],
                        'language'         => $articleContent['language'],
                        'external_links'   => $articleContent['external_links'],
                        'ads_and_sponsors' => $articleContent['ads_and_sponsors'],
                        'images'           => $articleContent['images'],
                        'meta_title'       => $articleContent['meta_title'],
                        'meta_description' => $articleContent['meta_description'],
                        'is_guide'         => $articleData['is_guide'],
                        'scraped_at'       => now(),
                    ]);

                    $existingArticleUrls[$articleData['url']] = true;
                    $consecutiveFailures = 0; // Reset on success

                    foreach ($articleContent['external_links'] as $link) {
                        $linkHash = hash('sha256', $link['url']);

                        if (isset($existingLinkHashes[$linkHash])) {
                            ContentExternalLink::where('source_id', $source->id)
                                ->where('url_hash', $linkHash)
                                ->increment('occurrences');
                        } else {
                            ContentExternalLink::create([
                                'source_id'    => $source->id,
                                'article_id'   => $article->id,
                                'url'          => $link['url'],
                                'url_hash'     => $linkHash,
                                'original_url' => $link['original_url'],
                                'domain'       => $link['domain'],
                                'anchor_text'  => $link['anchor_text'],
                                'context'      => $link['context'],
                                'country_id'   => $country->id,
                                'link_type'    => $link['link_type'],
                                'is_affiliate' => $link['is_affiliate'],
                                'language'     => $articleContent['language'] ?? 'fr',
                            ]);
                            $existingLinkHashes[$linkHash] = true;
                        }
                    }

                    $scrapedCount++;

                    if ($scrapedCount % 20 === 0) {
                        gc_collect_cycles();
                    }

                    $scraper->rateLimitSleep();

                } catch (\Throwable $e) {
                    $failedCount++;
                    $consecutiveFailures++;
                    Log::warning('ScrapeContentCountryJob: article failed', [
                        'url'   => $articleData['url'],
                        'error' => $e->getMessage(),
                    ]);
                    if ($consecutiveFailures >= 10) {
                        Log::error('ScrapeContentCountryJob: aborting after 10 consecutive failures');
                        break;
                    }
                    $scraper->rateLimitSleep();
                    continue;
                }
            }

            Log::info('ScrapeContentCountryJob: completed', [
                'country' => $country->slug,
                'scraped' => $scrapedCount,
                'failed'  => $failedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeContentCountryJob: failed', [
                'country' => $country->slug,
                'error'   => $e->getMessage(),
            ]);
        } finally {
            // Always mark country as processed (even on failure) to avoid blocking source completion
            if ($country && !$country->scraped_at) {
                $country->update([
                    'articles_count' => $country->articles()->count(),
                    'scraped_at'     => now(),
                ]);
            }
            if ($source) {
                $this->checkSourceCompletion($source);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeContentCountryJob: job failed permanently', [
            'countryId' => $this->countryId,
            'error'     => $e->getMessage(),
        ]);
    }

    private function checkSourceCompletion(ContentSource $source): void
    {
        $totalCountries = $source->countries()->count();
        $scrapedCountries = $source->countries()->whereNotNull('scraped_at')->count();

        $source->update([
            'total_countries' => $totalCountries,
            'total_articles'  => $source->articles()->count(),
            'total_links'     => $source->externalLinks()->count(),
        ]);

        if ($scrapedCountries >= $totalCountries && $totalCountries > 0) {
            $source->update([
                'status'          => 'completed',
                'last_scraped_at' => now(),
            ]);
        }
    }
}
