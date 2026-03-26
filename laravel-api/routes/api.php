<?php

use App\Http\Controllers\AiResearchController;
use App\Http\Controllers\AutoCampaignController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparativeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AiPromptController;
use App\Http\Controllers\BusinessDirectoryController;
use App\Http\Controllers\ContentCampaignController;
use App\Http\Controllers\ContentEngineController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\GeneratedArticleController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\PublishingController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ContactTypeController;
use App\Http\Controllers\ContentMetricController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfluenceurController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Tracking & Unsubscribe (public, no auth)
Route::get('/track/open/{trackingId}', [\App\Http\Controllers\OutreachController::class, 'trackOpen']);
Route::get('/track/click/{trackingId}', [\App\Http\Controllers\OutreachController::class, 'trackClick']);
Route::get('/unsubscribe/{token}', [\App\Http\Controllers\OutreachController::class, 'unsubscribePage']);
Route::post('/unsubscribe/{token}', [\App\Http\Controllers\OutreachController::class, 'unsubscribeConfirm']);
Route::post('/webhooks/pmta/bounce', [\App\Http\Controllers\OutreachController::class, 'pmtaBounce']);

// Health check (public)
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Throwable $e) {
        $dbOk = false;
    }
    $redisOk = false;
    try {
        \Illuminate\Support\Facades\Cache::store('redis')->put('health', true, 10);
        $redisOk = \Illuminate\Support\Facades\Cache::store('redis')->get('health') === true;
    } catch (\Throwable $e) {
        $redisOk = false;
    }
    $status = $dbOk && $redisOk ? 200 : 503;
    return response()->json([
        'status' => $status === 200 ? 'ok' : 'degraded',
        'database' => $dbOk,
        'redis' => $redisOk,
        'timestamp' => now()->toIso8601String(),
    ], $status);
});

// Enums (public, cacheable) — contact_types from DB, rest from PHP Enums
Route::get('/enums', function () {
    return response()->json([
        'contact_types'     => \App\Models\ContactTypeModel::allActive(),
        'pipeline_statuses' => \App\Enums\PipelineStatus::cases(),
        'platforms'         => \App\Enums\Platform::cases(),
        'channels'          => \App\Enums\Channel::cases(),
    ]);
});

