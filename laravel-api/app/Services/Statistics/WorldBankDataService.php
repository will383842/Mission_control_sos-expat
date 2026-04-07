<?php

namespace App\Services\Statistics;

use App\Models\StatisticsDataPoint;
use App\Models\StatisticsIndicator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * World Bank Open Data API — free, no auth, no rate limit.
 * Docs: https://datahelpdesk.worldbank.org/knowledgebase/articles/889392
 */
class WorldBankDataService
{
    private const BASE_URL = 'https://api.worldbank.org/v2';

    // Indicators relevant to expat/migration/travel/investment
    public const INDICATORS = [
        // Migration & Population
        'SM.POP.TOTL'       => ['name' => 'International migrant stock, total',              'theme' => 'expatries',      'unit' => 'persons'],
        'SM.POP.TOTL.ZS'    => ['name' => 'International migrant stock (% of population)',   'theme' => 'expatries',      'unit' => 'percent'],
        'SM.POP.NETM'       => ['name' => 'Net migration',                                   'theme' => 'expatries',      'unit' => 'persons'],
        'SP.POP.TOTL'       => ['name' => 'Population, total',                               'theme' => 'expatries',      'unit' => 'persons'],

        // Remittances
        'BX.TRF.PWKR.CD.DT' => ['name' => 'Personal remittances, received (current US$)',   'theme' => 'expatries',      'unit' => 'usd'],
        'BX.TRF.PWKR.DT.GD.ZS' => ['name' => 'Personal remittances, received (% of GDP)',  'theme' => 'expatries',      'unit' => 'percent'],

        // Tourism
        'ST.INT.ARVL'       => ['name' => 'International tourism, number of arrivals',       'theme' => 'voyageurs',      'unit' => 'persons'],
        'ST.INT.DPRT'       => ['name' => 'International tourism, number of departures',     'theme' => 'voyageurs',      'unit' => 'persons'],
        'ST.INT.RCPT.CD'    => ['name' => 'International tourism receipts (current US$)',     'theme' => 'voyageurs',      'unit' => 'usd'],
        'ST.INT.XPND.CD'    => ['name' => 'International tourism expenditures (current US$)','theme' => 'voyageurs',      'unit' => 'usd'],

        // Foreign Direct Investment
        'BX.KLT.DINV.CD.WD' => ['name' => 'Foreign direct investment, net inflows (BoP, current US$)', 'theme' => 'investisseurs', 'unit' => 'usd'],
        'BX.KLT.DINV.WD.GD.ZS' => ['name' => 'Foreign direct investment, net inflows (% of GDP)',     'theme' => 'investisseurs', 'unit' => 'percent'],
        'BM.KLT.DINV.CD.WD' => ['name' => 'Foreign direct investment, net outflows (BoP, current US$)','theme' => 'investisseurs', 'unit' => 'usd'],

        // Education (international students)
        'SE.TER.ENRR'       => ['name' => 'School enrollment, tertiary (% gross)',           'theme' => 'etudiants',      'unit' => 'percent'],
        'SE.XPD.TOTL.GD.ZS' => ['name' => 'Government expenditure on education (% of GDP)', 'theme' => 'etudiants',      'unit' => 'percent'],

        // Economy (context)
        'NY.GDP.PCAP.CD'    => ['name' => 'GDP per capita (current US$)',                    'theme' => 'investisseurs',  'unit' => 'usd'],
        'FP.CPI.TOTL.ZG'    => ['name' => 'Inflation, consumer prices (annual %)',           'theme' => 'investisseurs',  'unit' => 'percent'],
        'PA.NUS.PPP'        => ['name' => 'PPP conversion factor (GDP)',                     'theme' => 'voyageurs',      'unit' => 'index'],
    ];

