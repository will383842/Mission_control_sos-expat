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

// News: auto-generate from RSS at 06:00 and 14:00 UTC (two batches per day)
Schedule::job(new RunNewsGenerationJob)->dailyAt('06:00')->withoutOverlapping(7200);
Schedule::job(new RunNewsGenerationJob)->dailyAt('14:00')->withoutOverlapping(7200);

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

// News failed retry: remettre les items échoués en pending (1 retry par jour à 12:00)
Schedule::call(function () {
    $failedCount = RssFeedItem::where('status', 'failed')
        ->where('relevance_score', '>=', 50)
        ->where('updated_at', '>=', now()->subDays(3))
        ->update(['status' => 'pending', 'error_message' => null]);

    if ($failedCount > 0) {
        \Illuminate\Support\Facades\Log::info("News failed retry: {$failedCount} items remis en pending");
    }
})->dailyAt('12:00')->name('news-failed-retry')->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// NEWS RELEVANCE RETRY (hourly, self-healing)
// ══════════════════════════════════════════════════════════════════════
// When the relevance scoring API call fails (OpenAI quota, transient
// network/timeout, JSON parse error), RssFeedItem ends up with:
//   status='pending', relevance_score=null, error_message='Relevance ...'
// FetchRssFeedsJob only evaluates items it has just fetched, so these
// orphans were stuck forever and never made it into RunNewsGenerationJob
// (which filters whereNotNull('relevance_score')).
//
// This cron picks up such orphans every hour and retries the relevance
// scoring. Batch limited to 50 to avoid bursts on OpenAI quota recovery.
Schedule::call(function () {
    $items = \App\Models\RssFeedItem::with('feed')
        ->where('status', 'pending')
        ->whereNull('relevance_score')
        ->where(function ($q) {
            $q->whereNotNull('error_message')
              ->where(function ($qq) {
                  $qq->where('error_message', 'LIKE', '%Relevance%')
                     ->orWhere('error_message', 'LIKE', '%relevance%')
                     ->orWhere('error_message', 'LIKE', '%API call failed%');
              });
        })
        ->orWhere(function ($q) {
            // Also catch items with no error_message but stuck without score for >2h
            $q->where('status', 'pending')
              ->whereNull('relevance_score')
              ->whereNull('error_message')
              ->where('created_at', '<', now()->subHours(2));
        })
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();

    if ($items->isEmpty()) return;

    $filter = app(\App\Services\News\RelevanceFilterService::class);
    $recovered = 0;
    $stillFailed = 0;
    foreach ($items as $item) {
        try {
            $filter->evaluate($item);
            $item->refresh();
            if ($item->relevance_score !== null) {
                $recovered++;
            } else {
                $stillFailed++;
            }
        } catch (\Throwable $e) {
            $stillFailed++;
            \Illuminate\Support\Facades\Log::warning("News relevance retry: failed item #{$item->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    \Illuminate\Support\Facades\Log::info("News relevance retry: {$recovered} recovered, {$stillFailed} still failed (batch of {$items->count()})");
})->hourly()->name('news-relevance-retry')->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// CLEANUP EMPTY ARTICLE SKELETONS (every 30 min)
// ══════════════════════════════════════════════════════════════════════
// ArticleGenerationService::generate() now rethrows on failure and the
// catch block deletes empty skeletons before rethrowing. This cron is the
// belt-and-braces safety net: if anything ever fails to clean up an empty
// article (race condition, partial write, manual SQL, etc.) this catches
// it within 30 minutes and prevents pollution of the publication queue.
//
// We only target rows that:
//   - have no content (word_count = 0 AND content_html empty/null)
//   - are not translations (parent_article_id IS NULL — translations are
//     created by the translation worker and may temporarily be empty)
//   - are at least 30 minutes old (give the active generation job enough
//     time to complete normally before we touch the row)
Schedule::call(function () {
    $deleted = \Illuminate\Support\Facades\DB::table('generated_articles')
        ->where('word_count', 0)
        ->where(function ($q) {
            $q->whereNull('content_html')->orWhere('content_html', '');
        })
        ->whereNull('parent_article_id')
        ->where('created_at', '<', now()->subMinutes(30))
        ->delete();

    if ($deleted > 0) {
        \Illuminate\Support\Facades\Log::info("Cleanup empty drafts: deleted {$deleted} skeleton rows");
    }
})->everyThirtyMinutes()->name('cleanup-empty-drafts')->withoutOverlapping();

// Source items stale recovery: reset items stuck in 'processing' for >20 min
// (happens when GenerateArticleJob fails but GenerateFromSourceJob already marked the item)
Schedule::call(function () {
    $staleCount = \Illuminate\Support\Facades\DB::table('generation_source_items')
        ->where('processing_status', 'processing')
        ->where('updated_at', '<', now()->subMinutes(20))
        ->update(['processing_status' => 'ready', 'updated_at' => now()]);

    if ($staleCount > 0) {
        \Illuminate\Support\Facades\Log::info("Source items stale recovery: {$staleCount} items remis en ready");
    }
})->everyFifteenMinutes()->name('source-items-stale-recovery')->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// PUBLICATION ENGINE (DB-driven, every 2 min)
// This is THE definitive fix for lost Redis jobs during deploys.
// Instead of relying on Redis delayed jobs, we scan the DB every 2 min
// and publish everything that's ready. No Redis dependency.
// ══════════════════════════════════════════════════════════════════════
Schedule::call(function () {
    $db = \Illuminate\Support\Facades\DB::class;
    $log = \Illuminate\Support\Facades\Log::class;

    // ── 1. Re-dispatch pending queue items (lost Redis jobs) ──
    $staleItems = $db::table('publication_queue')
        ->where('status', 'pending')
        ->where('updated_at', '<', now()->subMinutes(3))
        ->get(['id']);

    foreach ($staleItems as $item) {
        try {
            // NO delay — dispatch immediately, let the job handle schedule/rate checks
            \App\Jobs\PublishContentJob::dispatch($item->id);
        } catch (\Throwable $e) {
            $log::warning("Pub engine: failed to dispatch queue #{$item->id}", ['error' => $e->getMessage()]);
        }
    }

    // ── 2. Find articles ready to publish but NOT in queue at all ──
    $orphans = $db::table('generated_articles as ga')
        ->leftJoin('publication_queue as pq', function ($join) {
            $join->on('pq.publishable_id', '=', 'ga.id')
                 ->where('pq.publishable_type', '=', 'App\\Models\\GeneratedArticle');
        })
        ->whereNull('pq.id')
        ->where('ga.status', 'review')
        ->where('ga.quality_score', '>=', 60)
        ->whereNull('ga.parent_article_id')
        ->where('ga.word_count', '>', 0)
        ->whereNotNull('ga.content_html')
        ->where('ga.created_at', '<', now()->subMinutes(6)) // wait for translations
        ->pluck('ga.id');

    if ($orphans->isNotEmpty()) {
        $endpoint = $db::table('publishing_endpoints')
            ->where('is_default', true)->where('is_active', true)->first();

        if ($endpoint) {
            foreach ($orphans as $articleId) {
                $queueId = $db::table('publication_queue')->insertGetId([
                    'publishable_type' => 'App\\Models\\GeneratedArticle',
                    'publishable_id'   => $articleId,
                    'endpoint_id'      => $endpoint->id,
                    'status'           => 'pending',
                    'priority'         => 'default',
                    'max_attempts'     => 5,
                    'attempts'         => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                \App\Jobs\PublishContentJob::dispatch($queueId);
            }
            $log::info("Pub engine: {$orphans->count()} orphan articles queued for publication");
        }
    }

    $total = $staleItems->count() + $orphans->count();
    if ($total > 0) {
        $log::info("Pub engine: {$staleItems->count()} stale re-dispatched, {$orphans->count()} orphans queued");
    }
})->everyTwoMinutes()->name('publication-engine')->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// LANDING GENERATOR — auto-pilot quotidien à 05:00 UTC
// Lance RunLandingCampaignJob pour les 4 audiences (clients/lawyers/helpers/matching).
// Génère en FR uniquement ; les autres langues se lancent manuellement depuis l'UI.
// withoutOverlapping(7200) = lock 2h max pour éviter les doublons si le job tourne long.
// ══════════════════════════════════════════════════════════════════════
Schedule::job(new \App\Jobs\RunLandingCampaignJob)->dailyAt('05:00')->withoutOverlapping(7200);

// ══════════════════════════════════════════════════════════════════════
// LINKEDIN FILL CALENDAR — quotidien à 06:00 UTC
// Maintient 30 jours d'avance de posts LinkedIn.
// Pour chaque jour ouvré sans post, génère automatiquement 1 post.
// Rotation éditoriale déterministe par numéro de semaine ISO.
// ══════════════════════════════════════════════════════════════════════
Schedule::command('linkedin:fill-calendar')->dailyAt('06:00')->withoutOverlapping(3600);

// ══════════════════════════════════════════════════════════════════════
// LINKEDIN TOKEN HEALTH CHECK — quotidien à 08:00 UTC
// Alerte Telegram si token expire dans < 14j (sans refresh) ou < 3j (avec refresh).
// ══════════════════════════════════════════════════════════════════════
Schedule::command('linkedin:check-token')->dailyAt('08:00');

// ══════════════════════════════════════════════════════════════════════
// LINKEDIN STALE GENERATING RECOVERY — toutes les 30 minutes
// Remet en "failed" les posts bloqués en "generating" depuis >45 min
// (worker crashé ou job perdu dans Redis lors d'un redémarrage).
// ══════════════════════════════════════════════════════════════════════
Schedule::call(function () {
    $stale = \App\Models\LinkedInPost::where('status', 'generating')
        ->where('updated_at', '<', now()->subMinutes(45))
        ->get();

    foreach ($stale as $post) {
        $post->update([
            'status'        => 'failed',
            'error_message' => 'Generating timeout — job lost. Will be regenerated by fill-calendar.',
        ]);
        \Illuminate\Support\Facades\Log::warning('linkedin:stale-recovery: post marked failed', [
            'post_id'     => $post->id,
            'day_type'    => $post->day_type,
            'source_type' => $post->source_type,
        ]);
    }

    if ($stale->count() > 0) {
        \Illuminate\Support\Facades\Log::info("linkedin:stale-recovery: {$stale->count()} posts reset to failed");
    }
})->everyThirtyMinutes()->name('linkedin-stale-recovery')->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// LINKEDIN AUTO-PUBLISH — toutes les 5 minutes
// Publie les posts en status='scheduled' dont scheduled_at <= now().
// Optimal posting times : 07h30 et 12h15 heure locale → planifier depuis l'UI.
// ══════════════════════════════════════════════════════════════════════
Schedule::command('linkedin:auto-publish')->everyFiveMinutes()->withoutOverlapping();

// ══════════════════════════════════════════════════════════════════════
// LINKEDIN CHECK COMMENTS — toutes les 15 minutes
// Poll les commentaires sur les posts publiés (30 derniers jours).
// Nouveaux commentaires → Telegram avec 3 variantes de réponse + boutons.
// ══════════════════════════════════════════════════════════════════════
Schedule::command('linkedin:check-comments')->everyFifteenMinutes()->withoutOverlapping();
