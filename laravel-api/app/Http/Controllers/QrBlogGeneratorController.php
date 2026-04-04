<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateQrBlogJob;
use App\Models\ContentQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QrBlogGeneratorController extends Controller
{
    private const PROGRESS_KEY = 'qr_blog_generation_progress';

    /**
     * GET /content-gen/qr-blog/stats
     * Nombre de questions disponibles par statut.
     */
    public function stats(): JsonResponse
    {
        $available = ContentQuestion::where('article_status', 'opportunity')->count();
        $writing   = ContentQuestion::where('article_status', 'writing')->count();
        $published = ContentQuestion::where('article_status', 'published')->count();
        $skipped   = ContentQuestion::where('article_status', 'skipped')->count();
        $total     = ContentQuestion::count();

        $progress = Cache::get(self::PROGRESS_KEY);

        return response()->json([
            'available' => $available,
            'writing'   => $writing,
            'published' => $published,
            'skipped'   => $skipped,
            'total'     => $total,
            'progress'  => $progress,
        ]);
    }

    /**
     * POST /content-gen/qr-blog/generate
     * Lance la génération en arrière-plan (queue job).
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit'    => 'nullable|integer|min:1|max:200',
            'country'  => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
        ]);

        // Vérifier qu'une génération n'est pas déjà en cours
        $current = Cache::get(self::PROGRESS_KEY);
        if ($current && ($current['status'] ?? '') === 'running') {
            return response()->json([
                'message' => 'Une génération Q/R est déjà en cours. Attendez qu\'elle se termine.',
            ], 409);
        }

        $limit    = $data['limit'] ?? 50;
        $country  = $data['country'] ?? null;
        $category = $data['category'] ?? null;

        // Récupérer les IDs des questions à traiter
        $query = ContentQuestion::where('article_status', 'opportunity')
            ->orderByDesc('views');

        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->where('country_slug', $country)
                  ->orWhere('country', 'ilike', '%' . $country . '%');
            });
        }
        if ($category) {
            // On peut filtrer par mots-clés dans le titre pour les catégories
            // car les questions n'ont pas encore de catégorie assignée
        }

        $questionIds = $query->limit($limit)->pluck('id')->toArray();

        if (empty($questionIds)) {
            return response()->json(['message' => 'Aucune question disponible (status=opportunity).'], 422);
        }

        // Initialiser la progression dans Redis
        Cache::put(self::PROGRESS_KEY, [
            'status'        => 'running',
            'total'         => count($questionIds),
            'completed'     => 0,
            'skipped'       => 0,
            'errors'        => 0,
            'current_title' => null,
            'started_at'    => now()->toIso8601String(),
            'finished_at'   => null,
            'log'           => [],
        ], now()->addHours(24));

        // Dispatcher le job en arrière-plan
        GenerateQrBlogJob::dispatch($questionIds)->onQueue('default');

        return response()->json([
            'message'     => count($questionIds) . ' Q/R lancées en génération.',
            'total'       => count($questionIds),
            'progress_key'=> self::PROGRESS_KEY,
        ]);
    }

    /**
     * GET /content-gen/qr-blog/progress
     * Statut de la génération en cours (polling frontend).
     */
    public function progress(): JsonResponse
    {
        $progress = Cache::get(self::PROGRESS_KEY);

        if (! $progress) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json($progress);
    }

    /**
     * POST /content-gen/qr-blog/reset
     * Remet des questions en statut 'new' si bloquées en 'writing'.
     */
    public function reset(): JsonResponse
    {
        $count = ContentQuestion::where('article_status', 'writing')->update(['article_status' => 'opportunity']);
        Cache::forget(self::PROGRESS_KEY);

        return response()->json(['message' => "{$count} question(s) remises en statut 'opportunity'.", 'reset' => $count]);
    }
}
