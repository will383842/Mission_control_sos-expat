<?php

namespace App\Services\Statistics;

use App\Models\StatisticsDataPoint;
use App\Models\StatisticsIndicator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OECD SDMX REST API — free, no auth.
 * Docs: https://data.oecd.org/api/sdmx-json-documentation/
 * New API: https://sdmx.oecd.org/public/rest
 */
class OecdDataService
{
    private const BASE_URL = 'https://sdmx.oecd.org/public/rest/data';

    // OECD dataflows relevant to expat/migration
    public const INDICATORS = [
        // Migration
        'MIG' => [
            'dataflow' => 'OECD.ELS.IMD,DSD_MIG@DF_MIG,1.0',
            'name'     => 'International migration inflows',
            'theme'    => 'expatries',
            'unit'     => 'persons',
        ],
        // Permanent immigrant inflows
        'MIG_PERM' => [
            'dataflow' => 'OECD.ELS.IMD,DSD_MIG@DF_PERMANENT,1.0',
            'name'     => 'Permanent immigrant inflows by category',
            'theme'    => 'expatries',
            'unit'     => 'persons',
        ],
        // Foreign-born population
        'MIG_FOREIGNBORN' => [
            'dataflow' => 'OECD.ELS.IMD,DSD_MIG@DF_FOREIGN_BORN,1.0',
            'name'     => 'Foreign-born population',
            'theme'    => 'expatries',
            'unit'     => 'persons',
        ],
        // International students
        'EDU_ENRL_MOBILE' => [
            'dataflow' => 'OECD.EDU.IMEP,DSD_EDU_ENRL_MOBILE@DF_EDU_ENRL_MOBILE,1.0',
            'name'     => 'International student mobility',
            'theme'    => 'etudiants',
            'unit'     => 'persons',
        ],
        // FDI
        'FDI_FLOW' => [
            'dataflow' => 'OECD.DAF.INV,DSD_FDI@DF_FDI_FLOW_PARTNER,1.0',
            'name'     => 'FDI flows by partner country',
            'theme'    => 'investisseurs',
            'unit'     => 'usd',
            'multiply' => 1_000_000, // OECD reports in millions
        ],
    ];

