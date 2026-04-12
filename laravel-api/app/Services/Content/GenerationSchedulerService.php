<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\Comparative;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — Intelligent generation scheduler.
 *
 * Controls the pace and order of content generation to:
 * - Appear natural to Google (10-20 articles/day max)
 * - Prioritize high-traffic countries first
 * - Balance content types (not all Q/R, not all guides)
 * - Track daily quotas and costs
 * - Prevent over-generation
 */
class GenerationSchedulerService
{
    // Rate limits per day — appear natural to search engines
    private const DAILY_LIMITS = [
        'total' => 50,          // Max articles per day (all types combined)
        'qa' => 10,             // Q/R are lightweight
        'news' => 15,           // RSS news
        'article' => 10,        // Deep articles (covers art_mots_cles + longues_traines)
        'guide' => 5,           // Country guides
        'guide_city' => 5,      // City guides
        'comparative' => 5,     // Comparatives
        'pain_point' => 8,      // Pain point — haute conversion
        'statistiques' => 5,    // Statistics
        'outreach' => 5,        // Outreach (all sub-types)
        'testimonial' => 3,     // Testimonials
        'affiliation' => 3,     // Affiliation comparatives
    ];

    // Priority countries (highest search volume first)
    private const PRIORITY_COUNTRIES = [
        // Tier 1: Highest traffic
        'FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT',
        // Tier 2: High traffic
        'CA', 'AU', 'IT', 'NL', 'BE', 'CH', 'MA', 'AE',
        // Tier 3: Growing markets
        'BR', 'MX', 'JP', 'SG', 'IN', 'TR', 'GR', 'HR',
        // Tier 4: Emerging
        'PH', 'VN', 'CO', 'CR', 'ID', 'MY', 'PL', 'RO',
    ];

    // Priority languages for translation order
    private const PRIORITY_LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

    private const CACHE_KEY_DAILY = 'generation_scheduler_daily';
    private const CACHE_KEY_COSTS = 'generation_scheduler_costs';

