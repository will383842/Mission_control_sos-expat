<?php

namespace App\Console\Commands;

use App\Services\Content\ContentOrchestratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Weekly warm-up scaling — automatically increases daily_target and
 * unlocks new content types as the site grows.
 *
 * Runs every Monday at 06:00 UTC via cron.
 * Based on Google indexing behavior (crawl budget increases with trust).
 *
 * Schedule:
 *   Week 1-2: 5/day — Fiches Pays Tier 1 + Art Mots Clés + Comparatifs
 *   Week 3-4: 8/day — + Fiches Villes + Longue Traîne
 *   Week 5-8: 12/day — + Q/R + Témoignages
 *   Week 9-12: 18/day — + Outreach + Affiliation
 *   Week 13-16: 25/day — + Brand Content, all types active
 *   Week 17+: 40/day — Full speed, all types, all countries
 */
class WarmupScaleCommand extends Command
{
    protected $signature = 'orchestrator:warmup-scale';
    protected $description = 'Automatically scale daily_target and unlock content types based on warm-up phase';

    private const PHASES = [
        // Phase 1: Piliers Tier 1 — établir l'autorité topicale
        ['week' => 2, 'target' => 5, 'rss' => 5, 'dist' => [
            'qa' => 0, 'art_mots_cles' => 15, 'art_longues_traines' => 10,
            'guide' => 20, 'guide_expat' => 15, 'guide_vacances' => 15,
            'guide_city' => 0, 'comparative' => 10, 'affiliation' => 0,
            'outreach_chatters' => 0, 'outreach_influenceurs' => 0, 'outreach_admin_groupes' => 0,
            'outreach_avocats' => 0, 'outreach_expats' => 0, 'testimonial' => 0, 'brand_content' => 0,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT']],

        // Phase 2: + Villes + Longue traîne + Témoignages
        ['week' => 4, 'target' => 8, 'rss' => 8, 'dist' => [
            'qa' => 5, 'art_mots_cles' => 12, 'art_longues_traines' => 10,
            'guide' => 12, 'guide_expat' => 10, 'guide_vacances' => 10,
            'guide_city' => 8, 'comparative' => 10, 'affiliation' => 5,
            'outreach_chatters' => 0, 'outreach_influenceurs' => 0, 'outreach_admin_groupes' => 0,
            'outreach_avocats' => 0, 'outreach_expats' => 0, 'testimonial' => 5, 'brand_content' => 3,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT']],

        // Phase 3: + Q/R + Affiliation + Brand Content
        ['week' => 8, 'target' => 15, 'rss' => 10, 'dist' => [
            'qa' => 8, 'art_mots_cles' => 10, 'art_longues_traines' => 8,
            'guide' => 8, 'guide_expat' => 6, 'guide_vacances' => 6,
            'guide_city' => 8, 'comparative' => 8, 'affiliation' => 5,
            'outreach_chatters' => 3, 'outreach_influenceurs' => 3, 'outreach_admin_groupes' => 2,
            'outreach_avocats' => 2, 'outreach_expats' => 2, 'testimonial' => 5, 'brand_content' => 4,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT', 'AE', 'JP']],

        // Phase 4: Tous types actifs — 16/16
        ['week' => 12, 'target' => 25, 'rss' => 15, 'dist' => [
            'qa' => 8, 'art_mots_cles' => 8, 'art_longues_traines' => 8,
            'guide' => 7, 'guide_expat' => 5, 'guide_vacances' => 5,
            'guide_city' => 8, 'comparative' => 7, 'affiliation' => 5,
            'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
            'outreach_avocats' => 3, 'outreach_expats' => 3, 'testimonial' => 5, 'brand_content' => 4,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT', 'AE', 'JP', 'SG', 'MA']],

        // Phase 5: Montée en puissance
        ['week' => 16, 'target' => 40, 'rss' => 20, 'dist' => [
            'qa' => 8, 'art_mots_cles' => 8, 'art_longues_traines' => 7,
            'guide' => 6, 'guide_expat' => 5, 'guide_vacances' => 5,
            'guide_city' => 8, 'comparative' => 7, 'affiliation' => 5,
            'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
            'outreach_avocats' => 3, 'outreach_expats' => 3, 'testimonial' => 5, 'brand_content' => 4,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT', 'AE', 'JP', 'SG', 'MA', 'BR', 'MX']],

        // Phase 6: Haute vitesse
        ['week' => 20, 'target' => 65, 'rss' => 25, 'dist' => [
            'qa' => 8, 'art_mots_cles' => 7, 'art_longues_traines' => 7,
            'guide' => 6, 'guide_expat' => 5, 'guide_vacances' => 5,
            'guide_city' => 8, 'comparative' => 7, 'affiliation' => 5,
            'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
            'outreach_avocats' => 3, 'outreach_expats' => 3, 'testimonial' => 5, 'brand_content' => 4,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT', 'AE', 'JP', 'SG', 'MA', 'BR', 'MX', 'NL', 'BE']],

        // Phase 7: Vitesse de croisière — 100/jour
        ['week' => 999, 'target' => 100, 'rss' => 30, 'dist' => [
            'qa' => 8, 'art_mots_cles' => 7, 'art_longues_traines' => 7,
            'guide' => 5, 'guide_expat' => 4, 'guide_vacances' => 4,
            'guide_city' => 8, 'comparative' => 7, 'affiliation' => 5,
            'outreach_chatters' => 4, 'outreach_influenceurs' => 4, 'outreach_admin_groupes' => 3,
            'outreach_avocats' => 3, 'outreach_expats' => 3, 'testimonial' => 5, 'brand_content' => 5,
        ], 'countries' => ['FR', 'US', 'GB', 'ES', 'DE', 'TH', 'PT', 'CA', 'AU', 'IT', 'AE', 'JP', 'SG', 'MA', 'BR', 'MX', 'NL', 'BE', 'CH', 'LU']],
    ];

    public function handle(): int
    {
        $config = DB::table('content_orchestrator_config')->first();
        if (!$config || $config->status !== 'running') {
            $this->info('Orchestrator not running — warmup skipped.');
            return 0;
        }

        // Calculate weeks since creation
        $createdAt = \Carbon\Carbon::parse($config->created_at);
        $weeksSinceStart = (int) $createdAt->diffInWeeks(now());

        $this->info("Warm-up: week {$weeksSinceStart} since orchestrator creation.");

        // Find current phase
        $phase = null;
        foreach (self::PHASES as $p) {
            if ($weeksSinceStart <= $p['week']) {
                $phase = $p;
                break;
            }
        }

        if (!$phase) {
            $this->info('Max phase reached.');
            return 0;
        }

        // Check if already at this level
        if ($config->daily_target >= $phase['target']) {
            $this->info("Already at target {$config->daily_target} (phase target: {$phase['target']}) — no change.");
            return 0;
        }

        // Scale up
        DB::table('content_orchestrator_config')->where('id', $config->id)->update([
            'daily_target' => $phase['target'],
            'rss_daily_target' => $phase['rss'],
            'type_distribution' => json_encode($phase['dist']),
            'priority_countries' => json_encode($phase['countries']),
            'updated_at' => now(),
        ]);

        $this->info("Scaled up: {$config->daily_target} → {$phase['target']} articles/day");
        $this->info("RSS: {$config->rss_daily_target} → {$phase['rss']}/day");
        $this->info("Countries: " . count($phase['countries']));

        // Telegram notification
        $orchestrator = app(ContentOrchestratorService::class);
        $orchestrator->sendTelegramAlert(
            "📈 Warm-up scale up (semaine {$weeksSinceStart})\n"
            . "Articles/jour: {$config->daily_target} → {$phase['target']}\n"
            . "RSS/jour: {$config->rss_daily_target} → {$phase['rss']}\n"
            . "Pays: " . implode(', ', $phase['countries']),
            'success'
        );

        Log::info("Orchestrator warmup scaled", [
            'week' => $weeksSinceStart,
            'old_target' => $config->daily_target,
            'new_target' => $phase['target'],
        ]);

        return 0;
    }
}
