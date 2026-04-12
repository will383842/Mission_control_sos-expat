<?php

namespace App\Services\Content;

use App\Models\CountryFact;
use App\Models\CountryGeo;
use App\Models\StatisticsDataPoint;
use App\Services\Statistics\WorldBankDataService;
use Illuminate\Support\Facades\Log;

/**
 * Injects VERIFIED statistics from the database into article generation prompts.
 *
 * Hierarchy of trust:
 *   1. country_facts (manually verified or API-populated)
 *   2. statistics_data_points (World Bank / OECD / Eurostat)
 *   3. Perplexity (with citation) — complement only
 *   4. AI pure — FORBIDDEN for statistics
 */
class StatisticsInjectionService
{
    /**
     * Get all verified data for a country, formatted for prompt injection.
     * Returns a string block to prepend to the AI prompt.
     */
    public function getCountryDataBlock(?string $countryCode): string
    {
        if (!$countryCode || strlen($countryCode) !== 2) {
            return '';
        }

        $countryCode = strtoupper($countryCode);
        $blocks = [];

        // 1. Structured country facts (primary source)
        $facts = CountryFact::find($countryCode);
        if ($facts) {
            $blocks[] = $facts->toPromptBlock();
        }

        // 2. Latest statistics data points (World Bank / OECD / Eurostat)
        $statsBlock = $this->getStatisticsBlock($countryCode);
        if ($statsBlock) {
            $blocks[] = $statsBlock;
        }

        // 3. Geo context from countries_geo
        $geo = CountryGeo::findByCode($countryCode);
        if ($geo && empty($facts)) {
            // Only add basic geo if no country_facts exist
            $blocks[] = $this->formatGeoBlock($geo);
        }

        if (empty($blocks)) {
            return '';
        }

        $dataContent = implode("\n\n", $blocks);

        return <<<BLOCK

=== DONNEES VERIFIEES (BASE DE DONNEES — NE PAS MODIFIER) ===

{$dataContent}

INSTRUCTIONS DONNEES :
- Utilise EXCLUSIVEMENT ces chiffres pour les statistiques.
- Cite TOUJOURS la source entre parentheses (ex: "World Bank 2024", "OECD 2023").
- NE JAMAIS inventer d'autres statistiques que celles ci-dessus.
- Si une donnee manque, ecris "selon les sources disponibles" au lieu d'inventer un chiffre.
- NE JAMAIS arrondir ou modifier les valeurs fournies.

=== FIN DONNEES VERIFIEES ===

BLOCK;
    }

