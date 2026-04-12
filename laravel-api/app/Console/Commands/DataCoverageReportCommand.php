<?php

namespace App\Console\Commands;

use App\Models\CountryFact;
use App\Models\CountryGeo;
use App\Models\StatisticsDataPoint;
use App\Models\StatisticsIndicator;
use App\Services\Statistics\WorldBankDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DataCoverageReportCommand extends Command
{
    protected $signature = 'data:coverage-report {--json : Output as JSON}';
    protected $description = 'Show data coverage report for Knowledge Base (statistics, country facts, geo)';

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   SOS-EXPAT DATA COVERAGE REPORT                ║');
        $this->info('║   ' . now()->format('Y-m-d H:i') . ' UTC                          ║');
        $this->info('╚══════════════════════════════════════════════════╝');

        $report = [];

        // ── 1. STATISTICS DATA POINTS ──
        $this->info('');
        $this->info('── Statistics Data Points ──');

        $totalPoints = StatisticsDataPoint::count();
        $countryCount = StatisticsDataPoint::distinct('country_code')->count('country_code');
        $indicatorCount = StatisticsDataPoint::distinct('indicator_code')->count('indicator_code');

        $bySource = StatisticsDataPoint::select('source', DB::raw('COUNT(*) as count'), DB::raw('COUNT(DISTINCT country_code) as countries'))
            ->groupBy('source')
            ->get();

        $this->info("  Total data points : {$totalPoints}");
        $this->info("  Countries covered : {$countryCount}/197");
        $this->info("  Unique indicators : {$indicatorCount}");

        $report['statistics'] = [
            'total_points' => $totalPoints,
            'countries' => $countryCount,
            'indicators' => $indicatorCount,
        ];

        foreach ($bySource as $source) {
            $status = $source->countries >= 150 ? '✓' : ($source->countries >= 30 ? '~' : '✗');
            $this->info("  {$status} {$source->source}: {$source->count} points, {$source->countries} countries");
        }

        // Latest fetch date
        $latestFetch = StatisticsDataPoint::max('fetched_at');
        if ($latestFetch) {
            $daysAgo = now()->diffInDays($latestFetch);
            $freshness = $daysAgo <= 30 ? 'FRESH' : ($daysAgo <= 90 ? 'OK' : 'STALE');
            $this->info("  Last fetch: {$latestFetch} ({$daysAgo} days ago) [{$freshness}]");
        } else {
            $this->warn('  ⚠ NO DATA FETCHED YET — run: php artisan statistics:fetch-all');
        }

        // ── 2. INDICATOR DETAIL ──
        $this->info('');
        $this->info('── Indicator Coverage ──');

        $indicators = StatisticsIndicator::where('is_active', true)->get();
        $indicatorRows = [];

        foreach ($indicators as $ind) {
            $dpCount = StatisticsDataPoint::where('indicator_id', $ind->id)->count();
            $dpCountries = StatisticsDataPoint::where('indicator_id', $ind->id)
                ->distinct('country_code')
                ->count('country_code');
            $latestYear = StatisticsDataPoint::where('indicator_id', $ind->id)->max('year');

            $status = $dpCountries >= 150 ? '✓' : ($dpCountries >= 30 ? '~' : '✗');
            $indicatorRows[] = [$status, $ind->code, $ind->theme, "{$dpCountries} pays", $latestYear ?? '-'];
        }

        if (!empty($indicatorRows)) {
            $this->table(['', 'Code', 'Theme', 'Countries', 'Latest Year'], $indicatorRows);
        }

        // ── 3. COUNTRY FACTS ──
        $this->info('── Country Facts ──');

        $totalFacts = CountryFact::count();
        $verifiedFacts = CountryFact::where('verification_status', 'verified')->count();
        $unverifiedFacts = CountryFact::where('verification_status', 'unverified')->count();
        $outdatedFacts = CountryFact::where('verification_status', 'outdated')->count();

        $this->info("  Total entries   : {$totalFacts}/197");
        $this->info("  Verified        : {$verifiedFacts}");
        $this->info("  Unverified      : {$unverifiedFacts}");
        $this->info("  Outdated        : {$outdatedFacts}");
        $this->info("  Missing         : " . (197 - $totalFacts));

        $report['country_facts'] = [
            'total' => $totalFacts,
            'verified' => $verifiedFacts,
            'unverified' => $unverifiedFacts,
            'missing' => 197 - $totalFacts,
        ];

        // Completeness distribution
        if ($totalFacts > 0) {
            $avgCompleteness = CountryFact::all()->avg(fn ($f) => $f->completeness);
            $this->info("  Avg completeness: " . round($avgCompleteness) . "%");
        }

        // ── 4. GEO DATA ──
        $this->info('');
        $this->info('── Geographic Data ──');

        $totalGeo = CountryGeo::count();
        $this->info("  Countries in geo table: {$totalGeo}");

        $report['geo'] = ['total' => $totalGeo];

        // ── 5. WORLD BANK INDICATORS CONFIGURED ──
        $this->info('');
        $this->info('── Configured Data Sources ──');

        $wbIndicators = count(WorldBankDataService::INDICATORS);
        $this->info("  World Bank indicators : {$wbIndicators}");
        $this->info("  Themes covered       : " . implode(', ', array_unique(array_column(WorldBankDataService::INDICATORS, 'theme'))));

        // ── 6. ALERTS ──
        $this->info('');
        $this->info('── Alerts ──');

        $alerts = [];

        if ($totalPoints === 0) {
            $alerts[] = '🔴 CRITICAL: No statistics data — run php artisan statistics:fetch-all';
        }
        if ($totalFacts === 0) {
            $alerts[] = '🟡 WARNING: No country facts — run php artisan country:enrich-batch --tier=1';
        }
        if ($latestFetch && now()->diffInDays($latestFetch) > 60) {
            $alerts[] = '🟡 WARNING: Statistics data older than 60 days';
        }
        if ($verifiedFacts < 24) {
            $alerts[] = "🟡 WARNING: Only {$verifiedFacts}/24 Tier 1 countries verified";
        }

        // Countries with no data at all
        $geoCountries = CountryGeo::pluck('country_code');
        $statsCountries = StatisticsDataPoint::distinct('country_code')->pluck('country_code');
        $factsCountries = CountryFact::pluck('country_code');

        $noDataCountries = $geoCountries->diff($statsCountries)->diff($factsCountries);
        if ($noDataCountries->count() > 0 && $totalPoints > 0) {
            $sample = $noDataCountries->take(10)->implode(', ');
            $alerts[] = "🟡 WARNING: {$noDataCountries->count()} countries with NO data at all (e.g., {$sample})";
        }

        if (empty($alerts)) {
            $this->info('  ✓ No alerts — all data sources healthy');
        } else {
            foreach ($alerts as $alert) {
                $this->warn("  {$alert}");
            }
        }

        $report['alerts'] = $alerts;

        $this->info('');

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }
}
