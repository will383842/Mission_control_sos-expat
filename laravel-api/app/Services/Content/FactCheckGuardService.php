<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\StatisticsDataPoint;
use Illuminate\Support\Facades\Log;

/**
 * Post-generation fact-checking guard.
 *
 * Compares numbers cited in generated articles against verified data
 * in statistics_data_points and country_facts tables.
 *
 * Detects:
 * - Numbers that deviate >15% from verified sources
 * - SOS-Expat pricing inconsistencies
 * - Unsourced statistical claims ("environ X millions...")
 * - Fabricated source names
 */
class FactCheckGuardService
{
    // SOS-Expat prices that must be exact (from KB)
    private const EXPECTED_PRICES = [
        'lawyer_eur' => 49,
        'lawyer_usd' => 55,
        'expert_eur' => 19,
        'expert_usd' => 25,
        'provider_lawyer_eur' => 30,
        'provider_lawyer_usd' => 30,
        'provider_expert_eur' => 10,
        'provider_expert_usd' => 10,
    ];

    // Price patterns to detect in content
    private const PRICE_PATTERNS = [
        '/(\d+)\s*(?:€|EUR|euros?)/iu',
        '/(\d+)\s*(?:\$|USD|dollars?)/iu',
    ];

    // Max acceptable deviation from verified data (15%)
    private const MAX_DEVIATION_PERCENT = 15;

