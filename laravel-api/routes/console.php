<?php

use App\Jobs\CheckRemindersJob;
use App\Jobs\FetchRssFeedsJob;
use App\Jobs\ProcessAutoCampaignJob;
use App\Jobs\ProcessEmailQueueJob;
use App\Jobs\ProcessSequencesJob;
use App\Jobs\RunDailyContentJob;
use App\Jobs\RunNewsGenerationJob;
use App\Jobs\RunQualityVerificationJob;
use App\Jobs\RunScraperBatchJob;
use App\Models\RssFeedItem;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckRemindersJob)->hourly();

// Daily database backup at 3:00 AM UTC
Schedule::command('backup:database')->dailyAt('03:00')->withoutOverlapping();

// ── SCRAPING DESACTIVE ──
// Web scraper Expat.com: DESACTIVE (donnees existantes gardees en base)
// Schedule::job(new RunScraperBatchJob)->hourly()->withoutOverlapping();

// ── GENERATION AUTO DESACTIVE ──
// Pipeline 14 sources: DESACTIVE (toute generation via onglets UI)
// Schedule::job(new RunDailyContentJob)->dailyAt('06:00')->withoutOverlapping(14400);

// Q/R Blog auto: génération quotidienne à 07:00 UTC
Schedule::command('qr:daily-generate')->dailyAt('07:00')->withoutOverlapping(7200);

// Statistics data: monthly fetch from World Bank/OECD/Eurostat (1st of month at 02:00 UTC)
Schedule::command('statistics:fetch-all')->monthlyOn(1, '02:00')->withoutOverlapping(7200);

// ── ACTIFS ──

// Auto campaigns: check for next task to process every minute
Schedule::job(new ProcessAutoCampaignJob)->everyMinute()->withoutOverlapping();

// Quality verification: run full pipeline every hour
Schedule::job(new RunQualityVerificationJob)->hourly()->withoutOverlapping();

// Outreach: send approved emails every 5 minutes
Schedule::job(new ProcessEmailQueueJob)->everyFiveMinutes()->withoutOverlapping();

// Outreach: advance sequences (generate next step) every 15 minutes
Schedule::job(new ProcessSequencesJob)->everyFifteenMinutes()->withoutOverlapping();

// Orchestrator: auto-pilot cycle every 15 minutes (06:00-22:00 UTC)
Schedule::job(new \App\Jobs\RunOrchestratorCycleJob)->everyFifteenMinutes()->withoutOverlapping(900);

// Orchestrator: warm-up scaling every Monday at 06:00 UTC
Schedule::command('orchestrator:warmup-scale')->weeklyOn(1, '06:00')->withoutOverlapping();

// Auto-discover new long-tail keywords every Wednesday + Saturday (feed the pipeline)
Schedule::command('keywords:discover --limit=30')->weeklyOn(3, '07:00')->withoutOverlapping(3600);
Schedule::command('keywords:discover --limit=30')->weeklyOn(6, '07:00')->withoutOverlapping(3600);

// Orchestrator: reset daily counters at midnight UTC
Schedule::call(function () {
    app(\App\Services\Content\ContentOrchestratorService::class)->resetDaily();
})->dailyAt('00:00');

// API Health Check: daily at 08:00 UTC — Telegram alert if any account is empty
Schedule::command('api:health-check')->dailyAt('08:00')->withoutOverlapping();

// RSS: fetch feeds every 4 hours (SEULE source de scraping active)
Schedule::job(new FetchRssFeedsJob)->everyFourHours()->withoutOverlapping(3600);

// News: auto-generate from RSS at 8:00 AM UTC
Schedule::job(new RunNewsGenerationJob)->dailyAt('08:00')->withoutOverlapping(7200);

// News stale recovery: remettre en pending les items bloqués en 'generating' depuis >30 min
// (cas de crash de worker pendant la génération)
Schedule::call(function () {
    $staleCount = RssFeedItem::where('status', 'generating')
        ->where('updated_at', '<', now()->subMinutes(30))
        ->update(['status' => 'pending', 'error_message' => null]);

    if ($staleCount > 0) {
        \Illuminate\Support\Facades\Log::info("News stale recovery: {$staleCount} items remis en pending");
    }
})->everyFifteenMinutes()->name('news-stale-recovery')->withoutOverlapping();
