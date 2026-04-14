<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLandingPageJob;
use App\Models\LandingCampaign;
use App\Models\LandingPage;
use App\Models\LandingProblem;
use App\Services\Content\LandingGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingCampaignController extends Controller
{
    private const VALID_TYPES = ['clients', 'lawyers', 'helpers', 'matching'];

    // ============================================================
    // GET /api/landing-campaigns/{type}
    // ============================================================

    public function show(string $type): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $campaign = LandingCampaign::findOrCreateForType($type);
        return response()->json($this->buildStatus($campaign));
    }

    // ============================================================
    // PUT /api/landing-campaigns/{type}
    // ============================================================

    public function update(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $validated = $request->validate([
            'pages_per_country'       => 'sometimes|integer|min:1|max:500',
            'daily_limit'             => 'sometimes|integer|min:0|max:9999',
            'selected_templates'      => 'sometimes|array',
            'selected_templates.*'    => 'string',
            'problem_filters'         => 'sometimes|nullable|array',
            'problem_filters.categories'      => 'sometimes|array',
            'problem_filters.min_urgency'     => 'sometimes|integer|min:0|max:10',
            'problem_filters.business_values' => 'sometimes|array',
        ]);

        $campaign = LandingCampaign::findOrCreateForType($type);
        $campaign->update($validated);

        return response()->json($this->buildStatus($campaign));
    }

    // ============================================================
    // POST /api/landing-campaigns/{type}/launch
    // ============================================================

    public function launch(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $campaign = LandingCampaign::findOrCreateForType($type);

        $currentCountry = $campaign->current_country;
        if (! $currentCountry) {
            // Prendre le premier pays de la queue
            $queue = $campaign->country_queue ?? [];
            if (empty($queue)) {
                return response()->json(['error' => 'Aucun pays dans la queue'], 422);
            }
            $currentCountry = $queue[0];
            $campaign->update(['current_country' => $currentCountry, 'status' => 'running', 'started_at' => now()]);
        }

        $selectedTemplates = $campaign->selected_templates ?? LandingCampaign::DEFAULT_TEMPLATES[$type];

        if (empty($selectedTemplates)) {
            return response()->json(['error' => 'Aucun template sélectionné pour cette campagne'], 422);
        }

        // Garde quotidienne : daily_limit = 0 → illimité
        $dailyLimit = (int) $campaign->daily_limit;
        if ($dailyLimit > 0) {
            $todayGenerated = LandingPage::where('audience_type', $type)
                ->where('generation_source', 'ai_generated')
                ->whereDate('created_at', today())
                ->count();

            if ($todayGenerated >= $dailyLimit) {
                return response()->json([
                    'error'          => "Limite journalière atteinte ({$todayGenerated}/{$dailyLimit} LPs aujourd'hui)",
                    'today_generated'=> $todayGenerated,
                    'daily_limit'    => $dailyLimit,
                ], 429);
            }
        }

        $language  = $request->input('language', 'fr');
        $supported = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'hi', 'zh', 'ru'];
        if (! in_array($language, $supported, true)) {
            return response()->json([
                'error' => 'Langue non supportée. Valeurs acceptées : ' . implode(', ', $supported),
            ], 422);
        }

        $dispatched = 0;

        if ($type === 'clients') {
            $dispatched = $this->launchClients($campaign, $currentCountry, $selectedTemplates, $language);
        } else {
            $dispatched = $this->launchSimple($type, $campaign, $currentCountry, $selectedTemplates, $language);
        }

        $campaign->update(['status' => 'running']);

        Log::info('LandingCampaignController::launch', [
            'type'           => $type,
            'country'        => $currentCountry,
            'dispatched'     => $dispatched,
            'templates'      => $selectedTemplates,
        ]);

        return response()->json([
            'message'        => "{$dispatched} jobs dispatchés pour {$currentCountry}",
            'country'        => $currentCountry,
            'dispatched'     => $dispatched,
            'campaign'       => $this->buildStatus($campaign->fresh()),
        ]);
    }

    // ============================================================
    // POST /api/landing-campaigns/{type}/add/{country_code}
    // ============================================================

    public function addCountry(string $type, string $countryCode): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $campaign = LandingCampaign::findOrCreateForType($type);
        $queue    = $campaign->country_queue ?? [];

        $code = strtoupper($countryCode);

        if (strlen($code) !== 2 || ! ctype_alpha($code)) {
            return response()->json(['error' => 'Code pays invalide — 2 lettres ISO 3166-1 alpha-2 requises'], 422);
        }

        if (! in_array($code, $queue)) {
            $queue[] = $code;

            // Définir le premier pays comme current si pas encore défini
            $updates = ['country_queue' => $queue];
            if (! $campaign->current_country) {
                $updates['current_country'] = $code;
            }

            $campaign->update($updates);
        }

        return response()->json($this->buildStatus($campaign->fresh()));
    }

    // ============================================================
    // DELETE /api/landing-campaigns/{type}/remove/{country_code}
    // ============================================================

    public function removeCountry(string $type, string $countryCode): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $campaign = LandingCampaign::findOrCreateForType($type);
        $code     = strtoupper($countryCode);

        if (strlen($code) !== 2 || ! ctype_alpha($code)) {
            return response()->json(['error' => 'Code pays invalide — 2 lettres ISO 3166-1 alpha-2 requises'], 422);
        }

        $queue    = array_values(array_filter($campaign->country_queue ?? [], fn ($c) => $c !== $code));

        $updates = ['country_queue' => $queue];

        // Si on retire le pays actuel, passer au suivant
        if ($campaign->current_country === $code) {
            $updates['current_country'] = $queue[0] ?? null;
        }

        $campaign->update($updates);

        return response()->json($this->buildStatus($campaign->fresh()));
    }

    // ============================================================
    // PUT /api/landing-campaigns/{type}/reorder
    // ============================================================

    public function reorder(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::VALID_TYPES)) {
            return response()->json(['error' => 'Type invalide'], 422);
        }

        $validated = $request->validate([
            'country_queue'   => 'required|array',
            'country_queue.*' => 'string|size:2',
        ]);

        $campaign = LandingCampaign::findOrCreateForType($type);
        $newQueue = array_map('strtoupper', $validated['country_queue']);

        $updates = ['country_queue' => $newQueue];
        if (! empty($newQueue)) {
            $updates['current_country'] = $newQueue[0];
        }

        $campaign->update($updates);

        return response()->json($this->buildStatus($campaign->fresh()));
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    /**
     * Lance les jobs pour l'audience "clients":
     * selected_templates × problems filtrés, limité à pages_per_country.
     */
    private function launchClients(LandingCampaign $campaign, string $countryCode, array $templates, string $language): int
    {
        $filters   = $campaign->problem_filters ?? [];
        $perCountry= $campaign->pages_per_country;

        $query = LandingProblem::active()->ordered();

        if (! empty($filters['categories'])) {
            $query->whereIn('category', $filters['categories']);
        }
        if (! empty($filters['min_urgency'])) {
            $query->minUrgency((int) $filters['min_urgency']);
        }
        if (! empty($filters['business_values'])) {
            $query->byBusinessValue($filters['business_values']);
        }

        $problems  = $query->limit(max(1, (int) ceil($perCountry / count($templates))))->get();
        $dispatched = 0;
        $delay      = 0;

        foreach ($templates as $templateId) {
            foreach ($problems as $problem) {
                // Déduplication
                $exists = LandingPage::where([
                    'audience_type' => 'clients',
                    'template_id'   => $templateId,
                    'problem_id'    => $problem->slug,
                    'country_code'  => $countryCode,
                    'language'      => $language,
                ])->exists();

                if ($exists) {
                    continue;
                }

                GenerateLandingPageJob::dispatch([
                    'audience_type' => 'clients',
                    'template_id'   => $templateId,
                    'country_code'  => $countryCode,
                    'language'      => $language,
                    'problem_slug'  => $problem->slug,
                ])->delay(now()->addSeconds($delay));

                $delay     += 5; // Anti-throttle Claude
                $dispatched++;

                if ($dispatched >= $perCountry) {
                    break 2;
                }
            }
        }

        return $dispatched;
    }

    /**
     * Lance les jobs pour lawyers/helpers/matching:
     * 1 job par selected_template (pas de problems).
     */
    private function launchSimple(string $type, LandingCampaign $campaign, string $countryCode, array $templates, string $language): int
    {
        $dispatched = 0;
        $delay      = 0;

        foreach ($templates as $templateId) {
            // Déduplication
            $exists = LandingPage::where([
                'audience_type' => $type,
                'template_id'   => $templateId,
                'country_code'  => $countryCode,
                'language'      => $language,
            ])->exists();

            if ($exists) {
                continue;
            }

            GenerateLandingPageJob::dispatch([
                'audience_type' => $type,
                'template_id'   => $templateId,
                'country_code'  => $countryCode,
                'language'      => $language,
            ])->delay(now()->addSeconds($delay));

            $delay     += 5;
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Construit la réponse d'état complète d'une campagne.
     * Calcule la progression réelle depuis la BDD.
     */
    private function buildStatus(LandingCampaign $campaign): array
    {
        $queue      = $campaign->country_queue ?? [];
        $perCountry = $campaign->pages_per_country;

        // Compter les LPs générées par pays (dans la queue)
        $counts = [];
        if (! empty($queue)) {
            $rows = LandingPage::where('audience_type', $campaign->audience_type)
                ->where('generation_source', 'ai_generated')
                ->whereIn('country_code', $queue)
                ->selectRaw('country_code, COUNT(*) as cnt')
                ->groupBy('country_code')
                ->pluck('cnt', 'country_code')
                ->toArray();

            foreach ($queue as $code) {
                $counts[$code] = $rows[$code] ?? 0;
            }
        }

        // Pays terminés = count >= pages_per_country (pas dans la queue active)
        $allDone = LandingPage::where('audience_type', $campaign->audience_type)
            ->where('generation_source', 'ai_generated')
            ->whereNotIn('country_code', $queue)
            ->selectRaw('country_code, COUNT(*) as cnt')
            ->groupBy('country_code')
            ->get()
            ->filter(fn ($r) => $r->cnt >= $perCountry)
            ->map(fn ($r) => ['code' => $r->country_code, 'count' => $r->cnt])
            ->values()
            ->toArray();

        $queueItems = array_map(function ($code) use ($counts, $perCountry, $campaign) {
            return [
                'code'   => $code,
                'count'  => $counts[$code] ?? 0,
                'target' => $perCountry,
                'status' => ($code === $campaign->current_country) ? 'active' : 'pending',
            ];
        }, $queue);

        $dailyLimit     = (int) $campaign->daily_limit;
        $todayGenerated = LandingPage::where('audience_type', $campaign->audience_type)
            ->where('generation_source', 'ai_generated')
            ->whereDate('created_at', today())
            ->count();

        return [
            'audience_type'      => $campaign->audience_type,
            'status'             => $campaign->status,
            'current_country'    => $campaign->current_country,
            'pages_per_country'  => $perCountry,
            'daily_limit'        => $dailyLimit,
            'today_generated'    => $todayGenerated,
            'daily_remaining'    => $dailyLimit > 0 ? max(0, $dailyLimit - $todayGenerated) : null,
            'selected_templates' => $campaign->selected_templates ?? [],
            'problem_filters'    => $campaign->problem_filters,
            'total_generated'    => $campaign->total_generated,
            'total_cost_cents'   => $campaign->total_cost_cents,
            'queue'              => $queueItems,
            'completed_countries'=> $allDone,
            'available_templates'=> LandingGenerationService::getTemplatesForAudience($campaign->audience_type),
            'started_at'         => $campaign->started_at?->toIso8601String(),
            'completed_at'       => $campaign->completed_at?->toIso8601String(),
        ];
    }
}
