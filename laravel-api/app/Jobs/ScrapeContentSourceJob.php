<?php

namespace App\Jobs;

use App\Models\ContentCountry;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeContentSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min (scrape 9 pages + dispatch 200+ countries)
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-source-' . $this->sourceId))
                ->releaseAfter(3600)
                ->expireAfter(3600),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) {
            Log::warning('ScrapeContentSourceJob: source not found', ['id' => $this->sourceId]);
            return;
        }

        $source->update(['status' => 'scraping']);
        Log::info('ScrapeContentSourceJob: starting', ['source' => $source->slug]);

        try {
            $countries = $scraper->scrapeCountryList($source);
            $dispatchedCount = 0;

            foreach ($countries as $countryData) {
                $existing = ContentCountry::where('source_id', $source->id)
                    ->where('slug', $countryData['slug'])
                    ->first();

                if ($existing) {
                    if (!$existing->scraped_at) {
                        ScrapeContentCountryJob::dispatch($existing->id);
                        $dispatchedCount++;
                    }
                    continue;
                }

                $country = ContentCountry::create([
                    'source_id' => $source->id,
                    'name'      => $countryData['name'],
                    'slug'      => $countryData['slug'],
                    'continent' => $countryData['continent'],
                    'guide_url' => $countryData['guide_url'],
                ]);

                ScrapeContentCountryJob::dispatch($country->id);
                $dispatchedCount++;
                // No rateLimitSleep here - dispatching is local, not HTTP
            }

            $source->update(['total_countries' => $source->countries()->count()]);

            // If no jobs dispatched (e.g. rescrape after completion), check completion now
            if ($dispatchedCount === 0) {
                $totalCountries = $source->countries()->count();
                $scrapedCountries = $source->countries()->whereNotNull('scraped_at')->count();
                if ($scrapedCountries >= $totalCountries && $totalCountries > 0) {
                    $source->update([
                        'status'          => 'completed',
                        'total_articles'  => $source->articles()->count(),
                        'total_links'     => $source->externalLinks()->count(),
                        'last_scraped_at' => now(),
                    ]);
                } else {
                    // Nothing to scrape, nothing to dispatch - reset
                    $source->update(['status' => 'completed']);
                }
            }

            Log::info('ScrapeContentSourceJob: countries dispatched', [
                'source'     => $source->slug,
                'countries'  => count($countries),
                'dispatched' => $dispatchedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeContentSourceJob: failed', [
                'source' => $source->slug,
                'error'  => $e->getMessage(),
            ]);
            $source->update(['status' => 'pending']);
        }
    }

    public function failed(\Throwable $e): void
    {
        $source = ContentSource::find($this->sourceId);
        if ($source) {
            $source->update(['status' => 'pending']);
        }
        Log::error('ScrapeContentSourceJob: job failed permanently', [
            'sourceId' => $this->sourceId,
            'error'    => $e->getMessage(),
        ]);
    }
}
