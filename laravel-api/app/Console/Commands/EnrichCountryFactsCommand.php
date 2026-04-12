<?php

namespace App\Console\Commands;

use App\Models\CountryFact;
use App\Models\CountryGeo;
use App\Models\StatisticsDataPoint;
use App\Services\PerplexitySearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Enriches the country_facts table with data from:
 * 1. statistics_data_points (World Bank / OECD / Eurostat — already fetched)
 * 2. Perplexity (for visa, cost-of-living, safety — when available)
 *
 * Usage:
 *   php artisan country:enrich TH              # Enrich one country
 *   php artisan country:enrich-batch --tier=1   # Enrich Tier 1 (24 countries)
 *   php artisan country:enrich-batch --tier=2   # Enrich Tier 2 (50 countries)
 *   php artisan country:enrich-batch --all      # Enrich all 197 countries
 *   php artisan country:enrich-batch --missing  # Only countries without facts
 */
class EnrichCountryFactsCommand extends Command
{
    protected $signature = 'country:enrich
                            {country? : ISO 2-letter country code (e.g., TH, FR)}
                            {--batch : Run in batch mode}
                            {--tier=0 : Tier to process (1=top 24, 2=next 50, 3=rest)}
                            {--all : Process all 197 countries}
                            {--missing : Only countries without existing facts}
                            {--skip-perplexity : Skip Perplexity research (DB only)}';

    protected $description = 'Enrich country_facts with data from statistics DB + Perplexity';

    // Tier 1: 24 top expat destinations
    private const TIER_1 = [
        'FR', 'GB', 'DE', 'ES', 'PT', 'US', 'CA', 'AU', 'AE', 'TH',
        'SG', 'JP', 'CH', 'BE', 'NL', 'IT', 'BR', 'MX', 'MA', 'CN',
        'RU', 'IN', 'KR', 'NZ',
    ];

    // Tier 2: 50 notable countries
    private const TIER_2 = [
        'IE', 'AT', 'LU', 'SE', 'NO', 'DK', 'FI', 'PL', 'CZ', 'GR',
        'TR', 'SA', 'QA', 'IL', 'ZA', 'NG', 'EG', 'KE', 'SN', 'CI',
        'CM', 'TN', 'DZ', 'CO', 'AR', 'CL', 'MY', 'VN', 'PH', 'ID',
        'HK', 'TW', 'RO', 'HU', 'HR', 'LB', 'PE', 'CR', 'PA', 'UA',
        'KW', 'BH', 'OM', 'MU', 'GA', 'CD', 'CG', 'MG', 'GH', 'ET',
    ];

    public function handle(): int
    {
        if ($country = $this->argument('country')) {
            return $this->enrichCountry(strtoupper($country));
        }

        return $this->enrichBatch();
    }

    private function enrichBatch(): int
    {
        $codes = $this->getCountryCodes();

        if (empty($codes)) {
            $this->warn('No countries to process.');
            return 0;
        }

        $this->info("Enriching {$codes->count()} countries...");
        $bar = $this->output->createProgressBar($codes->count());

        $success = 0;
        $failed = 0;

        foreach ($codes as $code) {
            try {
                $this->enrichCountry($code, false);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("country:enrich failed for {$code}", ['error' => $e->getMessage()]);
            }
            $bar->advance();

            // Rate limiting for Perplexity
            if (!$this->option('skip-perplexity')) {
                usleep(2_000_000); // 2s between countries
            }
        }

        $bar->finish();
        $this->info('');
        $this->info("Done: {$success} enriched, {$failed} failed.");

        return 0;
    }