    /**
     * Fetch data from OECD SDMX API for a specific dataflow.
     */
    public function fetchIndicator(string $indicatorKey, int $startYear = 2015, int $endYear = 2024): int
    {
        $config = self::INDICATORS[$indicatorKey] ?? null;
        if (!$config) {
            Log::warning("OECD: unknown indicator {$indicatorKey}");
            return 0;
        }

        $indicator = StatisticsIndicator::updateOrCreate(
            ['code' => $indicatorKey, 'source' => 'oecd'],
            [
                'name'         => $config['name'],
                'theme'        => $config['theme'],
                'unit'         => $config['unit'],
                'api_endpoint' => self::BASE_URL . '/' . $config['dataflow'],
                'is_active'    => true,
            ]
        );

        // OECD SDMX format: dataflow/key?startPeriod=YYYY&endPeriod=YYYY
        $url = self::BASE_URL . '/' . $config['dataflow']
            . "/all?startPeriod={$startYear}&endPeriod={$endYear}&dimensionAtObservation=AllDimensions";

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Accept' => 'application/vnd.sdmx.data+json;version=2.0.0'])
                ->get($url);

            if (!$response->successful()) {
                // OECD returns 404 for some dataflows that don't exist yet
                if ($response->status() === 404) {
                    Log::info("OECD: dataflow not found for {$indicatorKey}, skipping");
                    return 0;
                }
                Log::warning("OECD API error", ['indicator' => $indicatorKey, 'status' => $response->status()]);
                return 0;
            }

            $json = $response->json();
            $stored = $this->parseSdmxJson($json, $indicator, $indicatorKey, $config, $url);

            Log::info("OECD: fetched {$indicatorKey}", ['stored' => $stored]);
            return $stored;

        } catch (\Throwable $e) {
            Log::error("OECD fetch failed", [
                'indicator' => $indicatorKey,
                'error'     => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Fetch all configured OECD indicators.
     */
    public function fetchAll(int $startYear = 2015, int $endYear = 2024): array
    {
        $results = [];
        foreach (array_keys(self::INDICATORS) as $key) {
            $results[$key] = $this->fetchIndicator($key, $startYear, $endYear);
            usleep(1_000_000); // 1s between requests — OECD is slower
        }
        return $results;
    }

    /**
     * Parse SDMX-JSON 2.0 response into data points.
     */
    private function parseSdmxJson(array $json, StatisticsIndicator $indicator, string $indicatorKey, array $config, string $url): int
    {
        $stored = 0;

        // SDMX-JSON 2.0 structure
        $dataSets = $json['data']['dataSets'] ?? [];
        $structures = $json['data']['structures'] ?? $json['data']['structure'] ?? [];

        if (empty($dataSets)) return 0;

        // Extract dimension values (country codes, time periods)
        $dimensions = $structures[0]['dimensions']['observation'] ?? $structures['dimensions']['observation'] ?? [];
        $countryDim = null;
        $timeDim = null;

        foreach ($dimensions as $i => $dim) {
            $id = $dim['id'] ?? '';
            if (in_array($id, ['REF_AREA', 'COUNTRY', 'COU'])) $countryDim = $i;
            if (in_array($id, ['TIME_PERIOD', 'TIME'])) $timeDim = $i;
        }

        if ($countryDim === null || $timeDim === null) {
            Log::warning("OECD: could not find country/time dimensions for {$indicatorKey}");
            return 0;
        }

        $countryValues = $dimensions[$countryDim]['values'] ?? [];
        $timeValues = $timeDim !== null ? ($dimensions[$timeDim]['values'] ?? []) : [];

        // Parse observations
        $observations = $dataSets[0]['observations'] ?? [];

        foreach ($observations as $key => $obsValues) {
            $indices = explode(':', $key);
            $countryIdx = (int) ($indices[$countryDim] ?? -1);
            $timeIdx = (int) ($indices[$timeDim] ?? -1);

            $countryInfo = $countryValues[$countryIdx] ?? null;
            $timeInfo = $timeValues[$timeIdx] ?? null;

            if (!$countryInfo || !$timeInfo) continue;

            $countryCode = $countryInfo['id'] ?? null;
            $countryName = $countryInfo['name'] ?? $countryCode;
            $year = (int) ($timeInfo['id'] ?? $timeInfo['start'] ?? 0);
            $value = $obsValues[0] ?? null;

            if (!$countryCode || !$year || $value === null) continue;
            if (strlen($countryCode) > 3) continue; // Skip aggregate regions

            // Normalize to ISO2 (OECD uses ISO3 sometimes)
            $normalizedCode = strtoupper(substr($countryCode, 0, 3));
            if (strlen($normalizedCode) > 2) {
                // Skip 3-char codes we can't normalize (they're likely regions like "OECD", "EU27")
                continue;
            }

            // Handle localized names
            if (is_array($countryName)) {
                $countryName = $countryName['en'] ?? reset($countryName);
            }

            try {
                StatisticsDataPoint::updateOrCreate(
                    [
                        'indicator_id' => $indicator->id,
                        'country_code' => $normalizedCode,
                        'year'         => $year,
                    ],
                    [
                        'indicator_code' => $indicatorKey,
                        'indicator_name' => $config['name'],
                        'country_name'   => $countryName,
                        'value'          => $value * ($config['multiply'] ?? 1),
                        'unit'           => $config['unit'],
                        'source'         => 'oecd',
                        'source_dataset' => $config['dataflow'],
                        'source_url'     => $url,
                        'fetched_at'     => now(),
                    ]
                );
                $stored++;
            } catch (\Illuminate\Database\QueryException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate') && !str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
            }
        }

        return $stored;
    }
}
