<?php

use App\Http\Controllers\AffiliateProgramController;
use App\Http\Controllers\AiResearchController;
use App\Http\Controllers\AutoCampaignController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparativeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AiPromptController;
use App\Http\Controllers\BusinessDirectoryController;
use App\Http\Controllers\ContentCampaignController;
use App\Http\Controllers\ContentContactController;
use App\Http\Controllers\ContentEngineController;
use App\Http\Controllers\ContentQuestionController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\GeneratedArticleController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\KeywordTrackingController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ContactsBaseController;
use App\Http\Controllers\ImportPipelineController;
use App\Http\Controllers\ScrapingDashboardController;
use App\Http\Controllers\JournalistController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\BlogToolsProxyController;
use App\Http\Controllers\PromoTemplateController;
use App\Http\Controllers\SondageController;
use App\Http\Controllers\PublishingController;
use App\Http\Controllers\QaEntryController;
use App\Http\Controllers\SeoChecklistController;
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
use App\Http\Controllers\TopicClusterController;
use App\Http\Controllers\ContentQualityController;
use App\Http\Controllers\QuestionClusterController;
use App\Http\Controllers\DailyScheduleController;
use App\Http\Controllers\TranslationBatchController;
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');

// ============================================================
// COUNTRY DIRECTORY — PUBLIC (lecture seule, pour sos-expat.com/annuaire)
// ============================================================
Route::prefix('public/country-directory')->group(function () {
    Route::get('/countries',             [\App\Http\Controllers\CountryDirectoryController::class, 'countries']);
    Route::get('/country/{countryCode}', [\App\Http\Controllers\CountryDirectoryController::class, 'country']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ============================================================
    // CONTACTS (Core CRM — table unifiée influenceurs)
    // ============================================================
    Route::get('/contacts/reminders-pending', [InfluenceurController::class, 'remindersPending']);
    Route::get('/contacts/check-email', [InfluenceurController::class, 'checkEmail']);
    Route::get('/contacts/exports/csv', [ExportController::class, 'csv'])->middleware(['role:admin', 'throttle:10,1']);
    Route::get('/contacts/exports/excel', [ExportController::class, 'excel'])->middleware(['role:admin', 'throttle:10,1']);

    Route::get('/contacts', [InfluenceurController::class, 'index']);
    Route::post('/contacts', [InfluenceurController::class, 'store']);
    Route::get('/contacts/{influenceur}', [InfluenceurController::class, 'show']);
    Route::put('/contacts/{influenceur}', [InfluenceurController::class, 'update']);
    Route::post('/contacts/{influenceur}/rescrape', [InfluenceurController::class, 'rescrape']);
    Route::delete('/contacts/{influenceur}', [InfluenceurController::class, 'destroy']);

    // Timeline d'interactions
    Route::get('/contacts/{influenceur}/timeline', [ContactController::class, 'index']);
    Route::post('/contacts/{influenceur}/timeline', [ContactController::class, 'store']);
    Route::put('/contacts/{influenceur}/timeline/{contact}', [ContactController::class, 'update']);
    Route::delete('/contacts/{influenceur}/timeline/{contact}', [ContactController::class, 'destroy']);

    // Outreach pour un contact
    Route::get('/contacts/{influenceur}/outreach', [EmailTemplateController::class, 'generateForInfluenceur']);

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

    // Nombre de contacts pour une catégorie/type donné
    Route::get('/stats/category-count', [StatsController::class, 'categoryCount']);

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
        Route::post('/sources/{slug}/scrape-full', [ContentEngineController::class, 'scrapeFull']);
        Route::get('/sources/{slug}/cities', [ContentEngineController::class, 'cities']);
        Route::get('/sources/{slug}/cities/{citySlug}', [ContentEngineController::class, 'cityArticles']);
        Route::get('/sources/{slug}/city-stats', [ContentEngineController::class, 'cityStats']);
        Route::get('/country-profiles', [ContentEngineController::class, 'countryProfiles']);
        Route::get('/country-profiles/{countrySlug}', [ContentEngineController::class, 'countryProfile']);
        Route::get('/city-profiles', [ContentEngineController::class, 'cityProfiles']);
        Route::get('/city-profiles/{citySlug}', [ContentEngineController::class, 'cityProfile']);
        Route::get('/stats', [ContentEngineController::class, 'stats']);
        Route::get('/data-cleanup', [ContentEngineController::class, 'dataCleanupStats']);
    });

    // ============================================================
    // GENERATION SOURCES (Sources pour l'outil de generation)
    // ============================================================
    Route::prefix('generation-sources')->middleware('role:admin')->group(function () {
        Route::get('/categories',         [\App\Http\Controllers\GenerationSourceController::class, 'categories']);
        Route::get('/stats',              [\App\Http\Controllers\GenerationSourceController::class, 'stats']);
        Route::get('/command-center',              [\App\Http\Controllers\GenerationSourceController::class, 'commandCenter']);
        Route::post('/trigger-all',                [\App\Http\Controllers\GenerationSourceController::class, 'triggerAll']);
        Route::match(['get','post'], '/scheduler-config', [\App\Http\Controllers\GenerationSourceController::class, 'schedulerConfig']);
        Route::patch('/{slug}/weight',             [\App\Http\Controllers\GenerationSourceController::class, 'weight']);
        Route::get('/items/{id}',         [\App\Http\Controllers\GenerationSourceController::class, 'itemDetail'])->where('id', '[0-9]+');
        Route::get('/{slug}/items',       [\App\Http\Controllers\GenerationSourceController::class, 'categoryItems']);
        Route::post('/{slug}/trigger',    [\App\Http\Controllers\GenerationSourceController::class, 'trigger']);
        Route::post('/{slug}/pause',      [\App\Http\Controllers\GenerationSourceController::class, 'pause']);
        Route::post('/{slug}/visibility', [\App\Http\Controllers\GenerationSourceController::class, 'visibility']);
        Route::patch('/{slug}/quota',     [\App\Http\Controllers\GenerationSourceController::class, 'quota']);
    });

    // ============================================================
    // COUNTRY DIRECTORY (Annuaire pays — liens officiels expatries)
    // ============================================================
    Route::prefix('country-directory')->middleware('role:admin')->group(function () {
        // Lecture
        Route::get('/countries',              [\App\Http\Controllers\CountryDirectoryController::class, 'countries']);
        Route::get('/nationalities',          [\App\Http\Controllers\CountryDirectoryController::class, 'nationalities']);
        Route::get('/stats',                  [\App\Http\Controllers\CountryDirectoryController::class, 'stats']);
        Route::get('/country/{countryCode}',  [\App\Http\Controllers\CountryDirectoryController::class, 'country']);
        Route::get('/embassies',              [\App\Http\Controllers\CountryDirectoryController::class, 'embassies']);
        Route::get('/export-blog',            [\App\Http\Controllers\CountryDirectoryController::class, 'exportForBlog']);
        // CRUD
        Route::post('/',         [\App\Http\Controllers\CountryDirectoryController::class, 'store']);
        Route::put('/{id}',      [\App\Http\Controllers\CountryDirectoryController::class, 'update']);
        Route::delete('/{id}',   [\App\Http\Controllers\CountryDirectoryController::class, 'destroy']);

        // Imports (lancement depuis la console admin)
        Route::prefix('imports')->group(function () {
            Route::get('/sources',       [\App\Http\Controllers\AnnuaireImportController::class, 'sources']);
            Route::post('/launch-all',   [\App\Http\Controllers\AnnuaireImportController::class, 'launchAll']);
            Route::get('/',              [\App\Http\Controllers\AnnuaireImportController::class, 'index']);
            Route::post('/',             [\App\Http\Controllers\AnnuaireImportController::class, 'create']);
            Route::get('/{id}',          [\App\Http\Controllers\AnnuaireImportController::class, 'show']);
            Route::post('/{id}/cancel',  [\App\Http\Controllers\AnnuaireImportController::class, 'cancel']);
            Route::delete('/{id}',       [\App\Http\Controllers\AnnuaireImportController::class, 'destroy']);
        });
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
    // Lawyers Directory (worldwide lawyer scraping)
    // ============================================================
    Route::prefix('lawyers')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\LawyerDirectoryController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\LawyerDirectoryController::class, 'stats']);
        Route::get('/countries', [\App\Http\Controllers\LawyerDirectoryController::class, 'countries']);
        Route::get('/sources', [\App\Http\Controllers\LawyerDirectoryController::class, 'sources']);
        Route::get('/export', [\App\Http\Controllers\LawyerDirectoryController::class, 'export']);
        Route::get('/{id}', [\App\Http\Controllers\LawyerDirectoryController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/scrape/{sourceSlug}', [\App\Http\Controllers\LawyerDirectoryController::class, 'scrape']);
        Route::post('/scrape-all', [\App\Http\Controllers\LawyerDirectoryController::class, 'scrapeAll']);
    });

    // ============================================================
    // Q&A (forum questions scraped from expat sites)
    // ============================================================
    Route::prefix('questions')->middleware('role:admin')->group(function () {
        Route::get('/', [ContentQuestionController::class, 'index']);
        Route::get('/stats', [ContentQuestionController::class, 'stats']);
        Route::put('/{id}/status', [ContentQuestionController::class, 'updateStatus']);
        Route::post('/scrape/{sourceSlug}', [ContentQuestionController::class, 'scrape']);
    });

    // ============================================================
    // CONTACTS (scraped people & partners)
    // ============================================================
    Route::prefix('contacts')->middleware('role:admin')->group(function () {
        Route::get('/', [ContentContactController::class, 'index']);
        Route::get('/stats', [ContentContactController::class, 'stats']);
        Route::get('/export', [ContentContactController::class, 'export']);
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
        Route::post('/articles', [GeneratedArticleController::class, 'store'])->middleware('throttle:2,5');
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
        Route::post('/comparatives', [ComparativeController::class, 'store'])->middleware('throttle:2,5');
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

        // Scraping Dashboard centralisé
        Route::prefix('scraping')->group(function () {
            Route::get('/status', [ScrapingDashboardController::class, 'status']);
            Route::post('/launch', [ScrapingDashboardController::class, 'launch']);
        });

        // Contacts Base Unifiée (toutes sources)
        Route::prefix('import-pipeline')->group(function () {
            Route::get('/stats', [ImportPipelineController::class, 'stats']);
            Route::post('/import/{source}', [ImportPipelineController::class, 'importSource']);
            Route::post('/import-all', [ImportPipelineController::class, 'importAll']);
        });

        Route::prefix('contacts-base')->group(function () {
            Route::get('/stats', [ContactsBaseController::class, 'stats']);
            Route::get('/contacts', [ContactsBaseController::class, 'contacts']);
            Route::get('/unified', [ContactsBaseController::class, 'unified']);
            Route::get('/unified/export', [ContactsBaseController::class, 'unifiedExport']);
            Route::get('/duplicates', [ContactsBaseController::class, 'duplicates']);
            Route::post('/deduplicate', [ContactsBaseController::class, 'deduplicateAuto']);
        });

        // Journalists / Press Contacts Scraper
        Route::prefix('journalists')->group(function () {
            Route::get('/stats', [JournalistController::class, 'stats']);
            Route::get('/contacts', [JournalistController::class, 'contacts']);
            Route::post('/contacts', [JournalistController::class, 'storeContact']);
            Route::put('/contacts/{id}', [JournalistController::class, 'updateContact']);
            Route::delete('/contacts/{id}', [JournalistController::class, 'deleteContact']);
            Route::get('/contacts/export', [JournalistController::class, 'exportContacts']);
            Route::get('/publications', [JournalistController::class, 'publications']);
            Route::post('/publications', [JournalistController::class, 'storePublication']);
            Route::put('/publications/{id}/config', [JournalistController::class, 'updatePublicationConfig']);
            Route::post('/publications/scrape', [JournalistController::class, 'scrapePublications']);
            Route::post('/publications/scrape-authors', [JournalistController::class, 'scrapeAuthors']);
            Route::post('/publications/infer-emails', [JournalistController::class, 'inferEmails']);
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
        Route::post('/generation/auto-pipeline', [GenerationController::class, 'runAutoPipeline'])->middleware('throttle:2,5');
        Route::get('/generation/pipeline-status', [GenerationController::class, 'pipelineStatus']);

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

        // Topic Clusters
        Route::get('/clusters', [TopicClusterController::class, 'index']);
        Route::get('/clusters/{cluster}', [TopicClusterController::class, 'show']);
        Route::post('/clusters/auto-cluster', [TopicClusterController::class, 'autoCluster'])->middleware('throttle:1,10');
        Route::post('/clusters/{cluster}/brief', [TopicClusterController::class, 'generateBrief']);
        Route::post('/clusters/{cluster}/generate', [TopicClusterController::class, 'generateArticle']);
        Route::post('/clusters/{cluster}/generate-qa', [TopicClusterController::class, 'generateQa']);
        Route::delete('/clusters/{cluster}', [TopicClusterController::class, 'destroy']);

        // Q&A
        Route::get('/qa', [QaEntryController::class, 'index']);
        Route::post('/qa', [QaEntryController::class, 'store']);
        Route::get('/qa/{qa}', [QaEntryController::class, 'show']);
        Route::put('/qa/{qa}', [QaEntryController::class, 'update']);
        Route::delete('/qa/{qa}', [QaEntryController::class, 'destroy']);
        Route::post('/qa/{qa}/publish', [QaEntryController::class, 'publish']);
        Route::post('/qa/generate-from-article', [QaEntryController::class, 'generateFromArticle']);
        Route::post('/qa/generate-from-paa', [QaEntryController::class, 'generateFromPaa']);
        Route::post('/qa/bulk-publish', [QaEntryController::class, 'bulkPublish']);

        // Keywords
        Route::get('/keywords', [KeywordTrackingController::class, 'index']);
        Route::get('/keywords/gaps', [KeywordTrackingController::class, 'gaps']);
        Route::get('/keywords/cannibalization', [KeywordTrackingController::class, 'cannibalization']);
        Route::get('/keywords/article/{article}', [KeywordTrackingController::class, 'articleKeywords']);

        // Translation Batches
        Route::get('/translations', [TranslationBatchController::class, 'index']);
        Route::get('/translations/overview', [TranslationBatchController::class, 'overview']);
        Route::post('/translations/start', [TranslationBatchController::class, 'start']);
        Route::get('/translations/{batch}', [TranslationBatchController::class, 'show']);
        Route::post('/translations/{batch}/pause', [TranslationBatchController::class, 'pause']);
        Route::post('/translations/{batch}/resume', [TranslationBatchController::class, 'resume']);
        Route::post('/translations/{batch}/cancel', [TranslationBatchController::class, 'cancel']);

        // SEO Checklist
        Route::get('/seo/checklist/{article}', [SeoChecklistController::class, 'show']);
        Route::post('/seo/checklist/{article}/evaluate', [SeoChecklistController::class, 'evaluate']);
        Route::get('/seo/checklist/{article}/failed', [SeoChecklistController::class, 'failedChecks']);

        // Quality Analysis
        Route::get('/quality/{article}/readability', [ContentQualityController::class, 'readability']);
        Route::get('/quality/{article}/tone', [ContentQualityController::class, 'tone']);
        Route::get('/quality/{article}/brand', [ContentQualityController::class, 'brand']);
        Route::get('/quality/{article}/plagiarism', [ContentQualityController::class, 'plagiarism']);
        Route::get('/quality/{article}/fact-check', [ContentQualityController::class, 'factCheck']);
        Route::post('/quality/{article}/improve', [ContentQualityController::class, 'improve']);
        Route::get('/quality/{article}/full-audit', [ContentQualityController::class, 'fullAudit']);

        // Question Clusters
        Route::get('/question-clusters', [QuestionClusterController::class, 'index']);
        Route::get('/question-clusters/stats', [QuestionClusterController::class, 'stats']);
        Route::post('/question-clusters/auto-cluster', [QuestionClusterController::class, 'autoCluster'])->middleware('throttle:1,10');
        Route::get('/question-clusters/{cluster}', [QuestionClusterController::class, 'show']);
        Route::post('/question-clusters/{cluster}/generate-qa', [QuestionClusterController::class, 'generateQa']);
        Route::post('/question-clusters/{cluster}/generate-article', [QuestionClusterController::class, 'generateArticle']);
        Route::post('/question-clusters/{cluster}/generate-both', [QuestionClusterController::class, 'generateBoth']);
        Route::post('/question-clusters/{cluster}/skip', [QuestionClusterController::class, 'skip']);
        Route::delete('/question-clusters/{cluster}', [QuestionClusterController::class, 'destroy']);

        // Daily Content Schedule
        Route::get('/schedule', [DailyScheduleController::class, 'getSchedule']);
        Route::put('/schedule', [DailyScheduleController::class, 'updateSchedule']);
        Route::get('/schedule/history', [DailyScheduleController::class, 'getHistory']);
        Route::post('/schedule/run-now', [DailyScheduleController::class, 'runNow'])->middleware('throttle:2,5');
        Route::post('/schedule/custom-titles', [DailyScheduleController::class, 'addCustomTitles']);

        // Taxonomy distribution (percentage-based)
        Route::get('/taxonomy-distribution', [DailyScheduleController::class, 'getTaxonomyDistribution']);
        Route::put('/taxonomy-distribution', [DailyScheduleController::class, 'updateTaxonomyDistribution']);

        // Publication stats & rate control
        Route::get('/publication-stats', [DailyScheduleController::class, 'getPublicationStats']);
        Route::put('/publication-rate', [DailyScheduleController::class, 'updatePublicationRate']);

        // Quality monitoring
        Route::get('/quality-monitoring', [DailyScheduleController::class, 'getQualityMonitoring']);
        Route::post('/articles/{id}/reject', [DailyScheduleController::class, 'rejectArticle']);
        Route::post('/articles/{id}/approve', [DailyScheduleController::class, 'approveArticle']);
    });

    // ── Affiliés ─────────────────────────────────────────────────────────────
    Route::prefix('affiliates')->group(function () {
        Route::get('/stats',                                    [AffiliateProgramController::class, 'globalStats']);
        Route::get('/',                                         [AffiliateProgramController::class, 'index']);
        Route::post('/',                                        [AffiliateProgramController::class, 'store']);
        Route::get('/{affiliateProgram}',                       [AffiliateProgramController::class, 'show']);
        Route::put('/{affiliateProgram}',                       [AffiliateProgramController::class, 'update']);
        Route::delete('/{affiliateProgram}',                    [AffiliateProgramController::class, 'destroy']);
        Route::get('/{affiliateProgram}/earnings',              [AffiliateProgramController::class, 'getEarnings']);
        Route::post('/{affiliateProgram}/earnings',             [AffiliateProgramController::class, 'addEarning']);
    });

    // ── Promo Templates (admin only) ────────────────────────────────────────
    Route::middleware('role:admin')->prefix('promo-templates')->group(function () {
        Route::get('/',                           [PromoTemplateController::class, 'index']);
        Route::post('/',                          [PromoTemplateController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/{promoTemplate}',            [PromoTemplateController::class, 'show']);
        Route::put('/{promoTemplate}',            [PromoTemplateController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/{promoTemplate}',         [PromoTemplateController::class, 'destroy'])->middleware('throttle:20,1');
        Route::patch('/reorder',                  [PromoTemplateController::class, 'reorder'])->middleware('throttle:30,1');
    });

    // ── Outils Visiteurs (admin only, proxy → Blog) ─────────────────────────
    Route::middleware('role:admin')->prefix('blog/tools')->group(function () {
        Route::get('/',              [BlogToolsProxyController::class, 'index']);
        Route::get('/leads',         [BlogToolsProxyController::class, 'leads']);
        Route::post('/{id}/toggle',  [BlogToolsProxyController::class, 'toggle'])->middleware('throttle:20,1');
    });

    // ── Sondages (admin only) ───────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('sondages')->group(function () {
        Route::get('/',                      [SondageController::class, 'index']);
        Route::post('/',                     [SondageController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/{sondage}',             [SondageController::class, 'show']);
        Route::put('/{sondage}',             [SondageController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/{sondage}',          [SondageController::class, 'destroy'])->middleware('throttle:10,1');
        Route::post('/{sondage}/sync',       [SondageController::class, 'syncToBlog'])->middleware('throttle:3,1');
        Route::get('/{sondage}/resultats',   [SondageController::class, 'resultats']);
    });
});
