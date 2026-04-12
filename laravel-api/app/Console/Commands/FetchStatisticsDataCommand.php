<?php

namespace App\Console\Commands;

use App\Services\Statistics\EurostatDataService;
use App\Services\Statistics\OecdDataService;
use App\Services\Statistics\WorldBankDataService;
use Illuminate\Console\Command;

class FetchStatisticsDataCommand extends Command
{
    protected $signature = 'statistics:fetch-all
                            {--source=all : Source to fetch (all, world-bank, oecd, eurostat)}
                            {--start-year=2018 : Start year for data}
                            {--end-year= : End year (defaults to current year)}';

    protected $description = 'Fetch statistics data from World Bank, OECD, and Eurostat APIs';

    public function handle(): int
    {
        $source = $this->option('source');
        $startYear = (int) $this->option('start-year');
        $endYear = (int) ($this->option('end-year') ?: date('Y'));

        $this->info("Fetching statistics data ({$startYear}-{$endYear})...");
        $totalPoints = 0;

        // World Bank (free, no auth, 197 countries)
        if (in_array($source, ['all', 'world-bank'])) {
            $this->info('');
            $this->info('── World Bank ──');
            try {
                $result = app(WorldBankDataService::class)->fetchAll($startYear, $endYear);
                $count = is_array($result) ? array_sum($result) : 0;
                $totalPoints += $count;
                $this->info("  World Bank: {$count} data points");
            } catch (\Throwable $e) {
                $this->error("  World Bank FAILED: {$e->getMessage()}");
            }
        }

        // OECD (free, 38 countries)
        if (in_array($source, ['all', 'oecd'])) {
            $this->info('');
            $this->info('── OECD ──');
            try {
                $result = app(OecdDataService::class)->fetchAll($startYear, $endYear);
                $count = is_array($result) ? array_sum($result) : 0;
                $totalPoints += $count;
                $this->info("  OECD: {$count} data points");
            } catch (\Throwable $e) {
                $this->error("  OECD FAILED: {$e->getMessage()}");
            }
        }

        // Eurostat (free, 27 EU countries)
        if (in_array($source, ['all', 'eurostat'])) {
            $this->info('');
            $this->info('── Eurostat ──');
            try {
                $result = app(EurostatDataService::class)->fetchAll($startYear, $endYear);
                $count = is_array($result) ? array_sum($result) : 0;
                $totalPoints += $count;
                $this->info("  Eurostat: {$count} data points");
            } catch (\Throwable $e) {
                $this->error("  Eurostat FAILED: {$e->getMessage()}");
            }
        }

        $this->info('');
        $this->info("Total: {$totalPoints} data points fetched.");

        return 0;
    }
}
