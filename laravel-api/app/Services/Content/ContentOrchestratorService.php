<?php

namespace App\Services\Content;

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
    ];

    public function getConfig(): array
    {
        $row = DB::table('content_orchestrator_config')->first();

        if (!$row) {
            return $this->defaultConfig();
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
            'today_generated' => $row->today_generated,
            'today_rss_generated' => $row->today_rss_generated ?? 0,
            'today_cost_cents' => $row->today_cost_cents,
            'telegram_alerts' => (bool) ($row->telegram_alerts ?? true),
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

        $update['updated_at'] = now();

        if ($row) {
            DB::table('content_orchestrator_config')->where('id', $row->id)->update($update);
        } else {
            $update['created_at'] = now();
            DB::table('content_orchestrator_config')->insert($update);
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

    private function defaultConfig(): array
    {
        return [
            'id' => null,
            'daily_target' => 20,
            'rss_daily_target' => 10,
            'auto_pilot' => false,
            'rss_auto_pilot' => true,
            'type_distribution' => [
                'qa' => 12, 'art_mots_cles' => 10, 'art_longues_traines' => 10,
                'guide' => 6, 'guide_expat' => 6, 'guide_vacances' => 6, 'guide_city' => 10,
                'comparative' => 8, 'affiliation' => 5,
                'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
                'outreach_avocats' => 3, 'outreach_expats' => 3,
                'testimonial' => 5, 'brand_content' => 5,
            ],
            'priority_countries' => ['FR','US','GB','ES','DE','TH','PT'],
            'status' => 'paused',
            'last_run_at' => null,
            'today_generated' => 0,
            'today_rss_generated' => 0,
            'today_cost_cents' => 0,
            'telegram_alerts' => true,
            'type_labels' => self::TYPE_LABELS,
        ];
    }
}
