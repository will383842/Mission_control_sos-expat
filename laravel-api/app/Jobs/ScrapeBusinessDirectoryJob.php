<?php

namespace App\Jobs;

use App\Models\ContentBusiness;
use App\Models\ContentSource;
use App\Services\BusinessDirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapeBusinessDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h — 37 countries x 30 categories
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-business-' . $this->sourceId))
                ->releaseAfter(7200)
                ->expireAfter(7200),
        ];
    }

    public function handle(BusinessDirectoryScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        Log::info('ScrapeBusinessDirectoryJob: starting', ['source' => $source->slug]);

        try {
            // Step 1: Discover all countries and cities
            $dirUrl = str_replace('/guide/', '/entreprises/', $source->base_url);
            $locations = $scraper->discoverLocations($dirUrl);

            $totalBusinesses = 0;

            foreach ($locations as $location) {
                $scraper->rateLimitSleep();

                // Step 2: Get categories for this country (use the country page)
                $categories = $scraper->discoverCategories($location['url']);

                if (empty($categories)) {
                    // No categories = try scraping the country page directly
                    $bizList = $scraper->scrapeListingPage($location['url']);
                    $this->saveBusinesses($bizList, $source, $location, null, null);
                    $totalBusinesses += count($bizList);
                    continue;
                }

                // Step 3: For each subcategory, scrape the listing
                foreach ($categories as $cat) {
                    foreach ($cat['children'] ?? [] as $sub) {
                        if (($sub['count'] ?? 0) === 0) continue;

                        $scraper->rateLimitSleep();
                        $bizList = $scraper->scrapeListingPage($sub['url']);
                        $this->saveBusinesses($bizList, $source, $location, $cat, $sub);
                        $totalBusinesses += count($bizList);
                    }

                    // Also scrape the parent category if it has direct businesses
                    if (($cat['count'] ?? 0) > 0 && !empty($cat['url'])) {
                        $scraper->rateLimitSleep();
                        $bizList = $scraper->scrapeListingPage($cat['url']);
                        $this->saveBusinesses($bizList, $source, $location, $cat, null);
                        $totalBusinesses += count($bizList);
                    }
                }

                // Also scrape cities within this country
                foreach ($location['cities'] ?? [] as $city) {
                    $scraper->rateLimitSleep();
                    $cityCategories = $scraper->discoverCategories($city['url']);

                    if (empty($cityCategories)) {
                        $bizList = $scraper->scrapeListingPage($city['url']);
                        $this->saveBusinesses($bizList, $source, $location, null, null, $city['name']);
                        $totalBusinesses += count($bizList);
                        continue;
                    }

                    foreach ($cityCategories as $cat) {
                        foreach ($cat['children'] ?? [] as $sub) {
                            if (($sub['count'] ?? 0) === 0) continue;
                            $scraper->rateLimitSleep();
                            $bizList = $scraper->scrapeListingPage($sub['url']);
                            $this->saveBusinesses($bizList, $source, $location, $cat, $sub, $city['name']);
                            $totalBusinesses += count($bizList);
                        }

                        if (($cat['count'] ?? 0) > 0 && !empty($cat['url'])) {
                            $scraper->rateLimitSleep();
                            $bizList = $scraper->scrapeListingPage($cat['url']);
                            $this->saveBusinesses($bizList, $source, $location, $cat, null, $city['name']);
                            $totalBusinesses += count($bizList);
                        }
                    }
                }
            }

            Log::info('ScrapeBusinessDirectoryJob: listings done', [
                'source'     => $source->slug,
                'businesses' => $totalBusinesses,
            ]);

            // Step 4: Fetch details for all businesses that don't have email yet
            $this->scrapeDetails($scraper, $source);

        } catch (\Throwable $e) {
            Log::error('ScrapeBusinessDirectoryJob: failed', [
                'source' => $source->slug,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function saveBusinesses(
        array $bizList,
        ContentSource $source,
        array $location,
        ?array $cat,
        ?array $sub,
        ?string $city = null,
    ): void {
        foreach ($bizList as $biz) {
            if (empty($biz['url']) || empty($biz['name'])) continue;

            $urlHash = hash('sha256', $biz['url']);

            ContentBusiness::updateOrCreate(
                ['url_hash' => $urlHash],
                [
                    'source_id'        => $source->id,
                    'external_id'      => $biz['external_id'],
                    'name'             => $biz['name'],
                    'slug'             => Str::slug($biz['name']) ?: substr($urlHash, 0, 12),
                    'url'              => $biz['url'],
                    'contact_name'     => $biz['contact_name'],
                    'contact_phone'    => $biz['contact_phone'],
                    'website_redirect' => $biz['website_redirect'],
                    'address'          => $biz['address'],
                    'logo_url'         => $biz['logo_url'],
                    'is_premium'       => $biz['is_premium'],
                    'recommendations'  => $biz['recommendations'],
                    'country'          => $location['country'],
                    'country_slug'     => $location['country_slug'],
                    'continent'        => $location['continent'],
                    'city'             => $city,
                    'category'         => $cat['label'] ?? $biz['category'] ?? null,
                    'category_slug'    => $cat['slug'] ?? null,
                    'category_id'      => $cat['id'] ?? null,
                    'subcategory'      => $sub['label'] ?? null,
                    'subcategory_slug' => $sub['slug'] ?? null,
                    'subcategory_id'   => $sub['id'] ?? null,
                    'language'         => 'fr',
                    'scraped_at'       => now(),
                ]
            );
        }
    }

    /**
     * Fetch detail pages for businesses without email (pass 2).
     */
    private function scrapeDetails(BusinessDirectoryScraperService $scraper, ContentSource $source): void
    {
        $businesses = ContentBusiness::where('source_id', $source->id)
            ->where('detail_scraped', false)
            ->orderBy('is_premium', 'desc') // Premium first (more data)
            ->cursor();

        $detailCount = 0;
        $consecutiveFailures = 0;

        foreach ($businesses as $biz) {
            $scraper->rateLimitSleep();

            try {
                $detail = $scraper->scrapeBusinessDetail($biz->url);
                if (!$detail) {
                    $consecutiveFailures++;
                    if ($consecutiveFailures >= 15) {
                        Log::warning('ScrapeBusinessDirectoryJob: stopping detail scraping after 15 failures');
                        break;
                    }
                    continue;
                }

                $biz->update(array_filter([
                    'contact_email'   => $detail['contact_email'] ?? $biz->contact_email,
                    'contact_phone'   => $detail['contact_phone'] ?? $biz->contact_phone,
                    'contact_name'    => $detail['contact_name'] ?? $biz->contact_name,
                    'website'         => $detail['website'] ?? $biz->website,
                    'description'     => $detail['description'] ?? $biz->description,
                    'is_premium'      => $detail['is_premium'] ?? $biz->is_premium,
                    'views'           => $detail['views'] ?? $biz->views,
                    'recommendations' => $detail['recommendations'] ?? $biz->recommendations,
                    'images'          => $detail['images'] ?? $biz->images,
                    'opening_hours'   => $detail['opening_hours'] ?? $biz->opening_hours,
                    'logo_url'        => $detail['logo_url'] ?? $biz->logo_url,
                    'schema_type'     => $detail['schema_type'] ?? $biz->schema_type,
                    'latitude'        => $detail['latitude'] ?? $biz->latitude,
                    'longitude'       => $detail['longitude'] ?? $biz->longitude,
                    'detail_scraped'  => true,
                ], fn($v) => $v !== null));

                $detailCount++;
                $consecutiveFailures = 0;

                if ($detailCount % 50 === 0) {
                    Log::info('ScrapeBusinessDirectoryJob: detail progress', ['count' => $detailCount]);
                    gc_collect_cycles();
                }

            } catch (\Throwable $e) {
                $consecutiveFailures++;
                Log::warning('ScrapeBusinessDirectoryJob: detail failed', [
                    'business' => $biz->name,
                    'error'    => $e->getMessage(),
                ]);
                if ($consecutiveFailures >= 15) break;
            }
        }

        Log::info('ScrapeBusinessDirectoryJob: details done', ['count' => $detailCount]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeBusinessDirectoryJob: job failed permanently', [
            'sourceId' => $this->sourceId,
            'error'    => $e->getMessage(),
        ]);
    }
}
