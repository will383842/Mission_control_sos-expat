<?php

namespace App\Services\Statistics;

use App\Models\StatisticsDataPoint;
use App\Models\StatisticsIndicator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Eurostat JSON API — free, no auth.
 * Docs: https://wikis.ec.europa.eu/display/EUROSTATHELP/Transition+-+from+JSON+web+service+to+API+Statistics
 * Base: https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/
 */
class EurostatDataService
{
    private const BASE_URL = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data';

    // Eurostat datasets relevant to expat/migration
    public const INDICATORS = [
        // Immigration
        'MIGR_IMM1CTZ' => [
            'name'    => 'Immigration by citizenship',
            'theme'   => 'expatries',
            'unit'    => 'persons',
            'params'  => ['citizen' => 'TOTAL', 'age' => 'TOTAL', 'sex' => 'T'],
        ],
        // Emigration
        'MIGR_EMI1CTZ' => [
            'name'    => 'Emigration by citizenship',
            'theme'   => 'expatries',
            'unit'    => 'persons',
            'params'  => ['citizen' => 'TOTAL', 'age' => 'TOTAL', 'sex' => 'T'],
        ],
        // First residence permits
        'MIGR_RESFIRST' => [
            'name'    => 'First permits by reason',
            'theme'   => 'expatries',
            'unit'    => 'persons',
            'params'  => ['citizen' => 'TOTAL', 'reason' => 'TOTAL'],
        ],
        // Asylum applications
        'MIGR_ASYAPPCTZA' => [
            'name'    => 'Asylum applicants by citizenship',
            'theme'   => 'expatries',
            'unit'    => 'persons',
            'params'  => ['citizen' => 'TOTAL', 'age' => 'TOTAL', 'sex' => 'T', 'asyl_app' => 'TOTAL'],
        ],
        // Foreign-born population (% of total)
        'MIGR_POP3CTB' => [
            'name'    => 'Population by country of birth',
            'theme'   => 'expatries',
            'unit'    => 'persons',
            'params'  => ['c_birth' => 'FOR', 'age' => 'TOTAL', 'sex' => 'T'],
        ],
        // Tourism nights spent
        'TOUR_OCC_NIM' => [
            'name'    => 'Nights spent at tourist accommodation',
            'theme'   => 'voyageurs',
            'unit'    => 'nights',
            'params'  => ['c_resid' => 'TOTAL', 'nace_r2' => 'I551-I553', 'unit' => 'NR'],
        ],
        // Students from abroad
        'EDUC_UOE_MOBS02' => [
            'name'    => 'Mobile students from abroad',
            'theme'   => 'etudiants',
            'unit'    => 'persons',
            'params'  => ['isced11' => 'TOTAL', 'sex' => 'T'],
        ],
    ];

    /**
     * Fetch one Eurostat dataset.
     */
    public function fetchIndicator(string $datasetCode, int $startYear = 2015, int $endYear = 2024): int
    {
        $config = self::INDICATORS[$datasetCode] ?? null;
        if (!$config) {
            Log::warning("Eurostat: unknown dataset {$datasetCode}");
            return 0;
        }

        $indicator = StatisticsIndicator::updateOrCreate(
            ['code' => $datasetCode, 'source' => 'eurostat'],
            [
                'name'         => $config['name'],
                'theme'        => $config['theme'],
                'unit'         => $config['unit'],
                'api_endpoint' => self::BASE_URL . '/' . $datasetCode,
                'is_active'    => true,
            ]
        );

        // Build query params
        $params = array_merge($config['params'] ?? [], [
            'sinceTimePeriod' => (string) $startYear,
            'untilTimePeriod' => (string) $endYear,
            'lang'            => 'en',
        ]);

        $url = self::BASE_URL . '/' . strtolower($datasetCode) . '?' . http_build_query($params);

        try {
            $response = Http::timeout(60)->get($url);

            if (!$response->successful()) {
                if ($response->status() === 404) {
                    Log::info("Eurostat: dataset {$datasetCode} not found, skipping");
                    return 0;
                }
                Log::warning("Eurostat API error", ['dataset' => $datasetCode, 'status' => $response->status()]);
                return 0;
            }

            $json = $response->json();
            $stored = $this->parseEurostatJson($json, $indicator, $datasetCode, $config, $url);

            Log::info("Eurostat: fetched {$datasetCode}", ['stored' => $stored]);
            return $stored;

        } catch (\Throwable $e) {
            Log::error("Eurostat fetch failed", [
                'dataset' => $datasetCode,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Fetch all configured Eurostat datasets.
     */
    public function fetchAll(int $startYear = 2015, int $endYear = 2024): array
    {
        $results = [];
        foreach (array_keys(self::INDICATORS) as $code) {
            $results[$code] = $this->fetchIndicator($code, $startYear, $endYear);
            usleep(1_000_000); // 1s between requests
        }
        return $results;
    }

    /**
     * Parse Eurostat JSON-stat response.
     */
    private function parseEurostatJson(array $json, StatisticsIndicator $indicator, string $datasetCode, array $config, string $url): int
    {
        $stored = 0;

        // Eurostat JSON-stat format
        $geoLabels = $json['dimension']['geo']['category']['label'] ?? [];
        $geoIndex = $json['dimension']['geo']['category']['index'] ?? [];
        $timeLabels = $json['dimension']['time']['category']['label'] ?? [];
        $timeIndex = $json['dimension']['time']['category']['index'] ?? [];
        $values = $json['value'] ?? [];
        $sizes = $json['size'] ?? [];

        if (empty($geoLabels) || empty($timeLabels) || empty($values)) {
            Log::info("Eurostat: no data for {$datasetCode}");
            return 0;
        }

        // Calculate the stride for the time dimension
        // Eurostat flattens multi-dimensional data; we need to compute offsets
        $dimensionIds = array_keys($json['dimension'] ?? []);
        $geoPos = array_search('geo', $dimensionIds);
        $timePos = array_search('time', $dimensionIds);

        if ($geoPos === false || $timePos === false) return 0;

        // Calculate strides for each dimension
        $strides = [];
        $stride = 1;
        for ($i = count($sizes) - 1; $i >= 0; $i--) {
            $strides[$i] = $stride;
            $stride *= $sizes[$i];
        }

        foreach ($geoIndex as $geoCode => $geoIdx) {
            $countryName = $geoLabels[$geoCode] ?? $geoCode;

            // Skip aggregate regions
            if (strlen($geoCode) > 2) continue;

            foreach ($timeIndex as $timeLabel => $timeIdx) {
                $year = (int) $timeLabel;
                if ($year < 2000) continue;

                // Calculate flat index
                $flatIndex = ($geoIdx * $strides[$geoPos]) + ($timeIdx * $strides[$timePos]);

                // For other dimensions, use index 0 (we filtered to TOTAL in params)
                // This is simplified — works when other dims have single values
                $value = $values[$flatIndex] ?? null;
                if ($value === null) continue;

                StatisticsDataPoint::updateOrCreate(
                    [
                        'indicator_id' => $indicator->id,
                        'country_code' => strtoupper($geoCode),
                        'year'         => $year,
                    ],
                    [
                        'indicator_code' => $datasetCode,
                        'indicator_name' => $config['name'],
                        'country_name'   => $countryName,
                        'value'          => $value,
                        'unit'           => $config['unit'],
                        'source'         => 'eurostat',
                        'source_dataset' => $datasetCode,
                        'source_url'     => $url,
                        'fetched_at'     => now(),
                    ]
                );
                $stored++;
            }
        }

        return $stored;
    }
}
