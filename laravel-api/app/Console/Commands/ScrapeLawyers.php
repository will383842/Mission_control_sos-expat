<?php

namespace App\Console\Commands;

use App\Models\Lawyer;
use App\Models\LawyerDirectorySource;
use App\Services\LawyerDirectoryScraperService;
use Illuminate\Console\Command;

class ScrapeLawyers extends Command
{
    protected $signature = 'lawyers:scrape {source? : Source slug (legal500, abogados-ar, rechtsanwalt, website-enrichment, all)}';
    protected $description = 'Scrape lawyer directories worldwide';

    public function handle(LawyerDirectoryScraperService $scraper): int
    {
        $source = $this->argument('source') ?? 'all';
        $this->info("Starting lawyer scraping: {$source}");

        if ($source === 'all' || $source === 'legal500') {
            $this->scrapeLegal500($scraper);
        }
        if ($source === 'all' || $source === 'abogados-ar') {
            $this->scrapeAbogadosAr($scraper);
        }
        if ($source === 'all' || $source === 'website-enrichment') {
            $this->enrichWebsites($scraper);
        }

        $total = Lawyer::count();
        $countries = Lawyer::distinct('country')->count('country');
        $this->info("DONE. Total: {$total} lawyers in {$countries} countries.");

        return 0;
    }

    private function scrapeLegal500(LawyerDirectoryScraperService $scraper): void
    {
        $src = LawyerDirectorySource::where('slug', 'legal500')->first();
        if ($src) $src->update(['status' => 'scraping']);

        $this->info('Legal500: discovering countries...');
        $countries = $scraper->legal500DiscoverCountries();
        $this->info("Legal500: " . count($countries) . " countries found");

        $totalSaved = 0;

        foreach ($countries as $i => $country) {
            $scraper->rateLimitSleep();
            $firms = $scraper->legal500ScrapeFirms($country['slug'], $country['name'], $country['country_code']);

            if (empty($firms)) continue;

            $countrySaved = 0;
            foreach ($firms as $firm) {
                $scraper->rateLimitSleep('www.legal500.com');
                try {
                    $lawyers = $scraper->legal500ScrapeFirmContact($firm);
                    foreach ($lawyers as $l) {
                        if ($scraper->saveLawyer($l, 'legal500')) {
                            $totalSaved++;
                            $countrySaved++;
                        }
                    }
                } catch (\Throwable $e) {
                    // Skip failed firms
                }
            }

            if ($countrySaved > 0) {
                $this->line("  {$country['name']}: +{$countrySaved} lawyers ({$totalSaved} total)");
            }
        }

        if ($src) {
            $src->update(['status' => 'completed', 'last_scraped_at' => now()]);
            $src->updateStats();
        }

        $this->info("Legal500: {$totalSaved} lawyers saved");
    }

    private function scrapeAbogadosAr(LawyerDirectoryScraperService $scraper): void
    {
        $src = LawyerDirectorySource::where('slug', 'abogados-ar')->first();
        if ($src) $src->update(['status' => 'scraping']);

        $this->info('Abogados.com.ar: scraping...');
        $lawyers = $scraper->abogadosArScrape();
        $saved = 0;
        foreach ($lawyers as $l) {
            if ($scraper->saveLawyer($l, 'abogados-ar')) $saved++;
        }

        if ($src) {
            $src->update(['status' => 'completed', 'last_scraped_at' => now()]);
            $src->updateStats();
        }

        $this->info("Abogados.com.ar: {$saved} lawyers saved");
    }

    private function enrichWebsites(LawyerDirectoryScraperService $scraper): void
    {
        $src = LawyerDirectorySource::where('slug', 'website-enrichment')->first();
        if ($src) $src->update(['status' => 'scraping']);

        $this->info('Website enrichment: visiting firm websites...');
        $totalNew = $scraper->enrichExistingLawyers();

        if ($src) {
            $src->update(['status' => 'completed', 'last_scraped_at' => now()]);
            $src->updateStats();
        }

        $this->info("Website enrichment: {$totalNew} new lawyers from firm websites");
    }
}