    /**
     * Get statistics data points for a country, grouped by source.
     */
    public function getStatisticsBlock(string $countryCode): string
    {
        $dataPoints = StatisticsDataPoint::where('country_code', $countryCode)
            ->whereNotNull('value')
            ->orderByDesc('year')
            ->orderBy('source')
            ->get();

        if ($dataPoints->isEmpty()) {
            return '';
        }

        // Group by source and pick the most recent year per indicator
        $latestByIndicator = [];
        foreach ($dataPoints as $dp) {
            $key = $dp->indicator_code;
            if (!isset($latestByIndicator[$key]) || $dp->year > $latestByIndicator[$key]->year) {
                $latestByIndicator[$key] = $dp;
            }
        }

        // Group by source for display
        $bySource = [];
        foreach ($latestByIndicator as $dp) {
            $source = $dp->source;
            $bySource[$source][] = $dp;
        }

        $lines = ['--- Statistiques officielles (APIs verifices) ---'];

        foreach ($bySource as $source => $points) {
            $sourceName = match ($source) {
                'world_bank' => 'World Bank',
                'oecd'       => 'OECD',
                'eurostat'   => 'Eurostat',
                default      => ucfirst($source),
            };

            $lines[] = "\nSource: {$sourceName}";

            foreach ($points as $dp) {
                $formattedValue = $this->formatValue($dp->value, $dp->unit);
                $lines[] = "- {$dp->indicator_name} ({$dp->year}) : {$formattedValue}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get country facts as structured array (for use in code, not prompts).
     */
    public function getCountryFacts(string $countryCode): ?array
    {
        $facts = CountryFact::find(strtoupper($countryCode));
        return $facts ? $facts->toArray() : null;
    }

    /**
     * Get the most recent value for a specific indicator + country.
     */
    public function getLatestStat(string $countryCode, string $indicatorCode): ?array
    {
        $dp = StatisticsDataPoint::where('country_code', strtoupper($countryCode))
            ->where('indicator_code', $indicatorCode)
            ->whereNotNull('value')
            ->orderByDesc('year')
            ->first();

        if (!$dp) {
            return null;
        }

        return [
            'value'   => $dp->value,
            'year'    => $dp->year,
            'unit'    => $dp->unit,
            'source'  => $dp->source,
            'source_url' => $dp->source_url,
        ];
    }

    /**
     * Check data availability for a country.
     * Returns a summary of what's available.
     */
    public function getDataAvailability(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);

        $hasFacts = CountryFact::where('country_code', $countryCode)->exists();
        $statsCount = StatisticsDataPoint::where('country_code', $countryCode)->count();
        $hasGeo = CountryGeo::where('country_code', $countryCode)->exists();

        $sources = StatisticsDataPoint::where('country_code', $countryCode)
            ->distinct('source')
            ->pluck('source')
            ->toArray();

        $indicators = StatisticsDataPoint::where('country_code', $countryCode)
            ->distinct('indicator_code')
            ->pluck('indicator_code')
            ->toArray();

        return [
            'country_code'    => $countryCode,
            'has_facts'       => $hasFacts,
            'stats_count'     => $statsCount,
            'has_geo'         => $hasGeo,
            'sources'         => $sources,
            'indicators'      => $indicators,
            'coverage_score'  => $this->calculateCoverageScore($hasFacts, $statsCount, count($indicators)),
        ];
    }

    /**
     * Extract country code from article topic/title.
     * Uses fuzzy matching against countries_geo table.
     */
    public function extractCountryCode(?string $topic, ?string $explicitCountry = null): ?string
    {
        // Explicit country code takes precedence
        if ($explicitCountry && strlen($explicitCountry) === 2) {
            return strtoupper($explicitCountry);
        }

        // Try to find country name in topic
        if (!$topic) {
            return null;
        }

        $topic = mb_strtolower($topic);

        // Check against all country names (fr + en)
        $countries = CountryGeo::all();
        $bestMatch = null;
        $bestLength = 0;

        foreach ($countries as $country) {
            $nameFr = mb_strtolower($country->country_name_fr);
            $nameEn = mb_strtolower($country->country_name_en);

            // Match longest country name to avoid "Niger" matching before "Nigeria"
            if (str_contains($topic, $nameFr) && mb_strlen($nameFr) > $bestLength) {
                $bestMatch = $country->country_code;
                $bestLength = mb_strlen($nameFr);
            }
            if (str_contains($topic, $nameEn) && mb_strlen($nameEn) > $bestLength) {
                $bestMatch = $country->country_code;
                $bestLength = mb_strlen($nameEn);
            }
        }

        return $bestMatch;
    }

    /**
     * Format a numeric value with its unit for display.
     */
    private function formatValue(float $value, string $unit): string
    {
        return match ($unit) {
            'persons' => number_format($value, 0, ',', ' ') . ' personnes',
            'percent' => number_format($value, 1, ',', ' ') . '%',
            'usd'     => $this->formatUsd($value),
            'index'   => number_format($value, 2, ',', ' '),
            default   => number_format($value, 2, ',', ' ') . " {$unit}",
        };
    }

    /**
     * Format USD with appropriate scale (millions, milliards).
     */
    private function formatUsd(float $value): string
    {
        if (abs($value) >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 1, ',', ' ') . ' milliards USD';
        }
        if (abs($value) >= 1_000_000) {
            return number_format($value / 1_000_000, 1, ',', ' ') . ' millions USD';
        }
        return number_format($value, 0, ',', ' ') . ' USD';
    }

    /**
     * Format basic geo data as prompt block.
     */
    private function formatGeoBlock(CountryGeo $geo): string
    {
        $lines = ["--- Donnees geographiques : {$geo->country_name_fr} ---"];

        if ($geo->capital_fr) {
            $lines[] = "Capitale : {$geo->capital_fr}";
        }
        if ($geo->official_language) {
            $lines[] = "Langue officielle : {$geo->official_language}";
        }
        if ($geo->currency_code) {
            $lines[] = "Monnaie : {$geo->currency_name} ({$geo->currency_code})";
        }
        if ($geo->timezone) {
            $lines[] = "Fuseau horaire : {$geo->timezone}";
        }
        if ($geo->region) {
            $lines[] = "Region : {$geo->region}";
        }

        return implode("\n", $lines);
    }

    /**
     * Calculate a coverage score (0-100).
     */
    private function calculateCoverageScore(bool $hasFacts, int $statsCount, int $indicatorCount): int
    {
        $score = 0;

        // Country facts present: 40 points
        if ($hasFacts) {
            $score += 40;
        }

        // Stats count (up to 30 points for 50+ data points)
        $score += min(30, (int) ($statsCount / 50 * 30));

        // Indicator diversity (up to 30 points for 15+ indicators)
        $score += min(30, (int) ($indicatorCount / 15 * 30));

        return min(100, $score);
    }
}
