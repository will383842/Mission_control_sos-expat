<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfluenceurController;
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

// Auth publique
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // IMPORTANT : routes statiques déclarées AVANT la route paramétrée {influenceur}
    Route::get('/influenceurs/reminders-pending', [InfluenceurController::class, 'remindersPending']);
    Route::get('/influenceurs/exports/csv', [ExportController::class, 'csv'])->middleware(['role:admin', 'throttle:10,1']);
    Route::get('/influenceurs/exports/excel', [ExportController::class, 'excel'])->middleware(['role:admin', 'throttle:10,1']);

    // CRUD influenceurs (researchers can create/read/update via controller scoping)
    Route::get('/influenceurs', [InfluenceurController::class, 'index']);
    Route::post('/influenceurs', [InfluenceurController::class, 'store']);
    Route::get('/influenceurs/{influenceur}', [InfluenceurController::class, 'show']);
    Route::put('/influenceurs/{influenceur}', [InfluenceurController::class, 'update']);
    Route::delete('/influenceurs/{influenceur}', [InfluenceurController::class, 'destroy']);

    // Contacts / Timeline
    Route::get('/influenceurs/{influenceur}/contacts', [ContactController::class, 'index']);
    Route::post('/influenceurs/{influenceur}/contacts', [ContactController::class, 'store']);
    Route::put('/influenceurs/{influenceur}/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/influenceurs/{influenceur}/contacts/{contact}', [ContactController::class, 'destroy']);

    // Rappels
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders/{reminder}/dismiss', [ReminderController::class, 'dismiss']);
    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'done']);

    // Statistiques
    Route::get('/stats', [StatsController::class, 'index']);

    // Objectifs (lecture pour tous, écriture admin uniquement)
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

    // Équipe (admin uniquement)
    Route::middleware('role:admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/team', [TeamController::class, 'store']);
        Route::put('/team/{user}', [TeamController::class, 'update']);
        Route::delete('/team/{user}', [TeamController::class, 'destroy']);
    });
});
