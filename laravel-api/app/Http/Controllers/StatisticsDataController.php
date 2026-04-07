<?php

namespace App\Http\Controllers;

use App\Models\StatisticsDataPoint;
use App\Models\StatisticsIndicator;
use App\Services\Statistics\WorldBankDataService;
use App\Services\Statistics\OecdDataService;
use App\Services\Statistics\EurostatDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StatisticsDataController extends Controller
{
    // ============================================================
    // DATA POINTS — List, filter, export
    // ============================================================

    /**
     * GET /content-gen/statistics-data
     */
    public function index(Request $request): JsonResponse
    {
        $query = StatisticsDataPoint::query();

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }
        if ($country = $request->query('country_code')) {
            $query->where('country_code', $country);
        }
        if ($indicator = $request->query('indicator_code')) {
            $query->where('indicator_code', $indicator);
        }
        if ($theme = $request->query('theme')) {
            $query->whereHas('indicator', fn ($q) => $q->where('theme', $theme));
        }
        if ($year = $request->query('year')) {
            $query->where('year', $year);
        }
        if ($yearFrom = $request->query('year_from')) {
            $query->where('year', '>=', $yearFrom);
        }
        if ($yearTo = $request->query('year_to')) {
            $query->where('year', '<=', $yearTo);
        }

        $query->orderByDesc('year')->orderBy('country_name');

        return response()->json(
            $query->paginate($request->integer('per_page', 100))
        );
    }

    /**
     * GET /content-gen/statistics-data/stats
     */
    public function stats(): JsonResponse
    {
        $total = StatisticsDataPoint::count();

        $bySource = StatisticsDataPoint::selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source');

        $byTheme = StatisticsIndicator::selectRaw('theme, count(*) as indicator_count')
            ->where('is_active', true)
            ->groupBy('theme')
            ->pluck('indicator_count', 'theme');

        $countries = StatisticsDataPoint::distinct('country_code')->count('country_code');
        $indicators = StatisticsIndicator::where('is_active', true)->count();

        $yearRange = StatisticsDataPoint::selectRaw('min(year) as min_year, max(year) as max_year')->first();

        $lastFetch = StatisticsDataPoint::max('fetched_at');

        return response()->json([
            'total_data_points' => $total,
            'by_source'         => $bySource,
            'by_theme'          => $byTheme,
            'countries_covered' => $countries,
            'indicators_count'  => $indicators,
            'year_min'          => $yearRange->min_year ?? null,
            'year_max'          => $yearRange->max_year ?? null,
            'last_fetched_at'   => $lastFetch,
        ]);
    }

    /**
     * GET /content-gen/statistics-data/indicators
     */
    public function indicators(): JsonResponse
    {
        $indicators = StatisticsIndicator::withCount('dataPoints')
            ->orderBy('source')
            ->orderBy('theme')
            ->get();

        return response()->json($indicators);
    }

    /**
     * GET /content-gen/statistics-data/country/{countryCode}
     * All data points for a specific country.
     */
    public function country(string $countryCode): JsonResponse
    {
        $data = StatisticsDataPoint::where('country_code', strtoupper($countryCode))
            ->orderBy('indicator_code')
            ->orderByDesc('year')
            ->get()
            ->groupBy('indicator_code')
            ->map(function ($points, $code) {
                return [
                    'indicator_code' => $code,
                    'indicator_name' => $points->first()->indicator_name,
                    'source'         => $points->first()->source,
                    'unit'           => $points->first()->unit,
                    'data'           => $points->map(fn ($p) => [
                        'year'  => $p->year,
                        'value' => $p->value,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'country_code' => strtoupper($countryCode),
            'indicators'   => $data,
        ]);
    }

    // ============================================================
    // FETCH — Trigger data collection from APIs
    // ============================================================

    /**
     * POST /content-gen/statistics-data/fetch/world-bank
     */
    public function fetchWorldBank(Request $request, WorldBankDataService $service): JsonResponse
    {
        $theme = $request->input('theme');
        $startYear = $request->integer('start_year', 2015);
        $endYear = $request->integer('end_year', 2024);

        if ($theme) {
            $results = $service->fetchByTheme($theme, $startYear, $endYear);
        } else {
            $results = $service->fetchAll($startYear, $endYear);
        }

        $totalStored = array_sum($results);

        return response()->json([
            'source'       => 'world_bank',
            'indicators'   => count($results),
            'total_stored'  => $totalStored,
            'details'      => $results,
        ]);
    }

    /**
     * POST /content-gen/statistics-data/fetch/oecd
     */
    public function fetchOecd(Request $request, OecdDataService $service): JsonResponse
    {
        $startYear = $request->integer('start_year', 2015);
        $endYear = $request->integer('end_year', 2024);

        $results = $service->fetchAll($startYear, $endYear);
        $totalStored = array_sum($results);

        return response()->json([
            'source'       => 'oecd',
            'indicators'   => count($results),
            'total_stored'  => $totalStored,
            'details'      => $results,
        ]);
    }

    /**
     * POST /content-gen/statistics-data/fetch/eurostat
     */
    public function fetchEurostat(Request $request, EurostatDataService $service): JsonResponse
    {
        $startYear = $request->integer('start_year', 2015);
        $endYear = $request->integer('end_year', 2024);

        $results = $service->fetchAll($startYear, $endYear);
        $totalStored = array_sum($results);

        return response()->json([
            'source'       => 'eurostat',
            'indicators'   => count($results),
            'total_stored'  => $totalStored,
            'details'      => $results,
        ]);
    }

    /**
     * POST /content-gen/statistics-data/fetch/all
     * Fetch from all 3 sources.
     */
    public function fetchAll(Request $request): JsonResponse
    {
        $startYear = $request->integer('start_year', 2015);
        $endYear = $request->integer('end_year', 2024);
        $results = [];

        // World Bank
        $wb = app(WorldBankDataService::class);
        $results['world_bank'] = $wb->fetchAll($startYear, $endYear);

        // OECD
        $oecd = app(OecdDataService::class);
        $results['oecd'] = $oecd->fetchAll($startYear, $endYear);

        // Eurostat
        $eurostat = app(EurostatDataService::class);
        $results['eurostat'] = $eurostat->fetchAll($startYear, $endYear);

        $total = 0;
        foreach ($results as $source => $indicators) {
            $total += array_sum($indicators);
        }

        return response()->json([
            'total_stored' => $total,
            'details'      => $results,
        ]);
    }

    /**
     * GET /content-gen/statistics-data/coverage
     * Coverage matrix: which indicators × countries have data.
     */
    public function coverage(): JsonResponse
    {
        $data = StatisticsDataPoint::selectRaw('
                country_code, country_name, source,
                count(distinct indicator_code) as indicators,
                count(*) as data_points,
                max(year) as latest_year
            ')
            ->groupBy('country_code', 'country_name', 'source')
            ->orderBy('country_name')
            ->get()
            ->groupBy('country_code')
            ->map(function ($rows, $code) {
                $first = $rows->first();
                return [
                    'country_code' => $code,
                    'country_name' => $first->country_name,
                    'sources'      => $rows->pluck('data_points', 'source')->toArray(),
                    'total_points' => $rows->sum('data_points'),
                    'indicators'   => $rows->sum('indicators'),
                    'latest_year'  => $rows->max('latest_year'),
                ];
            })
            ->sortByDesc('total_points')
            ->values();

        return response()->json($data);
    }

    /**
     * GET /content-gen/statistics-data/available-indicators
     * List all available indicators from the 3 sources (not yet fetched).
     */
    public function availableIndicators(): JsonResponse
    {
        $available = [];

        foreach (WorldBankDataService::INDICATORS as $code => $config) {
            $available[] = array_merge($config, ['code' => $code, 'source' => 'world_bank']);
        }
        foreach (OecdDataService::INDICATORS as $code => $config) {
            $available[] = ['code' => $code, 'source' => 'oecd', 'name' => $config['name'], 'theme' => $config['theme'], 'unit' => $config['unit']];
        }
        foreach (EurostatDataService::INDICATORS as $code => $config) {
            $available[] = ['code' => $code, 'source' => 'eurostat', 'name' => $config['name'], 'theme' => $config['theme'], 'unit' => $config['unit']];
        }

        return response()->json($available);
    }
}
