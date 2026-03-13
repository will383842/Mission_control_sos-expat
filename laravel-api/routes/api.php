<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfluenceurController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Auth publique
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // IMPORTANT : routes statiques déclarées AVANT la route paramétrée {influenceur}
    Route::get('/influenceurs/reminders-pending', [InfluenceurController::class, 'remindersPending']);
    Route::get('/influenceurs/exports/csv', [ExportController::class, 'csv'])->middleware('role:admin');
    Route::get('/influenceurs/exports/excel', [ExportController::class, 'excel'])->middleware('role:admin');

    // CRUD influenceurs
    Route::get('/influenceurs', [InfluenceurController::class, 'index']);
    Route::post('/influenceurs', [InfluenceurController::class, 'store']);
    Route::get('/influenceurs/{influenceur}', [InfluenceurController::class, 'show']);
    Route::put('/influenceurs/{influenceur}', [InfluenceurController::class, 'update']);
    Route::delete('/influenceurs/{influenceur}', [InfluenceurController::class, 'destroy'])
        ->middleware('role:admin');

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

    // Équipe (admin uniquement)
    Route::middleware('role:admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index']);
        Route::post('/team', [TeamController::class, 'store']);
        Route::put('/team/{user}', [TeamController::class, 'update']);
        Route::delete('/team/{user}', [TeamController::class, 'destroy']);
    });
});
