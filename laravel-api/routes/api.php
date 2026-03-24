<?php

use App\Http\Controllers\AiResearchController;
use App\Http\Controllers\AutoCampaignController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AiPromptController;
use App\Http\Controllers\DirectoryController;
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

    // Matrice de couverture type × pays × langue (admin uniquement)
    Route::get('/stats/coverage-matrix', [StatsController::class, 'coverageMatrix'])
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
    // ÉQUIPE (existing Tracker)
    // ============================================================
    Route::middleware('role:admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/team', [TeamController::class, 'store']);
        Route::put('/team/{user}', [TeamController::class, 'update']);
        Route::delete('/team/{user}', [TeamController::class, 'destroy']);
    });
});
