<?php

namespace App\Jobs;

use App\Services\Scraping\FeedDirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Option D2 — Scraper FeedSpot pour découvrir des blogs thématiques.
 * Ultra prudent : max 50 req/run, 1 run/jour, delays 15-30s.
 */
class ScrapeFeedSpotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800; // 30 min (50 req × ~25s = 21 min théorique)
    public int $tries = 1;

    /**
     * Top catégories FeedSpot ciblées expat/voyage/lifestyle.
     * URL format : https://blog.feedspot.com/<slug>/
     */
    private const CATEGORIES = [
        'expat_fr'           => 'https://blog.feedspot.com/expat_blogs/',
        'travel_fr'          => 'https://blog.feedspot.com/french_travel_blogs/',
        'french_expat'       => 'https://blog.feedspot.com/french_expat_blogs/',
        'nomad_fr'           => 'https://blog.feedspot.com/digital_nomad_blogs/',
        'travel_blogs'       => 'https://blog.feedspot.com/travel_blogs/',
        'expat_women'        => 'https://blog.feedspot.com/expat_women_blogs/',
        'lifestyle_expat'    => 'https://blog.feedspot.com/expat_lifestyle_blogs/',
        'france_blogs'       => 'https://blog.feedspot.com/france_blogs/',
        'blogs_paris'        => 'https://blog.feedspot.com/paris_blogs/',
        'travel_podcast'     => 'https://blog.feedspot.com/travel_podcasts/',
    ];

    public function __construct()
    {
        $this->onQueue('scraper');
    }

    public function handle(FeedDirectoryScraperService $scraper): void
    {
        Log::info('ScrapeFeedSpotJob: start');
        $result = $scraper->scrapeCategories('feedspot', self::CATEGORIES);
        Log::info('ScrapeFeedSpotJob: done', $result);
    }
}
