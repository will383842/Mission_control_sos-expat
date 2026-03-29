<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAnnuaireImport;
use App\Models\AnnuaireImportJob;
use App\Services\WikidataService;
use App\Services\OverpassService;
use App\Services\PerplexityPracticalLinksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des imports de l'annuaire depuis la console d'administration.
 *
 * POST   /country-directory/imports          — Lancer un import
 * GET    /country-directory/imports          — Historique des imports
 * GET    /country-directory/imports/{id}     — Statut/progression d'un import
 * POST   /country-directory/imports/{id}/cancel — Annuler
 * DELETE /country-directory/imports/{id}     — Supprimer de l'historique
 * GET    /country-directory/imports/sources  — Métadonnées des sources disponibles
 */
class AnnuaireImportController extends Controller
{
    /**
     * Lance un nouvel import en background.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source'      => 'required|in:wikidata,overpass,perplexity',
            'scope_type'  => 'required|in:nationality,country,all',
            'scope_value' => 'nullable|string|max:1000',  // CSV de codes ISO ou null
            'categories'  => 'nullable|array',
            'categories.*'=> 'string|max:50',
        ]);

        // Normaliser scope_value
        if (($validated['scope_type'] ?? '') === 'all') {
            $validated['scope_value'] = null;
        }

        $job = AnnuaireImportJob::create([
            'source'      => $validated['source'],
            'scope_type'  => $validated['scope_type'],
            'scope_value' => $validated['scope_value'] ?? null,
            'categories'  => $validated['categories'] ?? null,
            'status'      => 'pending',
            'launched_by' => $request->user()?->email ?? 'admin',
        ]);

        // Dispatch en background (queue: default)
        ProcessAnnuaireImport::dispatch($job->id)->onQueue('default');

        return response()->json([
            'id'     => $job->id,
            'status' => 'pending',
            'message'=> "Import #{$job->id} en attente dans la queue.",
        ], 202);
    }

    /**
     * Historique des imports (50 derniers).
     */
    public function index(): JsonResponse
    {
        $jobs = AnnuaireImportJob::recent()->get()->map(fn(AnnuaireImportJob $j) => [
            'id'              => $j->id,
            'source'          => $j->source,
            'scope_type'      => $j->scope_type,
            'scope_value'     => $j->scope_value,
            'categories'      => $j->categories,
            'status'          => $j->status,
            'total_expected'  => $j->total_expected,
            'total_processed' => $j->total_processed,
            'total_inserted'  => $j->total_inserted,
            'total_updated'   => $j->total_updated,
            'total_errors'    => $j->total_errors,
            'progress_pct'    => $j->progressPercent(),
            'launched_by'     => $j->launched_by,
            'started_at'      => $j->started_at?->toIso8601String(),
            'completed_at'    => $j->completed_at?->toIso8601String(),
            'duration_min'    => $j->started_at && $j->completed_at
                ? round($j->started_at->diffInSeconds($j->completed_at) / 60, 1)
                : null,
            'created_at'      => $j->created_at->toIso8601String(),
        ]);

        return response()->json($jobs);
    }

    /**
     * Détail d'un import (avec log en temps réel — polling).
     */
    public function show(int $id): JsonResponse
    {
        $j = AnnuaireImportJob::findOrFail($id);

        // Retourner les 80 dernières lignes du log
        $logLines = $j->log ? array_slice(explode("\n", $j->log), -80) : [];

        return response()->json([
            'id'              => $j->id,
            'source'          => $j->source,
            'scope_type'      => $j->scope_type,
            'scope_value'     => $j->scope_value,
            'categories'      => $j->categories,
            'status'          => $j->status,
            'total_expected'  => $j->total_expected,
            'total_processed' => $j->total_processed,
            'total_inserted'  => $j->total_inserted,
            'total_updated'   => $j->total_updated,
            'total_errors'    => $j->total_errors,
            'progress_pct'    => $j->progressPercent(),
            'error_message'   => $j->error_message,
            'log_lines'       => $logLines,
            'launched_by'     => $j->launched_by,
            'started_at'      => $j->started_at?->toIso8601String(),
            'completed_at'    => $j->completed_at?->toIso8601String(),
            'created_at'      => $j->created_at->toIso8601String(),
        ]);
    }

    /**
     * Annuler un import en cours.
     */
    public function cancel(int $id): JsonResponse
    {
        $job = AnnuaireImportJob::findOrFail($id);

        if (!in_array($job->status, ['pending', 'running'])) {
            return response()->json(['error' => "Import déjà {$job->status}"], 422);
        }

        $job->update(['status' => 'cancelled', 'completed_at' => now()]);
        $job->appendLog("Import annulé par l'administrateur.");

        return response()->json(['cancelled' => true, 'id' => $id]);
    }

    /**
     * Supprimer un import de l'historique.
     */
    public function destroy(int $id): JsonResponse
    {
        $job = AnnuaireImportJob::findOrFail($id);

        if ($job->status === 'running') {
            return response()->json(['error' => 'Impossible de supprimer un import en cours.'], 422);
        }

        $job->delete();
        return response()->json(['deleted' => true]);
    }

    /**
     * Métadonnées des sources disponibles (pour le formulaire frontend).
     */
    public function sources(): JsonResponse
    {
        $allIsoCodes = WikidataService::getSupportedIsoCodes();

        return response()->json([
            'wikidata' => [
                'label'       => 'Wikidata — Ambassades & Consulats',
                'description' => 'Importe toutes les ambassades et consulats depuis la base de données libre Wikidata. 9 langues (FR/EN/ES/AR/DE/PT/ZH/HI/RU). ~150-160 entrées par nationalité.',
                'scope_types' => ['nationality', 'all'],
                'categories'  => ['ambassade'],
                'iso_codes'   => $allIsoCodes,
                'total_countries' => count($allIsoCodes),
                'estimated_time' => '~10 min pour 30 nationalités, ~2-3h pour toutes',
            ],
            'overpass' => [
                'label'       => 'OpenStreetMap — Institutions physiques',
                'description' => 'Hôpitaux, banques, universités, gares, aéroports, commissariats. Données géographiques libres avec adresses GPS. Universel — toutes nationalités confondues.',
                'scope_types' => ['country', 'all'],
                'categories'  => OverpassService::SUPPORTED_CATEGORIES, // hopitaux→sub de sante
                'iso_codes'   => $allIsoCodes,
                'estimated_time' => '~30 min pour 50 pays, ~4h pour tous',
            ],
            'perplexity' => [
                'label'          => 'Perplexity — Liens pratiques (recherche web réelle)',
                'description'    => 'Recherche les sites officiels sur le vrai web (immigration, fiscalité, logement, emploi, telecom, etc.). URLs vérifiées avec citations — zéro hallucination. Universel — toutes nationalités. 9 langues.',
                'scope_types'    => ['country', 'all'],
                'categories'     => PerplexityPracticalLinksService::SUPPORTED_CATEGORIES,
                'iso_codes'      => $allIsoCodes,
                'total_countries'=> count($allIsoCodes),
                'estimated_time' => '~2h pour 50 pays × toutes catégories (2s/requête)',
                'cost_estimate'  => '~$3-4 USD pour les 195 pays × toutes catégories (sonar ~$0.002/req)',
            ],
        ]);
    }
}
