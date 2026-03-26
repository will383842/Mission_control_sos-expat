<?php

namespace App\Jobs;

use App\Models\Lawyer;
use App\Models\LawyerDirectorySource;
use App\Services\LawyerDirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeLawyerDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 28800; // 8h — many countries to scrape
    public int $tries = 1;

    public function __construct(
        private string $sourceSlug,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-lawyers-' . $this->sourceSlug))
                ->releaseAfter(28800)
                ->expireAfter(28800),
        ];
    }

    public function handle(LawyerDirectoryScraperService $scraper): void
    {
        $source = LawyerDirectorySource::where('slug', $this->sourceSlug)->first();
        if (!$source) {
            Log::error('ScrapeLawyerDirectoryJob: source not found', ['slug' => $this->sourceSlug]);
            return;
        }

        $source->update(['status' => 'scraping']);
        Log::info('ScrapeLawyerDirectoryJob: starting', ['source' => $this->sourceSlug]);

        $totalSaved = 0;

        try {
            $totalSaved = match ($this->sourceSlug) {
                'legal500'             => $this->scrapeLegal500($scraper),
                'lawyer-com'           => $this->scrapeLawyerCom($scraper),
                'abogados-ar'          => $this->scrapeAbogadosAr($scraper),
                'rechtsanwalt'         => $this->scrapeRechtsanwalt($scraper),
                'website-enrichment'   => $scraper->enrichExistingLawyers(),
                default                => 0,
            };

            $source->update([
                'status'         => 'completed',
                'last_scraped_at' => now(),
            ]);
            $source->updateStats();

            Log::info('ScrapeLawyerDirectoryJob: completed', [
                'source' => $this->sourceSlug,
                'saved'  => $totalSaved,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeLawyerDirectoryJob: failed', [
                'source' => $this->sourceSlug,
                'error'  => $e->getMessage(),
                'saved'  => $totalSaved,
            ]);
            $source->update(['status' => 'failed']);
            $source->updateStats();
        }
    }

    private function scrapeLegal500(LawyerDirectoryScraperService $scraper): int
    {
        $countries = $scraper->legal500DiscoverCountries();
        $totalSaved = 0;

        foreach ($countries as $country) {
            $scraper->rateLimitSleep();

            Log::info('Legal500: scraping country', ['country' => $country['name']]);

            $firms = $scraper->legal500ScrapeFirms($country['slug'], $country['name'], $country['country_code']);

            foreach ($firms as $firm) {
                $scraper->rateLimitSleep();

                try {
                    $lawyers = $scraper->legal500ScrapeFirmContact($firm);
                    foreach ($lawyers as $lawyerData) {
                        if ($scraper->saveLawyer($lawyerData, 'legal500')) {
                            $totalSaved++;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Legal500: firm scrape failed', [
                        'firm' => $firm['firm_name'], 'error' => $e->getMessage(),
                    ]);
                }

                if ($totalSaved > 0 && $totalSaved % 100 === 0) {
                    Log::info('Legal500: progress', ['saved' => $totalSaved, 'country' => $country['name']]);
                    gc_collect_cycles();
                }
            }
        }

        return $totalSaved;
    }

    private function scrapeLawyerCom(LawyerDirectoryScraperService $scraper): int
    {
        $states = [
            'alabama','alaska','arizona','arkansas','california','colorado','connecticut',
            'delaware','florida','georgia','hawaii','idaho','illinois','indiana','iowa',
            'kansas','kentucky','louisiana','maine','maryland','massachusetts','michigan',
            'minnesota','mississippi','missouri','montana','nebraska','nevada',
            'new-hampshire','new-jersey','new-mexico','new-york','north-carolina',
            'north-dakota','ohio','oklahoma','oregon','pennsylvania','rhode-island',
            'south-carolina','south-dakota','tennessee','texas','utah','vermont',
            'virginia','washington','west-virginia','wisconsin','wyoming',
        ];

        $totalSaved = 0;
        foreach ($states as $state) {
            $scraper->rateLimitSleep();
            Log::info('LawyerCom: scraping state', ['state' => $state]);

            try {
                $lawyers = $scraper->lawyerComScrapeByState($state);
                foreach ($lawyers as $lawyerData) {
                    if ($scraper->saveLawyer($lawyerData, 'lawyer-com')) {
                        $totalSaved++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('LawyerCom: state failed', ['state' => $state, 'error' => $e->getMessage()]);
            }
        }

        return $totalSaved;
    }

    private function scrapeAbogadosAr(LawyerDirectoryScraperService $scraper): int
    {
        $totalSaved = 0;
        try {
            $lawyers = $scraper->abogadosArScrape();
            foreach ($lawyers as $lawyerData) {
                if ($scraper->saveLawyer($lawyerData, 'abogados-ar')) {
                    $totalSaved++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AbogadosAR: failed', ['error' => $e->getMessage()]);
        }
        return $totalSaved;
    }

    private function scrapeRechtsanwalt(LawyerDirectoryScraperService $scraper): int
    {
        $totalSaved = 0;
        try {
            $lawyers = $scraper->rechtsanwaltComScrape();
            foreach ($lawyers as $lawyerData) {
                if ($scraper->saveLawyer($lawyerData, 'rechtsanwalt')) {
                    $totalSaved++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Rechtsanwalt: failed', ['error' => $e->getMessage()]);
        }
        return $totalSaved;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeLawyerDirectoryJob: job failed permanently', [
            'source' => $this->sourceSlug,
            'error'  => $e->getMessage(),
        ]);

        LawyerDirectorySource::where('slug', $this->sourceSlug)
            ->update(['status' => 'failed']);
    }
}
