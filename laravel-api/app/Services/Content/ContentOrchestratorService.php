<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Content Orchestrator — reads config from DB and manages auto-generation.
 *
 * Config is stored in content_orchestrator_config table:
 * - daily_target: how many articles per day
 * - type_distribution: % per content type
 * - auto_pilot: whether to auto-generate
 * - priority_countries: ordered list of priority countries
 */
class ContentOrchestratorService
{
    private const TYPE_LABELS = [
        'qa' => 'Q/R',
        'art_mots_cles' => 'Art Mots Cles',
        'art_longues_traines' => 'Art Longues Traines',
        'guide' => 'Fiches Pays General',
        'guide_expat' => 'Fiches Pays Expat',
        'guide_vacances' => 'Fiches Pays Vacances',
        'guide_city' => 'Fiches Villes',
        'comparative' => 'Comparatifs SEO',
        'affiliation' => 'Comparatifs Affiliation',
        'outreach_chatters' => 'Chatters',
        'outreach_influenceurs' => 'Influenceurs',
        'outreach_admin_groupes' => 'Admin Groupes',
        'outreach_avocats' => 'Partenaires Avocats',
        'outreach_expats' => 'Partenaires Expats',
        'testimonial' => 'Temoignages',
        'brand_content' => 'Brand Content',
        'statistiques' => 'Articles Statistiques',
        'pain_point' => 'Souffrances',
    ];

    public function getConfig(): array
    {
        $row = DB::table('content_orchestrator_config')->first();

        if (!$row) {
            return $this->defaultConfig();
        }

        // SELF-HEALING COUNTER: derive today_generated from a live COUNT() of
        // generated_articles instead of trusting the stored counter. This
        // protects against the historical bug where the orchestrator
        // incremented the counter at dispatch time even when generation
        // ultimately failed (OpenAI quota, timeout, etc.), causing the system
        // to wrongly think it had reached the daily target and pause until
        // midnight.
        //
        // We only count "real" articles: with content (word_count > 0 AND
        // content_html non-empty) and not translations (parent_article_id IS NULL).
        // Created today in the application timezone.
        try {
            $actualToday = DB::table('generated_articles')
                ->whereDate('created_at', now()->toDateString())
                ->where('word_count', '>', 0)
                ->whereNotNull('content_html')
                ->where('content_html', '!=', '')
                ->whereNull('parent_article_id')
                ->count();
        } catch (\Throwable $e) {
            // If the live query fails for any reason, fall back to the stored
            // counter so the system stays operational rather than crashing.
            \Illuminate\Support\Facades\Log::warning('ContentOrchestratorService: live counter query failed, falling back to stored value', [
                'error' => $e->getMessage(),
            ]);
            $actualToday = $row->today_generated;
        }

        return [
            'id' => $row->id,
            'daily_target' => $row->daily_target,
            'rss_daily_target' => $row->rss_daily_target ?? 10,
            'auto_pilot' => (bool) $row->auto_pilot,
            'rss_auto_pilot' => (bool) ($row->rss_auto_pilot ?? true),
            'type_distribution' => json_decode($row->type_distribution, true) ?? [],
            'priority_countries' => json_decode($row->priority_countries, true) ?? [],
            'status' => $row->status,
            'last_run_at' => $row->last_run_at,
            'today_generated' => $actualToday,
            'today_rss_generated' => $row->today_rss_generated ?? 0,
            'today_cost_cents' => $row->today_cost_cents,
            'telegram_alerts' => (bool) ($row->telegram_alerts ?? true),
            'campaign_country_queue' => json_decode($row->campaign_country_queue ?? '[]', true) ?: [],
            'campaign_articles_per_country' => (int) ($row->campaign_articles_per_country ?? 100),
            'type_labels' => self::TYPE_LABELS,
        ];
    }