    private function enrichCountry(string $countryCode, bool $verbose = true): int
    {
        $geo = CountryGeo::findByCode($countryCode);
        if (!$geo) {
            $this->error("Country not found in countries_geo: {$countryCode}");
            return 1;
        }

        if ($verbose) {
            $this->info("Enriching {$geo->country_name_fr} ({$countryCode})...");
        }

        // 1. Start with existing fact or create new
        $fact = CountryFact::firstOrNew(['country_code' => $countryCode]);
        $fact->country_name_fr = $geo->country_name_fr;
        $fact->country_name_en = $geo->country_name_en;
        $fact->currency_code = $geo->currency_code;
        $fact->currency_name = $geo->currency_name;
        $fact->timezone = $geo->timezone;

        $sources = $fact->source_urls ?? [];
        $years = $fact->data_years ?? [];

        // 2. Fill from statistics_data_points (DB — verified)
        $this->fillFromStatistics($fact, $countryCode, $sources, $years);

        // 3. Fill from Perplexity (optional — for visa, safety, cost-of-living)
        if (!$this->option('skip-perplexity') && app(PerplexitySearchService::class)->isConfigured()) {
            $this->fillFromPerplexity($fact, $countryCode, $geo->country_name_en, $sources, $years);
        }

        // 4. Save
        $fact->source_urls = $sources;
        $fact->data_years = $years;
        $fact->last_verified_at = now();
        $fact->verification_status = !empty($sources) ? 'verified' : 'unverified';
        $fact->save();

        if ($verbose) {
            $this->info("  Completeness: {$fact->completeness}%");
            $this->info("  Sources: " . count($sources));
        }

        return 0;
    }

    /**
     * Fill country fact fields from statistics_data_points table.
     */
    private function fillFromStatistics(CountryFact $fact, string $countryCode, array &$sources, array &$years): void
    {
        $mapping = [
            'SP.POP.TOTL'             => ['field' => 'total_population', 'cast' => 'int'],
            'SM.POP.TOTL'             => ['field' => 'expat_population', 'cast' => 'int'],
            'SM.POP.TOTL.ZS'          => ['field' => 'expat_pct_population', 'cast' => 'float'],
            'NY.GDP.PCAP.CD'          => ['field' => 'gdp_per_capita_usd', 'cast' => 'float'],
            'FP.CPI.TOTL.ZG'          => ['field' => 'inflation_rate', 'cast' => 'float'],
            'PA.NUS.PPP'              => ['field' => 'ppp_factor', 'cast' => 'float'],
            'ST.INT.ARVL'             => ['field' => 'tourism_arrivals', 'cast' => 'int'],
            'ST.INT.RCPT.CD'          => ['field' => 'tourism_receipts_usd', 'cast' => 'float'],
            'SE.XPD.TOTL.GD.ZS'      => ['field' => 'health_expenditure_pct_gdp', 'cast' => 'float'],
        ];

        foreach ($mapping as $indicator => $config) {
            $dp = StatisticsDataPoint::where('country_code', $countryCode)
                ->where('indicator_code', $indicator)
                ->whereNotNull('value')
                ->orderByDesc('year')
                ->first();

            if ($dp) {
                $field = $config['field'];
                $value = $config['cast'] === 'int' ? (int) $dp->value : (float) $dp->value;
                $fact->$field = $value;
                $years[$field] = $dp->year;
                $sources[$field] = $dp->source . ' (' . $dp->year . ')';
            }
        }

        // Derive cost_of_living_index from PPP factor if available
        if ($fact->ppp_factor && !$fact->cost_of_living_index) {
            // PPP < 1 means cheaper than US, > 1 means more expensive
            // Normalize: NYC ≈ 100, PPP=1 → ~70, PPP=0.3 → ~30
            $fact->cost_of_living_index = min(100, round($fact->ppp_factor * 70, 2));
            $sources['cost_of_living_index'] = 'Derived from World Bank PPP factor';
        }
    }

