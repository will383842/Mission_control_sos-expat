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
 * Option D2 — Scraper AllTop pour découvrir des blogs par catégorie.
 * Ultra prudent : max 50 req/run, 1 run/jour, delays 15-30s.
 */
class ScrapeAllTopJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    /**
     * Top catégories AllTop ciblées.
     * URL format : https://alltop.com/<slug>
     */
    private const CATEGORIES = [
        'travel'         => 'https://alltop.com/travel',
        'blog'           => 'https://alltop.com/blog',
        'france'         => 'https://alltop.com/france',
        'paris'          => 'https://alltop.com/paris',
        'culture'        => 'https://alltop.com/culture',
        'food'           => 'https://alltop.com/food',
        'lifestyle'      => 'https://alltop.com/lifestyle',
        'technology'     => 'https://alltop.com/technology',
        'startups'       => 'https://alltop.com/startups',
        'podcasts'       => 'https://alltop.com/podcasts',
    ];

    public function __construct()
    {
        $this->onQueue('scraper');
    }

    public function handle(FeedDirectoryScraperService $scraper): void
    {
        Log::info('ScrapeAllTopJob: start');
        $result = $scraper->scrapeCategories('alltop', self::CATEGORIES);
        Log::info('ScrapeAllTopJob: done', $result);
    }
}