    public function updateConfig(array $data): array
    {
        $row = DB::table('content_orchestrator_config')->first();

        $update = [];
        if (isset($data['daily_target'])) $update['daily_target'] = max(1, min(10000, (int) $data['daily_target']));
        if (isset($data['rss_daily_target'])) $update['rss_daily_target'] = max(0, min(10000, (int) $data['rss_daily_target']));
        if (isset($data['auto_pilot'])) $update['auto_pilot'] = (bool) $data['auto_pilot'];
        if (isset($data['rss_auto_pilot'])) $update['rss_auto_pilot'] = (bool) $data['rss_auto_pilot'];
        if (isset($data['type_distribution'])) $update['type_distribution'] = json_encode($data['type_distribution']);
        if (isset($data['priority_countries'])) $update['priority_countries'] = json_encode($data['priority_countries']);
        if (isset($data['status'])) $update['status'] = in_array($data['status'], ['running', 'paused', 'stopped']) ? $data['status'] : 'paused';
        if (isset($data['telegram_alerts'])) $update['telegram_alerts'] = (bool) $data['telegram_alerts'];
        if (isset($data['campaign_country_queue'])) $update['campaign_country_queue'] = json_encode($data['campaign_country_queue']);
        if (isset($data['campaign_articles_per_country'])) $update['campaign_articles_per_country'] = max(10, min(500, (int) $data['campaign_articles_per_country']));

        $update['updated_at'] = now();

        if ($row) {
            DB::table('content_orchestrator_config')->where('id', $row->id)->update($update);
        } else {
            $update['created_at'] = now();
            DB::table('content_orchestrator_config')->insert($update);
        }

        // Invalidate campaign caches when queue or threshold changes
        if (isset($update['campaign_country_queue']) || isset($update['campaign_articles_per_country'])) {
            Cache::forget('country_campaign_counts');
            Cache::forget('country_campaign_focus');
        }

        return $this->getConfig();
    }

    /**
     * Calculate how many articles of each type to generate today.
     */
    public function getDailyPlan(): array
    {
        $config = $this->getConfig();
        $target = $config['daily_target'];
        $distribution = $config['type_distribution'];
        $remaining = $target - $config['today_generated'];

        if ($remaining <= 0) {
            return ['target' => $target, 'generated' => $config['today_generated'], 'remaining' => 0, 'plan' => []];
        }

        $plan = [];
        foreach ($distribution as $type => $pct) {
            $count = max(0, (int) round($remaining * $pct / 100));
            if ($count > 0) {
                $plan[] = [
                    'type' => $type,
                    'label' => self::TYPE_LABELS[$type] ?? $type,
                    'count' => $count,
                    'pct' => $pct,
                ];
            }
        }

        return [
            'target' => $target,
            'generated' => $config['today_generated'],
            'remaining' => $remaining,
            'plan' => $plan,
            'auto_pilot' => $config['auto_pilot'],
            'status' => $config['status'],
        ];
    }

    /**
     * Record a generation (called by GenerationSchedulerService).
     */
    public function recordGeneration(int $costCents = 0): void
    {
        DB::table('content_orchestrator_config')
            ->limit(1)
            ->update([
                'today_generated' => DB::raw('today_generated + 1'),
                'today_cost_cents' => DB::raw("today_cost_cents + {$costCents}"),
                'last_run_at' => now(),
            ]);
    }