    /**
     * Fill additional fields from Perplexity search (visa, safety, salary, etc.).
     */
    private function fillFromPerplexity(CountryFact $fact, string $countryCode, string $countryName, array &$sources, array &$years): void
    {
        // Only query if key fields are missing
        $needsData = !$fact->safety_index
            || !$fact->min_wage_monthly_usd
            || !$fact->avg_rent_1bed_center_usd
            || !$fact->healthcare_type
            || !$fact->emergency_number;

        if (!$needsData) {
            return;
        }

        $perplexity = app(PerplexitySearchService::class);

        $query = "For {$countryName} ({$countryCode}), provide ONLY verified factual data with sources:\n"
            . "1. Safety index (Numbeo or similar, 0-100 scale)\n"
            . "2. Minimum wage in USD/month (official government source)\n"
            . "3. Average salary in USD/month\n"
            . "4. Average rent for 1-bedroom apartment in city center (USD/month)\n"
            . "5. Average rent for 1-bedroom apartment outside city center (USD/month)\n"
            . "6. Coffee price in USD\n"
            . "7. Restaurant meal price in USD\n"
            . "8. Healthcare system type (public/private/mixed/universal)\n"
            . "9. Emergency phone number\n"
            . "10. Internet speed (average Mbps)\n"
            . "11. Does this country have a digital nomad visa? (yes/no + cost in USD if yes)\n"
            . "\nFormat each answer as: FIELD: value (source, year)\n"
            . "If data is not available, write: FIELD: NOT_AVAILABLE";

        $result = $perplexity->searchFactual($query, 'en');

        if (!$result['success'] || empty($result['text'])) {
            return;
        }

        $text = $result['text'];

        // Parse structured responses
        $this->parsePerplexityField($text, $fact, $sources, $years, 'safety_index', '/safety[^:]*:\s*([\d.]+)/i', 'float');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'min_wage_monthly_usd', '/minimum\s+wage[^:]*:\s*\$?([\d,]+)/i', 'int');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'avg_salary_monthly_usd', '/average\s+salary[^:]*:\s*\$?([\d,]+)/i', 'int');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'avg_rent_1bed_center_usd', '/rent[^:]*center[^:]*:\s*\$?([\d,]+)/i', 'int');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'avg_rent_1bed_outside_usd', '/rent[^:]*outside[^:]*:\s*\$?([\d,]+)/i', 'int');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'coffee_usd', '/coffee[^:]*:\s*\$?([\d.]+)/i', 'float');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'meal_restaurant_usd', '/(?:restaurant|meal)[^:]*:\s*\$?([\d.]+)/i', 'float');
        $this->parsePerplexityField($text, $fact, $sources, $years, 'internet_speed_mbps', '/internet[^:]*:\s*([\d.]+)/i', 'float');

        // Healthcare type
        if (preg_match('/healthcare[^:]*:\s*(public|private|mixed|universal)/i', $text, $m)) {
            $fact->healthcare_type = strtolower($m[1]);
            $sources['healthcare_type'] = 'Perplexity (' . date('Y') . ')';
        }

        // Emergency number
        if (preg_match('/emergency[^:]*:\s*([\d\s-]+)/i', $text, $m)) {
            $number = trim($m[1]);
            if (strlen($number) >= 2 && strlen($number) <= 20) {
                $fact->emergency_number = $number;
                $sources['emergency_number'] = 'Perplexity (' . date('Y') . ')';
            }
        }

        // Digital nomad visa
        if (preg_match('/nomad\s*visa[^:]*:\s*(yes|no)/i', $text, $m)) {
            $fact->has_digital_nomad_visa = strtolower($m[1]) === 'yes';
            if ($fact->has_digital_nomad_visa && preg_match('/\$?([\d,]+)\s*(?:USD)?/i', substr($text, strpos($text, $m[0]) + strlen($m[0]), 100), $costMatch)) {
                $fact->nomad_visa_cost_usd = (int) str_replace(',', '', $costMatch[1]);
            }
            $sources['has_digital_nomad_visa'] = 'Perplexity (' . date('Y') . ')';
        }
    }

    /**
     * Parse a single field from Perplexity response.
     */
    private function parsePerplexityField(string $text, CountryFact $fact, array &$sources, array &$years, string $field, string $pattern, string $cast): void
    {
        if ($fact->$field !== null) {
            return; // Don't overwrite existing data
        }

        if (preg_match($pattern, $text, $m)) {
            $value = str_replace(',', '', $m[1]);
            $fact->$field = $cast === 'int' ? (int) $value : (float) $value;
            $sources[$field] = 'Perplexity (' . date('Y') . ')';
            $years[$field] = (int) date('Y');
        }
    }

    /**
     * Get list of country codes to process based on options.
     */
    private function getCountryCodes()
    {
        $tier = (int) $this->option('tier');

        if ($this->option('all')) {
            $codes = CountryGeo::pluck('country_code');
        } elseif ($tier === 1) {
            $codes = collect(self::TIER_1);
        } elseif ($tier === 2) {
            $codes = collect(self::TIER_2);
        } elseif ($tier === 3) {
            $allCodes = CountryGeo::pluck('country_code');
            $codes = $allCodes->diff(collect(array_merge(self::TIER_1, self::TIER_2)));
        } else {
            // Default: Tier 1
            $codes = collect(self::TIER_1);
        }

        if ($this->option('missing')) {
            $existing = CountryFact::pluck('country_code');
            $codes = $codes->diff($existing);
        }

        return $codes;
    }
}