    /**
     * Fetch data for one indicator across all countries.
     * Returns number of data points stored.
     */
    public function fetchIndicator(string $indicatorCode, int $startYear = 2015, int $endYear = 2024): int
    {
        $config = self::INDICATORS[$indicatorCode] ?? null;
        if (!$config) {
            Log::warning("WorldBank: unknown indicator {$indicatorCode}");
            return 0;
        }

        // Ensure indicator exists in DB
        $indicator = StatisticsIndicator::updateOrCreate(
            ['code' => $indicatorCode, 'source' => 'world_bank'],
            [
                'name'         => $config['name'],
                'theme'        => $config['theme'],
                'unit'         => $config['unit'],
                'api_endpoint' => self::BASE_URL . "/country/all/indicator/{$indicatorCode}",
                'is_active'    => true,
            ]
        );

        $stored = 0;
        $page = 1;
        $totalPages = 1;

        while ($page <= $totalPages) {
            $url = self::BASE_URL . "/country/all/indicator/{$indicatorCode}"
                . "?date={$startYear}:{$endYear}&format=json&per_page=500&page={$page}";

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    Log::warning("WorldBank API error", ['indicator' => $indicatorCode, 'status' => $response->status()]);
                    break;
                }

                $json = $response->json();
                if (!is_array($json) || count($json) < 2) break;

                $meta = $json[0];
                $totalPages = $meta['pages'] ?? 1;
                $records = $json[1] ?? [];

                foreach ($records as $record) {
                    if ($record['value'] === null) continue;

                    $countryCode = $record['country']['id'] ?? null;
                    $countryName = $record['country']['value'] ?? null;
                    $year = (int) ($record['date'] ?? 0);

                    // Skip aggregates (regions, world)
                    if (!$countryCode || strlen($countryCode) !== 2 && strlen($countryCode) !== 3) continue;
                    if ($year < $startYear) continue;

                    // Use 2-letter code when possible
                    $iso2 = $record['countryiso3code'] ?? $countryCode;
                    if (strlen($iso2) === 3) {
                        $iso2 = $this->iso3toIso2($iso2) ?? $iso2;
                    }

                    StatisticsDataPoint::updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'country_code' => strtoupper(substr($iso2, 0, 3)),
                            'year'         => $year,
                        ],
                        [
                            'indicator_code' => $indicatorCode,
                            'indicator_name' => $config['name'],
                            'country_name'   => $countryName,
                            'value'          => $record['value'],
                            'unit'           => $config['unit'],
                            'source'         => 'world_bank',
                            'source_dataset' => 'WDI',
                            'source_url'     => $url,
                            'fetched_at'     => now(),
                        ]
                    );
                    $stored++;
                }

                $page++;

                // Polite delay between pages
                if ($page <= $totalPages) {
                    usleep(200_000); // 200ms
                }

            } catch (\Throwable $e) {
                Log::error("WorldBank fetch failed", [
                    'indicator' => $indicatorCode,
                    'page'      => $page,
                    'error'     => $e->getMessage(),
                ]);
                break;
            }
        }

        Log::info("WorldBank: fetched {$indicatorCode}", ['stored' => $stored]);
        return $stored;
    }

    /**
     * Fetch ALL configured indicators.
     */
    public function fetchAll(int $startYear = 2015, int $endYear = 2024): array
    {
        $results = [];
        foreach (array_keys(self::INDICATORS) as $code) {
            $count = $this->fetchIndicator($code, $startYear, $endYear);
            $results[$code] = $count;
            // 500ms between indicators to be polite
            usleep(500_000);
        }
        return $results;
    }

    /**
     * Fetch indicators for a specific theme only.
     */
    public function fetchByTheme(string $theme, int $startYear = 2015, int $endYear = 2024): array
    {
        $results = [];
        foreach (self::INDICATORS as $code => $config) {
            if ($config['theme'] === $theme) {
                $results[$code] = $this->fetchIndicator($code, $startYear, $endYear);
                usleep(500_000);
            }
        }
        return $results;
    }

    /**
     * ISO 3166-1 alpha-3 to alpha-2 mapping (common countries).
     */
    private function iso3toIso2(string $iso3): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'AFG'=>'AF','ALB'=>'AL','DZA'=>'DZ','AND'=>'AD','AGO'=>'AO','ARG'=>'AR','ARM'=>'AM','AUS'=>'AU',
                'AUT'=>'AT','AZE'=>'AZ','BHS'=>'BS','BHR'=>'BH','BGD'=>'BD','BRB'=>'BB','BLR'=>'BY','BEL'=>'BE',
                'BLZ'=>'BZ','BEN'=>'BJ','BTN'=>'BT','BOL'=>'BO','BIH'=>'BA','BWA'=>'BW','BRA'=>'BR','BRN'=>'BN',
                'BGR'=>'BG','BFA'=>'BF','BDI'=>'BI','KHM'=>'KH','CMR'=>'CM','CAN'=>'CA','CPV'=>'CV','CAF'=>'CF',
                'TCD'=>'TD','CHL'=>'CL','CHN'=>'CN','COL'=>'CO','COM'=>'KM','COG'=>'CG','COD'=>'CD','CRI'=>'CR',
                'CIV'=>'CI','HRV'=>'HR','CUB'=>'CU','CYP'=>'CY','CZE'=>'CZ','DNK'=>'DK','DJI'=>'DJ','DOM'=>'DO',
                'ECU'=>'EC','EGY'=>'EG','SLV'=>'SV','GNQ'=>'GQ','ERI'=>'ER','EST'=>'EE','ETH'=>'ET','FIN'=>'FI',
                'FRA'=>'FR','GAB'=>'GA','GMB'=>'GM','GEO'=>'GE','DEU'=>'DE','GHA'=>'GH','GRC'=>'GR','GTM'=>'GT',
                'GIN'=>'GN','GNB'=>'GW','GUY'=>'GY','HTI'=>'HT','HND'=>'HN','HUN'=>'HU','ISL'=>'IS','IND'=>'IN',
                'IDN'=>'ID','IRN'=>'IR','IRQ'=>'IQ','IRL'=>'IE','ISR'=>'IL','ITA'=>'IT','JAM'=>'JM','JPN'=>'JP',
                'JOR'=>'JO','KAZ'=>'KZ','KEN'=>'KE','KWT'=>'KW','KGZ'=>'KG','LAO'=>'LA','LVA'=>'LV','LBN'=>'LB',
                'LSO'=>'LS','LBR'=>'LR','LBY'=>'LY','LIE'=>'LI','LTU'=>'LT','LUX'=>'LU','MDG'=>'MG','MWI'=>'MW',
                'MYS'=>'MY','MDV'=>'MV','MLI'=>'ML','MLT'=>'MT','MRT'=>'MR','MUS'=>'MU','MEX'=>'MX','MDA'=>'MD',
                'MCO'=>'MC','MNG'=>'MN','MNE'=>'ME','MAR'=>'MA','MOZ'=>'MZ','MMR'=>'MM','NAM'=>'NA','NPL'=>'NP',
                'NLD'=>'NL','NZL'=>'NZ','NIC'=>'NI','NER'=>'NE','NGA'=>'NG','NOR'=>'NO','OMN'=>'OM','PAK'=>'PK',
                'PAN'=>'PA','PRY'=>'PY','PER'=>'PE','PHL'=>'PH','POL'=>'PL','PRT'=>'PT','QAT'=>'QA','ROU'=>'RO',
                'RUS'=>'RU','RWA'=>'RW','SAU'=>'SA','SEN'=>'SN','SRB'=>'RS','SGP'=>'SG','SVK'=>'SK','SVN'=>'SI',
                'ZAF'=>'ZA','KOR'=>'KR','ESP'=>'ES','LKA'=>'LK','SDN'=>'SD','SWE'=>'SE','CHE'=>'CH','SYR'=>'SY',
                'TWN'=>'TW','TJK'=>'TJ','TZA'=>'TZ','THA'=>'TH','TGO'=>'TG','TTO'=>'TT','TUN'=>'TN','TUR'=>'TR',
                'TKM'=>'TM','UGA'=>'UG','UKR'=>'UA','ARE'=>'AE','GBR'=>'GB','USA'=>'US','URY'=>'UY','UZB'=>'UZ',
                'VEN'=>'VE','VNM'=>'VN','YEM'=>'YE','ZMB'=>'ZM','ZWE'=>'ZW',
            ];
        }
        return $map[strtoupper($iso3)] ?? null;
    }
}