    /**
     * Upsert a log row for today (called after each successful generation cycle).
     */
    public function updateDailyLog(string $contentType, int $generated = 1, int $errors = 0, int $duplicatesBlocked = 0, int $costCents = 0): void
    {
        $today = now()->toDateString();

        DB::table('content_orchestrator_logs')
            ->upsert(
                [[
                    'log_date'          => $today,
                    'content_type'      => $contentType,
                    'generated'         => $generated,
                    'published'         => $generated,
                    'translated'        => $generated, // translation dispatched immediately
                    'errors'            => $errors,
                    'duplicates_blocked' => $duplicatesBlocked,
                    'avg_seo_score'     => 0,
                    'avg_aeo_score'     => 0,
                    'cost_cents'        => $costCents,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]],
                ['log_date', 'content_type'],
                [
                    'generated'          => DB::raw('content_orchestrator_logs.generated + ' . $generated),
                    'published'          => DB::raw('content_orchestrator_logs.published + ' . $generated),
                    'translated'         => DB::raw('content_orchestrator_logs.translated + ' . $generated),
                    'errors'             => DB::raw('content_orchestrator_logs.errors + ' . $errors),
                    'duplicates_blocked' => DB::raw('content_orchestrator_logs.duplicates_blocked + ' . $duplicatesBlocked),
                    'cost_cents'         => DB::raw('content_orchestrator_logs.cost_cents + ' . $costCents),
                    'updated_at'         => now(),
                ]
            );
    }

    /**
     * Get historical logs for the last N days, aggregated by date.
     */
    public function getLogs(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->toDateString();

        $rows = DB::table('content_orchestrator_logs')
            ->where('log_date', '>=', $from)
            ->orderBy('log_date', 'desc')
            ->get();

        // Aggregate by date
        $byDate = [];
        foreach ($rows as $row) {
            $d = $row->log_date;
            if (!isset($byDate[$d])) {
                $byDate[$d] = [
                    'date'               => $d,
                    'generated'          => 0,
                    'published'          => 0,
                    'translated'         => 0,
                    'errors'             => 0,
                    'duplicates_blocked' => 0,
                    'avg_seo_score'      => 0,
                    'avg_aeo_score'      => 0,
                    'cost_cents'         => 0,
                    'by_type'            => [],
                ];
            }
            $byDate[$d]['generated']          += $row->generated;
            $byDate[$d]['published']          += $row->published;
            $byDate[$d]['translated']         += $row->translated;
            $byDate[$d]['errors']             += $row->errors;
            $byDate[$d]['duplicates_blocked'] += $row->duplicates_blocked;
            $byDate[$d]['cost_cents']         += $row->cost_cents;
            $byDate[$d]['by_type'][] = [
                'type'      => $row->content_type,
                'label'     => self::TYPE_LABELS[$row->content_type] ?? $row->content_type,
                'generated' => $row->generated,
                'errors'    => $row->errors,
            ];
        }

        // Monthly totals
        $monthFrom = now()->startOfMonth()->toDateString();
        $monthTotals = DB::table('content_orchestrator_logs')
            ->where('log_date', '>=', $monthFrom)
            ->selectRaw('SUM(generated) as generated, SUM(errors) as errors, SUM(duplicates_blocked) as duplicates_blocked, SUM(cost_cents) as cost_cents')
            ->first();

        return [
            'days'          => array_values($byDate),
            'month_totals'  => $monthTotals ? (array) $monthTotals : ['generated' => 0, 'errors' => 0, 'duplicates_blocked' => 0, 'cost_cents' => 0],
        ];
    }

    /**
     * Check alert thresholds and return current alert states.
     */
    public function getAlerts(): array
    {
        $config = $this->getConfig();
        $today = now()->toDateString();

        $todayTotals = DB::table('content_orchestrator_logs')
            ->where('log_date', $today)
            ->selectRaw('SUM(errors) as errors, SUM(duplicates_blocked) as duplicates_blocked')
            ->first();

        $errors = (int) ($todayTotals->errors ?? 0);
        $quotaReached = $config['today_generated'] >= $config['daily_target'];
        $quotaPct = $config['daily_target'] > 0 ? ($config['today_generated'] / $config['daily_target'] * 100) : 0;

        return [
            'errors_today'    => ['value' => $errors,    'alert' => $errors > 5,       'threshold' => 5],
            'quota_unmet'     => ['value' => $quotaPct,  'alert' => !$quotaReached && $config['status'] === 'running', 'threshold' => 100],
            'duplicates'      => ['value' => (int) ($todayTotals->duplicates_blocked ?? 0), 'alert' => false, 'threshold' => null],
        ];
    }