    /**
     * Check if we can generate more content today.
     *
     * @return array{allowed: bool, reason: string|null, remaining: int, stats: array}
     */
    public function canGenerate(string $contentType = 'article'): array
    {
        $today = $this->getTodayStats();
        $totalToday = $today['total'] ?? 0;
        $typeToday = $today['by_type'][$contentType] ?? 0;

        $totalLimit = self::DAILY_LIMITS['total'];
        $typeLimit = self::DAILY_LIMITS[$contentType] ?? self::DAILY_LIMITS['total'];

        if ($totalToday >= $totalLimit) {
            return [
                'allowed' => false,
                'reason' => "Limite journaliere atteinte: {$totalToday}/{$totalLimit} articles",
                'remaining' => 0,
                'stats' => $today,
            ];
        }

        if ($typeToday >= $typeLimit) {
            return [
                'allowed' => false,
                'reason' => "Limite type '{$contentType}' atteinte: {$typeToday}/{$typeLimit}",
                'remaining' => $typeLimit - $typeToday,
                'stats' => $today,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'remaining' => min($totalLimit - $totalToday, $typeLimit - $typeToday),
            'stats' => $today,
        ];
    }

    /**
     * Record a generation event (call after successful generation).
     */
    public function recordGeneration(string $contentType, int $costCents = 0): void
    {
        $today = $this->getTodayStats();
        $today['total'] = ($today['total'] ?? 0) + 1;
        $today['by_type'][$contentType] = ($today['by_type'][$contentType] ?? 0) + 1;
        $today['cost_cents'] = ($today['cost_cents'] ?? 0) + $costCents;
        $today['last_at'] = now()->toIso8601String();

        Cache::put(self::CACHE_KEY_DAILY, $today, now()->endOfDay());
    }

    /**
     * Get the next items to generate, ordered by priority.
     *
     * Returns a prioritized list of what should be generated next:
     * 1. High-traffic countries first (FR, US, GB, ES, DE, TH, PT)
     * 2. Content types balanced (alternate between types)
     * 3. Older pending items first
     *
     * @return array Array of items with {type, id, title, country, priority_score}
     */
    public function getNextBatch(int $batchSize = 10): array
    {
        $items = [];

        // 1. Pending comparatives (high SEO value)
        $comparatives = Comparative::where('status', 'draft')
            ->whereNull('content_html')
            ->orderByRaw("CASE " . $this->buildCountryPriorityCase('country') . " END ASC")
            ->limit($batchSize)
            ->get();

        foreach ($comparatives as $comp) {
            $items[] = [
                'type' => 'comparative',
                'id' => $comp->id,
                'title' => $comp->title,
                'country' => $comp->country,
                'priority_score' => $this->calculatePriority($comp->country, 'comparative'),
            ];
        }

        // 2. Pending articles by country priority
        $articles = GeneratedArticle::where('status', 'draft')
            ->whereNull('content_html')
            ->whereNull('parent_article_id')
            ->orderByRaw("CASE " . $this->buildCountryPriorityCase('country') . " END ASC")
            ->limit($batchSize)
            ->get();

        foreach ($articles as $art) {
            $items[] = [
                'type' => $art->content_type ?? 'article',
                'id' => $art->id,
                'title' => $art->title,
                'country' => $art->country,
                'priority_score' => $this->calculatePriority($art->country, $art->content_type),
            ];
        }

        // Sort by priority (highest first)
        usort($items, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        return array_slice($items, 0, $batchSize);
    }

    /**
     * Get priority translation languages for an article.
     * Returns ordered list: always FR first, then by traffic.
     */
    public function getTranslationOrder(?string $country = null): array
    {
        return self::PRIORITY_LANGUAGES;
    }

    /**
     * Get today's generation statistics.
     */
    public function getTodayStats(): array
    {
        return Cache::get(self::CACHE_KEY_DAILY, [
            'date' => now()->toDateString(),
            'total' => 0,
            'by_type' => [],
            'cost_cents' => 0,
            'last_at' => null,
        ]);
    }

    /**
     * Get generation statistics for a date range.
     */
    public function getStats(string $from, string $to): array
    {
        return [
            'articles' => GeneratedArticle::whereBetween('created_at', [$from, $to])
                ->selectRaw("content_type, status, COUNT(*) as count, SUM(generation_cost_cents) as cost")
                ->groupBy('content_type', 'status')
                ->get()
                ->toArray(),
            'comparatives' => Comparative::whereBetween('created_at', [$from, $to])
                ->selectRaw("status, COUNT(*) as count, SUM(generation_cost_cents) as cost")
                ->groupBy('status')
                ->get()
                ->toArray(),
            'daily_limit' => self::DAILY_LIMITS,
            'priority_countries' => self::PRIORITY_COUNTRIES,
        ];
    }

    // -----------------------------------------------------------------
    // PRIVATE
    // -----------------------------------------------------------------

    private function calculatePriority(?string $country, ?string $contentType): int
    {
        $score = 50; // base

        // Country Campaign bonus: current focus country gets +100 (always first)
        $focusCountry = $this->getCurrentFocusCountry();
        if ($focusCountry && $country && strtoupper($country) === $focusCountry) {
            $score += 100;
        }

        // Country priority bonus (0-40)
        if ($country) {
            $tier1 = array_slice(self::PRIORITY_COUNTRIES, 0, 7);
            $tier2 = array_slice(self::PRIORITY_COUNTRIES, 7, 8);
            $tier3 = array_slice(self::PRIORITY_COUNTRIES, 15, 8);

            if (in_array(strtoupper($country), $tier1, true)) $score += 40;
            elseif (in_array(strtoupper($country), $tier2, true)) $score += 25;
            elseif (in_array(strtoupper($country), $tier3, true)) $score += 10;
        }

        // Content type bonus (0-20)
        $typeBonus = match ($contentType) {
            'guide', 'fiches_pays' => 20,   // Country guides = pillar content
            'pain_point' => 18,             // Pain point = highest conversion (urgency)
            'comparative' => 15,            // Comparatives = high conversion
            'statistics' => 12,             // Statistics = authority (citable by media)
            'article' => 10,                // Deep articles = authority
            'qa' => 8,                      // Q/R = featured snippets
            'news' => 5,                    // News = freshness signal
            default => 5,
        };
        $score += $typeBonus;

        return $score;
    }

    private function buildCountryPriorityCase(string $column): string
    {
        $cases = [];
        foreach (self::PRIORITY_COUNTRIES as $i => $country) {
            $cases[] = "WHEN {$column} = '{$country}' THEN {$i}";
        }
        $cases[] = "ELSE 999";

        return implode(' ', $cases);
    }

    /**
     * Get the current Country Campaign focus country (below threshold from DB).
     * Cached for 10 minutes.
     */
    private function getCurrentFocusCountry(): ?string
    {
        return Cache::remember('country_campaign_focus', 600, function () {
            // Read campaign queue and threshold from DB (configurable via dashboard)
            $config = \Illuminate\Support\Facades\DB::table('content_orchestrator_config')->first();
            $campaignOrder = json_decode($config->campaign_country_queue ?? '[]', true);
            $threshold = (int) ($config->campaign_articles_per_country ?? 100);

            // Fallback: if queue is empty, use priority_countries
            if (empty($campaignOrder)) {
                $campaignOrder = json_decode($config->priority_countries ?? '[]', true);
            }

            if (empty($campaignOrder)) {
                return null;
            }

            $counts = GeneratedArticle::where('language', 'fr')
                ->whereIn('status', ['review', 'published', 'approved'])
                ->whereNotNull('country')
                ->where('word_count', '>', 0)
                ->groupBy('country')
                ->selectRaw('country, COUNT(*) as total')
                ->pluck('total', 'country')
                ->toArray();

            foreach ($campaignOrder as $code) {
                if (($counts[$code] ?? 0) < $threshold) {
                    return $code;
                }
            }

            return null;
        });
    }
}