    /**
     * Run fact-check on generated article.
     *
     * @param  GeneratedArticle  $article
     * @param  string|null       $countryCode  Country for data comparison
     * @return array{passed: bool, score: int, issues: array, warnings: array}
     */
    public function check(GeneratedArticle $article, ?string $countryCode = null): array
    {
        $html = $article->content_html ?? '';
        $text = strip_tags($html);
        $issues = [];
        $warnings = [];

        // 1. Check SOS-Expat pricing consistency
        $priceIssues = $this->checkPricing($text);
        $issues = array_merge($issues, $priceIssues);

        // 2. Check statistics against DB
        if ($countryCode) {
            $statIssues = $this->checkStatistics($text, $countryCode);
            $warnings = array_merge($warnings, $statIssues['warnings']);
            $issues = array_merge($issues, $statIssues['issues']);
        }

        // 3. Detect unsourced statistical claims
        $unsourcedWarnings = $this->detectUnsourcedClaims($text);
        $warnings = array_merge($warnings, $unsourcedWarnings);

        // 4. Check for fabricated source names
        $fabricatedWarnings = $this->detectFabricatedSources($text);
        $warnings = array_merge($warnings, $fabricatedWarnings);

        // Score: 100 minus penalties
        $score = 100;
        $score -= count($issues) * 15;       // Blocking issues: -15 each
        $score -= count($warnings) * 5;      // Warnings: -5 each
        $score = max(0, $score);

        $passed = empty($issues) && $score >= 60;

        if (!$passed) {
            Log::warning('FactCheckGuard: article failed', [
                'article_id' => $article->id,
                'country'    => $countryCode,
                'score'      => $score,
                'issues'     => count($issues),
                'warnings'   => count($warnings),
            ]);
        }

        return [
            'passed'   => $passed,
            'score'    => $score,
            'issues'   => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check SOS-Expat prices mentioned in content match KB.
     */
    private function checkPricing(string $text): array
    {
        $issues = [];

        // Check for common price patterns near SOS-Expat keywords
        $sosContext = $this->extractContext($text, 'SOS-Expat', 200);
        $contexts = array_merge($sosContext, $this->extractContext($text, 'avocat', 100), $this->extractContext($text, 'expert', 100));

        foreach ($contexts as $ctx) {
            // Look for price mentions
            if (preg_match('/avocat[^.]{0,50}?(\d+)\s*(?:€|EUR)/iu', $ctx, $m)) {
                $price = (int) $m[1];
                if ($price !== self::EXPECTED_PRICES['lawyer_eur'] && $price > 0) {
                    $issues[] = [
                        'type'     => 'price_mismatch',
                        'severity' => 'blocking',
                        'message'  => "Prix avocat EUR incorrect: {$price}€ (attendu: " . self::EXPECTED_PRICES['lawyer_eur'] . "€)",
                        'found'    => $price,
                        'expected' => self::EXPECTED_PRICES['lawyer_eur'],
                    ];
                }
            }

            if (preg_match('/expert[^.]{0,50}?(\d+)\s*(?:€|EUR)/iu', $ctx, $m)) {
                $price = (int) $m[1];
                if ($price !== self::EXPECTED_PRICES['expert_eur'] && $price > 0) {
                    $issues[] = [
                        'type'     => 'price_mismatch',
                        'severity' => 'blocking',
                        'message'  => "Prix expert EUR incorrect: {$price}€ (attendu: " . self::EXPECTED_PRICES['expert_eur'] . "€)",
                        'found'    => $price,
                        'expected' => self::EXPECTED_PRICES['expert_eur'],
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Compare statistics in content against verified DB data.
     */
    private function checkStatistics(string $text, string $countryCode): array
    {
        $issues = [];
        $warnings = [];

        // Get all verified data points for this country (latest year per indicator)
        $verified = StatisticsDataPoint::where('country_code', strtoupper($countryCode))
            ->whereNotNull('value')
            ->orderByDesc('year')
            ->get()
            ->groupBy('indicator_code')
            ->map(fn ($group) => $group->first());

        if ($verified->isEmpty()) {
            return ['issues' => [], 'warnings' => []];
        }

        // Extract all numbers from text with surrounding context
        preg_match_all('/(\d[\d\s,.]*\d|\d+)\s*(millions?|milliards?|billions?|%|personnes?|habitants?|USD|EUR|€|\$)?/iu', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($verified as $indicatorCode => $dp) {
            $value = (float) $dp->value;
            if ($value == 0) continue;

            // Try to find this stat mentioned in the text
            $match = $this->findStatInText($text, $dp, $matches);
            if ($match) {
                $textValue = $match['value'];
                $deviation = abs($textValue - $value) / $value * 100;

                if ($deviation > self::MAX_DEVIATION_PERCENT) {
                    $warnings[] = [
                        'type'       => 'stat_deviation',
                        'severity'   => 'warning',
                        'message'    => "Ecart {$dp->indicator_name}: article={$this->formatNumber($textValue)}, DB={$this->formatNumber($value)} ({$dp->source} {$dp->year}) — deviation {$deviation:.0f}%",
                        'indicator'  => $indicatorCode,
                        'found'      => $textValue,
                        'expected'   => $value,
                        'deviation'  => round($deviation, 1),
                    ];
                }
            }
        }

        return ['issues' => $issues, 'warnings' => $warnings];
    }

    /**
     * Detect statistical claims without source attribution.
     */
    private function detectUnsourcedClaims(string $text): array
    {
        $warnings = [];

        // Patterns that suggest unsourced statistical claims
        $patterns = [
            '/environ\s+(\d[\d\s,.]*)\s*(millions?|milliards?)/iu' => 'Statistique approximative sans source',
            '/(?:pres de|plus de|presque)\s+(\d[\d\s,.]*)\s*(millions?|milliards?)/iu' => 'Statistique approximative sans source',
            '/(\d[\d\s,.]*)\s*%\s+(?:de la population|des expatries|des touristes)/iu' => 'Pourcentage sans source',
        ];

        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $surrounding = substr($text, max(0, $match[1] - 100), 250);

                    // Check if a source is mentioned nearby
                    $hasSource = preg_match('/(?:World Bank|Banque mondiale|OECD|OCDE|Eurostat|ONU|UN|IMF|FMI|UNESCO|WHO|OMS|selon|source|d\'apres)/iu', $surrounding);

                    if (!$hasSource) {
                        $warnings[] = [
                            'type'     => 'unsourced_claim',
                            'severity' => 'warning',
                            'message'  => "{$description} : \"{$match[0]}\"",
                            'context'  => trim(substr($surrounding, 0, 150)),
                        ];
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Detect potentially fabricated source/organization names.
     */
    private function detectFabricatedSources(string $text): array
    {
        $warnings = [];

        // Known legitimate sources
        $legitimate = [
            'world bank', 'banque mondiale', 'oecd', 'ocde', 'eurostat',
            'onu', 'united nations', 'nations unies', 'imf', 'fmi',
            'unesco', 'who', 'oms', 'ilo', 'oit', 'unhcr', 'hcr',
            'numbeo', 'mercer', 'internations', 'expat insider',
            'transparency international', 'amnesty international',
            'reporters sans frontieres', 'global peace index',
            'henley passport index', 'bloomberg', 'reuters', 'economist',
        ];

        // Find "selon [Organization]" patterns
        preg_match_all('/selon\s+(?:le|la|l\'|les|une?|the)?\s*([A-Z][a-zA-Zéèêëàâäùûüôöîïç\s\'-]{3,40})/u', $text, $matches);

        foreach ($matches[1] ?? [] as $org) {
            $orgLower = mb_strtolower(trim($org));
            $isLegit = false;
            foreach ($legitimate as $known) {
                if (str_contains($orgLower, $known)) {
                    $isLegit = true;
                    break;
                }
            }
            if (!$isLegit && !str_contains($orgLower, 'sos-expat') && !str_contains($orgLower, 'gouvernement')) {
                $warnings[] = [
                    'type'     => 'unknown_source',
                    'severity' => 'info',
                    'message'  => "Source inconnue citee: \"{$org}\" — verifier qu'elle existe",
                ];
            }
        }

        return $warnings;
    }

    /**
     * Try to match a verified data point with a number in the text.
     */
    private function findStatInText(string $text, StatisticsDataPoint $dp, array $matches): ?array
    {
        $value = (float) $dp->value;
        $unit = $dp->unit;

        foreach ($matches as $match) {
            $rawNumber = str_replace([' ', ','], ['', '.'], $match[1][0]);
            $textValue = (float) $rawNumber;
            $modifier = strtolower($match[2][0] ?? '');

            // Apply scale modifier
            if (str_contains($modifier, 'million')) {
                $textValue *= 1_000_000;
            } elseif (str_contains($modifier, 'milliard') || str_contains($modifier, 'billion')) {
                $textValue *= 1_000_000_000;
            }

            if ($textValue == 0) continue;

            // Check if this number could be the same stat (within 50% to catch unit mismatches)
            $ratio = $textValue / $value;
            if ($ratio > 0.5 && $ratio < 2.0) {
                return ['value' => $textValue, 'raw' => $match[0][0]];
            }
        }

        return null;
    }

    /**
     * Extract text surrounding a keyword.
     */
    private function extractContext(string $text, string $keyword, int $radius): array
    {
        $contexts = [];
        $pos = 0;
        $lower = mb_strtolower($text);
        $keyLower = mb_strtolower($keyword);

        while (($pos = mb_strpos($lower, $keyLower, $pos)) !== false) {
            $start = max(0, $pos - $radius);
            $contexts[] = mb_substr($text, $start, $radius * 2);
            $pos += mb_strlen($keyword);
        }

        return $contexts;
    }

    /**
     * Format a number for display in messages.
     */
    private function formatNumber(float $value): string
    {
        if (abs($value) >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 1) . 'B';
        }
        if (abs($value) >= 1_000_000) {
            return number_format($value / 1_000_000, 1) . 'M';
        }
        return number_format($value, 0, ',', ' ');
    }
}