// Auth publique
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ============================================================
    // INFLUENCEURS (Core CRM — ex-Tracker + Mission Control fusion)
    // ============================================================
    Route::get('/influenceurs/reminders-pending', [InfluenceurController::class, 'remindersPending']);
    Route::get('/influenceurs/exports/csv', [ExportController::class, 'csv'])->middleware(['role:admin', 'throttle:10,1']);
    Route::get('/influenceurs/exports/excel', [ExportController::class, 'excel'])->middleware(['role:admin', 'throttle:10,1']);

    Route::get('/influenceurs', [InfluenceurController::class, 'index']);
    Route::post('/influenceurs', [InfluenceurController::class, 'store']);
    Route::get('/influenceurs/{influenceur}', [InfluenceurController::class, 'show']);
    Route::put('/influenceurs/{influenceur}', [InfluenceurController::class, 'update']);
    Route::post('/influenceurs/{influenceur}/rescrape', [InfluenceurController::class, 'rescrape']);
    Route::delete('/influenceurs/{influenceur}', [InfluenceurController::class, 'destroy']);

    // Contacts / Timeline
    Route::get('/influenceurs/{influenceur}/contacts', [ContactController::class, 'index']);
    Route::post('/influenceurs/{influenceur}/contacts', [ContactController::class, 'store']);
    Route::put('/influenceurs/{influenceur}/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/influenceurs/{influenceur}/contacts/{contact}', [ContactController::class, 'destroy']);

    // Outreach messages for specific influenceur
    Route::get('/influenceurs/{influenceur}/outreach', [EmailTemplateController::class, 'generateForInfluenceur']);

    // Rappels
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders/{reminder}/dismiss', [ReminderController::class, 'dismiss']);
    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'done']);

    // Statistiques
    Route::get('/stats', [StatsController::class, 'index']);

    // ============================================================
    // AI RESEARCH (NEW — from Mission Control)
    // ============================================================
    Route::prefix('ai-research')->group(function () {
        Route::get('/', [AiResearchController::class, 'index']);
        Route::post('/preview-prompt', [AiResearchController::class, 'previewPrompt']);
        Route::post('/launch', [AiResearchController::class, 'launch']);
        Route::get('/{session}', [AiResearchController::class, 'status']);
        Route::post('/{session}/import', [AiResearchController::class, 'import']);
        Route::post('/{session}/import-all', [AiResearchController::class, 'importAll']);
    });

    // ============================================================
    // EMAIL TEMPLATES & OUTREACH (NEW — from Mission Control)
    // ============================================================
    Route::prefix('templates')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index']);
        Route::get('/{template}', [EmailTemplateController::class, 'show']);
        Route::post('/{template}/preview', [EmailTemplateController::class, 'preview']);
        Route::post('/generate-batch', [EmailTemplateController::class, 'generateBatch']);

        // Admin-only write operations
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [EmailTemplateController::class, 'store']);
            Route::put('/{template}', [EmailTemplateController::class, 'update']);
            Route::delete('/{template}', [EmailTemplateController::class, 'destroy']);
        });
    });

    // ============================================================
    // CONTENT ENGINE (NEW — from Mission Control)
    // ============================================================
    Route::prefix('content-metrics')->group(function () {
        Route::get('/', [ContentMetricController::class, 'index']);
        Route::get('/today', [ContentMetricController::class, 'today']);
        Route::put('/today', [ContentMetricController::class, 'updateToday']);

        Route::middleware('role:admin')->group(function () {
            Route::post('/upsert', [ContentMetricController::class, 'upsert']);
        });
    });

    // ============================================================
    // JOURNAL (NEW — from Mission Control's activity journal)
    // ============================================================
    Route::prefix('journal')->group(function () {
        Route::get('/', [JournalController::class, 'index']);
        Route::post('/', [JournalController::class, 'store']);
        Route::get('/today', [JournalController::class, 'today']);
        Route::get('/weekly', [JournalController::class, 'weekly']);
    });

    // ============================================================
    // OBJECTIFS (existing Tracker)
    // ============================================================
    Route::get('/objectives', [ObjectiveController::class, 'index']);
    Route::get('/objectives/progress', [ObjectiveController::class, 'progress']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/objectives', [ObjectiveController::class, 'store']);
        Route::put('/objectives/{objective}', [ObjectiveController::class, 'update']);
        Route::delete('/objectives/{objective}', [ObjectiveController::class, 'destroy']);
    });

    // Stats chercheurs (admin uniquement)
    Route::get('/researchers/stats', [StatsController::class, 'researcherStats'])
        ->middleware('role:admin');

    // Couverture mondiale (admin uniquement)
    Route::get('/stats/coverage', [StatsController::class, 'coverage'])
        ->middleware('role:admin');

    // Progress par pays / type / langue (admin uniquement)
    Route::get('/stats/progress', [StatsController::class, 'progress'])
        ->middleware('role:admin');

    // Outreach / Prospection (admin)
    Route::prefix('outreach')->middleware('role:admin')->group(function () {
        Route::get('/config', [\App\Http\Controllers\OutreachController::class, 'configs']);
        Route::put('/config/{contactType}', [\App\Http\Controllers\OutreachController::class, 'updateConfig']);
        Route::post('/generate', [\App\Http\Controllers\OutreachController::class, 'generate']);
        Route::post('/generate/{influenceur}', [\App\Http\Controllers\OutreachController::class, 'generateOne']);
        Route::get('/review-queue', [\App\Http\Controllers\OutreachController::class, 'reviewQueue']);
        Route::post('/review/{outreachEmail}/approve', [\App\Http\Controllers\OutreachController::class, 'approve']);
        Route::post('/review/{outreachEmail}/reject', [\App\Http\Controllers\OutreachController::class, 'reject']);
        Route::post('/review/{outreachEmail}/edit', [\App\Http\Controllers\OutreachController::class, 'edit']);
        Route::post('/review/approve-batch', [\App\Http\Controllers\OutreachController::class, 'approveBatch']);
        Route::get('/stats', [\App\Http\Controllers\OutreachController::class, 'stats']);
        Route::get('/sequences', [\App\Http\Controllers\OutreachController::class, 'sequences']);
        Route::post('/sequences/{sequence}/pause', [\App\Http\Controllers\OutreachController::class, 'pauseSequence']);
        Route::post('/sequences/{sequence}/resume', [\App\Http\Controllers\OutreachController::class, 'resumeSequence']);
        Route::post('/sequences/{sequence}/stop', [\App\Http\Controllers\OutreachController::class, 'stopSequence']);
        Route::get('/domain-health', [\App\Http\Controllers\OutreachController::class, 'domainHealth']);
        Route::get('/alerts', [\App\Http\Controllers\OutreachController::class, 'alerts']);
    });

    // Quality verification (admin)
    Route::prefix('quality')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\QualityController::class, 'dashboard']);
        Route::get('/duplicates', [\App\Http\Controllers\QualityController::class, 'duplicates']);
        Route::post('/duplicates/{flag}/resolve', [\App\Http\Controllers\QualityController::class, 'resolveDuplicate']);
        Route::get('/type-flags', [\App\Http\Controllers\QualityController::class, 'typeFlags']);
        Route::post('/type-flags/{flag}/resolve', [\App\Http\Controllers\QualityController::class, 'resolveTypeFlag']);
        Route::post('/run-all', [\App\Http\Controllers\QualityController::class, 'runAll']);
    });

    // Matrice de couverture type × pays × langue (admin uniquement)
    Route::get('/stats/coverage-matrix', [StatsController::class, 'coverageMatrix'])
        ->middleware('role:admin');

    // Dashboard admin: vue globale + par type
    Route::get('/stats/admin-dashboard', [StatsController::class, 'adminDashboard'])
        ->middleware('role:admin');

    // ============================================================
    // SETTINGS — Scraper config etc. (admin only)
    // ============================================================
    Route::middleware('role:admin')->prefix('settings')->group(function () {
        Route::get('/scraper', [SettingsController::class, 'scraperConfig']);
        Route::put('/scraper', [SettingsController::class, 'updateScraperConfig']);
    });

    // ============================================================
    // AI PROMPTS (admin-editable)
    // ============================================================
    Route::middleware('role:admin')->prefix('ai-prompts')->group(function () {
        Route::get('/', [AiPromptController::class, 'index']);
        Route::get('/{contactType}', [AiPromptController::class, 'show']);
        Route::put('/', [AiPromptController::class, 'upsert']);
        Route::delete('/{contactType}', [AiPromptController::class, 'destroy']);
    });

    // ============================================================
    // AUTO CAMPAIGNS (automated country×type research)
    // ============================================================
    Route::middleware('role:admin')->prefix('auto-campaigns')->group(function () {
        Route::get('/config', [AutoCampaignController::class, 'config']);
        Route::get('/', [AutoCampaignController::class, 'index']);
        Route::post('/', [AutoCampaignController::class, 'store']);
        Route::get('/{campaign}', [AutoCampaignController::class, 'show']);
        Route::post('/{campaign}/pause', [AutoCampaignController::class, 'pause']);
        Route::post('/{campaign}/resume', [AutoCampaignController::class, 'resume']);
        Route::post('/{campaign}/cancel', [AutoCampaignController::class, 'cancel']);
        Route::post('/{campaign}/retry-failed', [AutoCampaignController::class, 'retryFailed']);
        Route::patch('/{campaign}/settings', [AutoCampaignController::class, 'updateSettings']);
        Route::delete('/{campaign}', [AutoCampaignController::class, 'destroy']);
        Route::post('/reorder', [AutoCampaignController::class, 'reorder']);
    });

    // ============================================================
    // DIRECTORIES / ANNUAIRES (admin only)
    // ============================================================
    Route::prefix('directories')->group(function () {
        Route::get('/', [DirectoryController::class, 'index']);
        Route::get('/stats', [DirectoryController::class, 'stats']);
        Route::get('/{directory}', [DirectoryController::class, 'show']);
        Route::get('/{directory}/contacts', [DirectoryController::class, 'contacts']);

        Route::middleware('role:admin')->group(function () {
            Route::post('/', [DirectoryController::class, 'store']);
            Route::put('/{directory}', [DirectoryController::class, 'update']);
            Route::delete('/{directory}', [DirectoryController::class, 'destroy']);
            Route::post('/{directory}/scrape', [DirectoryController::class, 'scrape']);
            Route::post('/batch-scrape', [DirectoryController::class, 'batchScrape']);
        });
    });

    // ============================================================
    // CONTACT TYPES (dynamic — managed from admin console)
    // ============================================================
    Route::get('/contact-types', [ContactTypeController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/contact-types', [ContactTypeController::class, 'store']);
        Route::put('/contact-types/{contactType}', [ContactTypeController::class, 'update']);
        Route::delete('/contact-types/{contactType}', [ContactTypeController::class, 'destroy']);
    });

    // ============================================================
    // CONTENT ENGINE (Scraper + Content Sources)
    // ============================================================
    Route::prefix('content')->middleware('role:admin')->group(function () {
        Route::get('/sources', [ContentEngineController::class, 'sources']);
        Route::post('/sources', [ContentEngineController::class, 'createSource']);
        Route::get('/sources/{slug}', [ContentEngineController::class, 'showSource']);
        Route::post('/sources/{slug}/scrape', [ContentEngineController::class, 'scrapeSource']);
        Route::post('/sources/{slug}/pause', [ContentEngineController::class, 'pauseSource']);
        Route::get('/sources/{slug}/countries', [ContentEngineController::class, 'countries']);
        Route::get('/sources/{slug}/countries/{countrySlug}', [ContentEngineController::class, 'countryArticles']);
        Route::get('/articles/{id}', [ContentEngineController::class, 'showArticle']);
        Route::get('/external-links', [ContentEngineController::class, 'externalLinks']);
        Route::get('/external-links/export', [ContentEngineController::class, 'exportLinks']);
        Route::get('/affiliate-domains', [ContentEngineController::class, 'affiliateDomains']);
        Route::post('/sources/{slug}/scrape-magazine', [ContentEngineController::class, 'scrapeMagazine']);
        Route::post('/sources/{slug}/scrape-services', [ContentEngineController::class, 'scrapeServices']);
        Route::post('/sources/{slug}/scrape-thematic', [ContentEngineController::class, 'scrapeThematic']);
        Route::post('/sources/{slug}/scrape-cities', [ContentEngineController::class, 'scrapeCities']);
        Route::get('/country-profiles', [ContentEngineController::class, 'countryProfiles']);
        Route::get('/country-profiles/{countrySlug}', [ContentEngineController::class, 'countryProfile']);
        Route::get('/stats', [ContentEngineController::class, 'stats']);
    });

    // ============================================================
    // BUSINESS DIRECTORY (Annuaire entreprises)
    // ============================================================
    Route::prefix('businesses')->middleware('role:admin')->group(function () {
        Route::get('/', [BusinessDirectoryController::class, 'index']);
        Route::get('/stats', [BusinessDirectoryController::class, 'stats']);
        Route::get('/countries', [BusinessDirectoryController::class, 'countries']);
        Route::get('/categories', [BusinessDirectoryController::class, 'categories']);
        Route::get('/export', [BusinessDirectoryController::class, 'export']);
        Route::get('/{id}', [BusinessDirectoryController::class, 'show']);
        Route::post('/scrape/{sourceSlug}', [BusinessDirectoryController::class, 'scrape']);
        Route::post('/scrape-details/{sourceSlug}', [BusinessDirectoryController::class, 'scrapeDetails']);
    });

    // ============================================================
    // ÉQUIPE (existing Tracker)
    // ============================================================
    Route::middleware('role:admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/team', [TeamController::class, 'store']);
        Route::put('/team/{user}', [TeamController::class, 'update']);
        Route::delete('/team/{user}', [TeamController::class, 'destroy']);
    });

    // ====================================================================
    // CONTENT ENGINE (Generation, SEO, Publishing, Campaigns, Costs, Media)
    // ====================================================================
    Route::middleware('role:admin')->prefix('content-gen')->group(function () {
        // Articles
        Route::get('/articles', [GeneratedArticleController::class, 'index']);
        Route::post('/articles', [GeneratedArticleController::class, 'store']);
        Route::get('/articles/{article}', [GeneratedArticleController::class, 'show']);
        Route::put('/articles/{article}', [GeneratedArticleController::class, 'update']);
        Route::delete('/articles/{article}', [GeneratedArticleController::class, 'destroy']);
        Route::post('/articles/{article}/publish', [GeneratedArticleController::class, 'publish']);
        Route::post('/articles/{article}/unpublish', [GeneratedArticleController::class, 'unpublish']);
        Route::post('/articles/{article}/duplicate', [GeneratedArticleController::class, 'duplicate']);
        Route::post('/articles/bulk-publish', [GeneratedArticleController::class, 'bulkPublish']);
        Route::delete('/articles/bulk-delete', [GeneratedArticleController::class, 'bulkDelete']);
        Route::get('/articles/{article}/versions', [GeneratedArticleController::class, 'versions']);
        Route::post('/articles/{article}/versions/{version}/restore', [GeneratedArticleController::class, 'restoreVersion']);

        // Comparatives
        Route::get('/comparatives', [ComparativeController::class, 'index']);
        Route::post('/comparatives', [ComparativeController::class, 'store']);
        Route::get('/comparatives/{comparative}', [ComparativeController::class, 'show']);
        Route::put('/comparatives/{comparative}', [ComparativeController::class, 'update']);
        Route::delete('/comparatives/{comparative}', [ComparativeController::class, 'destroy']);
        Route::post('/comparatives/{comparative}/publish', [ComparativeController::class, 'publish']);

        // Landing Pages
        Route::get('/landings', [LandingPageController::class, 'index']);
        Route::post('/landings', [LandingPageController::class, 'store']);
        Route::get('/landings/{landing}', [LandingPageController::class, 'show']);
        Route::put('/landings/{landing}', [LandingPageController::class, 'update']);
        Route::delete('/landings/{landing}', [LandingPageController::class, 'destroy']);
        Route::post('/landings/{landing}/publish', [LandingPageController::class, 'publish']);
        Route::post('/landings/{landing}/ctas', [LandingPageController::class, 'manageCtas']);

        // Press
        Route::prefix('press')->group(function () {
            Route::get('/releases', [PressController::class, 'releaseIndex']);
            Route::post('/releases', [PressController::class, 'releaseStore']);
            Route::get('/releases/{release}', [PressController::class, 'releaseShow']);
            Route::put('/releases/{release}', [PressController::class, 'releaseUpdate']);
            Route::delete('/releases/{release}', [PressController::class, 'releaseDestroy']);
            Route::post('/releases/{release}/publish', [PressController::class, 'releasePublish']);
            Route::get('/releases/{release}/export-pdf', [PressController::class, 'releaseExportPdf']);
            Route::get('/releases/{release}/export-word', [PressController::class, 'releaseExportWord']);

            Route::get('/dossiers', [PressController::class, 'dossierIndex']);
            Route::post('/dossiers', [PressController::class, 'dossierStore']);
            Route::get('/dossiers/{dossier}', [PressController::class, 'dossierShow']);
            Route::put('/dossiers/{dossier}', [PressController::class, 'dossierUpdate']);
            Route::delete('/dossiers/{dossier}', [PressController::class, 'dossierDestroy']);
            Route::post('/dossiers/{dossier}/items', [PressController::class, 'dossierAddItem']);
            Route::delete('/dossiers/{dossier}/items/{item}', [PressController::class, 'dossierRemoveItem']);
            Route::put('/dossiers/{dossier}/reorder', [PressController::class, 'dossierReorderItems']);
            Route::get('/dossiers/{dossier}/export-pdf', [PressController::class, 'dossierExportPdf']);
        });

        // Campaigns
        Route::get('/campaigns', [ContentCampaignController::class, 'index']);
        Route::post('/campaigns', [ContentCampaignController::class, 'store']);
        Route::get('/campaigns/{campaign}', [ContentCampaignController::class, 'show']);
        Route::put('/campaigns/{campaign}', [ContentCampaignController::class, 'update']);
        Route::delete('/campaigns/{campaign}', [ContentCampaignController::class, 'destroy']);
        Route::post('/campaigns/{campaign}/start', [ContentCampaignController::class, 'start']);
        Route::post('/campaigns/{campaign}/pause', [ContentCampaignController::class, 'pause']);
        Route::post('/campaigns/{campaign}/resume', [ContentCampaignController::class, 'resume']);
        Route::post('/campaigns/{campaign}/cancel', [ContentCampaignController::class, 'cancel']);
        Route::get('/campaigns/{campaign}/items', [ContentCampaignController::class, 'items']);

        // Generation
        Route::get('/generation/stats', [GenerationController::class, 'stats']);
        Route::get('/generation/history', [GenerationController::class, 'history']);
        Route::get('/generation/presets', [GenerationController::class, 'presetsIndex']);
        Route::post('/generation/presets', [GenerationController::class, 'presetStore']);
        Route::put('/generation/presets/{preset}', [GenerationController::class, 'presetUpdate']);
        Route::delete('/generation/presets/{preset}', [GenerationController::class, 'presetDelete']);
        Route::get('/generation/prompts', [GenerationController::class, 'promptsIndex']);
        Route::post('/generation/prompts', [GenerationController::class, 'promptStore']);
        Route::put('/generation/prompts/{prompt}', [GenerationController::class, 'promptUpdate']);
        Route::delete('/generation/prompts/{prompt}', [GenerationController::class, 'promptDelete']);
        Route::post('/generation/prompts/test', [GenerationController::class, 'testPrompt']);

        // SEO
        Route::get('/seo/dashboard', [SeoController::class, 'dashboard']);
        Route::post('/seo/analyze', [SeoController::class, 'analyze']);
        Route::get('/seo/hreflang-matrix', [SeoController::class, 'hreflangMatrix']);
        Route::get('/seo/internal-links-graph', [SeoController::class, 'internalLinksGraph']);
        Route::get('/seo/orphaned', [SeoController::class, 'orphanedArticles']);
        Route::post('/seo/fix-orphaned', [SeoController::class, 'fixOrphaned']);
        Route::get('/seo/sitemap.xml', [SeoController::class, 'sitemap']);

        // Publishing
        Route::get('/publishing/endpoints', [PublishingController::class, 'endpointsIndex']);
        Route::post('/publishing/endpoints', [PublishingController::class, 'endpointStore']);
        Route::put('/publishing/endpoints/{endpoint}', [PublishingController::class, 'endpointUpdate']);
        Route::delete('/publishing/endpoints/{endpoint}', [PublishingController::class, 'endpointDestroy']);
        Route::get('/publishing/queue', [PublishingController::class, 'queue']);
        Route::post('/publishing/queue/{item}/execute', [PublishingController::class, 'executeQueueItem']);
        Route::post('/publishing/queue/{item}/cancel', [PublishingController::class, 'cancelQueueItem']);
        Route::get('/publishing/endpoints/{endpoint}/schedule', [PublishingController::class, 'getSchedule']);
        Route::put('/publishing/endpoints/{endpoint}/schedule', [PublishingController::class, 'updateSchedule']);

        // Costs
        Route::get('/costs/overview', [CostController::class, 'overview']);
        Route::get('/costs/breakdown', [CostController::class, 'breakdown']);
        Route::get('/costs/trends', [CostController::class, 'trends']);

        // Media
        Route::get('/media/unsplash', [MediaController::class, 'searchUnsplash']);
        Route::post('/media/generate-image', [MediaController::class, 'generateImage']);
    });
});