    /**
     * Reset daily counters (called by daily cron at midnight).
     */
    public function resetDaily(): void
    {
        DB::table('content_orchestrator_config')
            ->limit(1)
            ->update([
                'today_generated' => 0,
                'today_rss_generated' => 0,
                'today_cost_cents' => 0,
            ]);
    }

    /**
     * Check if we can generate more today.
     */
    public function canGenerate(): bool
    {
        $config = $this->getConfig();
        return $config['status'] === 'running'
            && $config['auto_pilot']
            && $config['today_generated'] < $config['daily_target'];
    }

    /**
     * Send Telegram alert on error or completion.
     */
    public function sendTelegramAlert(string $message, string $level = 'info'): void
    {
        $config = $this->getConfig();
        if (!$config['telegram_alerts']) return;

        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id', '7560535072');
        if (!$botToken) return;

        $icon = match ($level) {
            'error' => '🚨',
            'warning' => '⚠️',
            'success' => '✅',
            default => 'ℹ️',
        };

        $text = "{$icon} *Content Orchestrator*\n\n{$message}\n\n_{$config['today_generated']}/{$config['daily_target']} articles — " . now()->format('H:i') . " UTC_";

        try {
            \Illuminate\Support\Facades\Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Orchestrator Telegram alert failed: {$e->getMessage()}");
        }
    }

    /**
     * Get campaign status: queue with per-country progress.
     */
    public function getCampaignStatus(): array
    {
        $config = $this->getConfig();
        $queue = $config['campaign_country_queue'];
        $threshold = $config['campaign_articles_per_country'];

        // Count articles per country (same criteria as getCurrentCampaignCountry)
        $counts = DB::table('generated_articles')
            ->where('language', 'fr')
            ->whereIn('status', ['review', 'published', 'approved'])
            ->whereNotNull('country')
            ->where('word_count', '>', 0)
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as total')
            ->pluck('total', 'country')
            ->toArray();

        $currentCountry = null;
        $queueItems = [];
        $completedCountries = [];

        foreach ($queue as $code) {
            $count = (int) ($counts[$code] ?? 0);
            $isComplete = $count >= $threshold;

            if ($isComplete) {
                $completedCountries[] = ['code' => $code, 'count' => $count];
            } else {
                if ($currentCountry === null) {
                    $currentCountry = $code;
                }
                $queueItems[] = [
                    'code' => $code,
                    'count' => $count,
                    'target' => $threshold,
                    'status' => $code === $currentCountry ? 'active' : 'pending',
                ];
            }
        }

        return [
            'queue' => $queueItems,
            'current_country' => $currentCountry,
            'articles_per_country' => $threshold,
            'completed_countries' => $completedCountries,
        ];
    }

    private function defaultConfig(): array
    {
        return [
            'id' => null,
            'daily_target' => 20,
            'rss_daily_target' => 10,
            'auto_pilot' => false,
            'rss_auto_pilot' => true,
            'type_distribution' => [
                'qa' => 12, 'art_mots_cles' => 10, 'art_longues_traines' => 8,
                'guide' => 6, 'guide_expat' => 6, 'guide_vacances' => 6, 'guide_city' => 9,
                'comparative' => 8, 'affiliation' => 5,
                'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
                'outreach_avocats' => 3, 'outreach_expats' => 3,
                'testimonial' => 5, 'brand_content' => 0,
                'statistiques' => 3, 'pain_point' => 5,
            ],
            'priority_countries' => ['FR','US','GB','ES','DE','TH','PT'],
            'status' => 'paused',
            'last_run_at' => null,
            'today_generated' => 0,
            'today_rss_generated' => 0,
            'today_cost_cents' => 0,
            'telegram_alerts' => true,
            'campaign_country_queue' => [],
            'campaign_articles_per_country' => 100,
            'type_labels' => self::TYPE_LABELS,
        ];
    }
}
