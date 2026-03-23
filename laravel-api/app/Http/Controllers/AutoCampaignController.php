<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AutoCampaign;
use App\Models\AutoCampaignTask;
use App\Models\ContactTypeModel;
use Illuminate\Http\Request;

class AutoCampaignController extends Controller
{
    /**
     * Predefined country lists grouped by region.
     * Used by frontend to offer quick presets.
     */
    public const COUNTRY_PRESETS = [
        'europe' => [
            'Royaume-Uni', 'Allemagne', 'Espagne', 'Belgique', 'Suisse',
            'Italie', 'Pays-Bas', 'Portugal', 'Luxembourg', 'Irlande',
            'Autriche', 'Suède', 'Danemark', 'Norvège', 'Pologne',
            'République Tchèque', 'Grèce', 'Roumanie', 'Hongrie',
        ],
        'afrique' => [
            'Maroc', 'Tunisie', 'Sénégal', 'Côte d\'Ivoire', 'Cameroun',
            'Madagascar', 'Algérie', 'Maurice', 'Gabon', 'Congo',
            'Mali', 'Burkina Faso', 'Bénin', 'Togo', 'Niger',
            'RDC', 'Guinée', 'Djibouti',
        ],
        'ameriques' => [
            'États-Unis', 'Canada', 'Brésil', 'Mexique', 'Argentine',
            'Colombie', 'Chili', 'Pérou', 'République Dominicaine',
        ],
        'asie_oceanie' => [
            'Thaïlande', 'Vietnam', 'Japon', 'Chine', 'Singapour',
            'Émirats Arabes Unis', 'Israël', 'Liban', 'Inde',
            'Corée du Sud', 'Cambodge', 'Laos', 'Malaisie',
            'Australie', 'Nouvelle-Zélande',
        ],
    ];

    /**
     * List all campaigns (paginated).
     */
    public function index(Request $request)
    {
        $campaigns = AutoCampaign::orderByDesc('created_at')
            ->withCount(['tasks as tasks_pending_count' => fn($q) => $q->where('status', 'pending')])
            ->paginate(20);

        return response()->json($campaigns);
    }

    /**
     * Get country presets + available contact types.
     */
    public function config()
    {
        return response()->json([
            'country_presets' => self::COUNTRY_PRESETS,
            'contact_types'  => ContactTypeModel::allActive(),
            'languages'      => [
                ['value' => 'fr', 'label' => 'Français'],
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'es', 'label' => 'Español'],
                ['value' => 'de', 'label' => 'Deutsch'],
                ['value' => 'pt', 'label' => 'Português'],
                ['value' => 'it', 'label' => 'Italiano'],
                ['value' => 'ar', 'label' => 'العربية'],
                ['value' => 'nl', 'label' => 'Nederlands'],
            ],
        ]);
    }

    /**
     * Create and launch a new campaign.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                          => 'required|string|max:200',
            'contact_types'                 => 'required|array|min:1',
            'contact_types.*'               => 'string|in:' . implode(',', ContactTypeModel::validValues()),
            'countries'                     => 'required|array|min:1',
            'countries.*'                   => 'string|max:100',
            'languages'                     => 'required|array|min:1',
            'languages.*'                   => 'string|in:fr,en,es,de,pt,it,ar,nl',
            'delay_between_tasks_seconds'   => 'sometimes|integer|min:60|max:3600',
            'delay_between_retries_seconds' => 'sometimes|integer|min:300|max:7200',
            'max_retries'                   => 'sometimes|integer|min:1|max:5',
            'max_consecutive_failures'      => 'sometimes|integer|min:3|max:20',
        ]);

        // Prevent launching if another campaign is already running
        if (AutoCampaign::running()->exists()) {
            return response()->json([
                'message' => 'Une campagne est déjà en cours. Mettez-la en pause ou attendez qu\'elle se termine.',
            ], 422);
        }

        // Create campaign
        $campaign = AutoCampaign::create([
            'name'                          => $data['name'],
            'status'                        => 'running',
            'contact_types'                 => $data['contact_types'],
            'countries'                     => $data['countries'],
            'languages'                     => $data['languages'],
            'delay_between_tasks_seconds'   => $data['delay_between_tasks_seconds'] ?? 300,
            'delay_between_retries_seconds' => $data['delay_between_retries_seconds'] ?? 600,
            'max_retries'                   => $data['max_retries'] ?? 3,
            'max_consecutive_failures'      => $data['max_consecutive_failures'] ?? 5,
            'started_at'                    => now(),
            'created_by'                    => $request->user()->id,
        ]);

        // Generate all task combos: type × country × language
        $tasks = [];
        $priority = 0;
        foreach ($data['contact_types'] as $type) {
            foreach ($data['countries'] as $country) {
                foreach ($data['languages'] as $lang) {
                    $tasks[] = [
                        'campaign_id'  => $campaign->id,
                        'contact_type' => $type,
                        'country'      => $country,
                        'language'     => $lang,
                        'status'       => 'pending',
                        'priority'     => $priority++,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
            }
        }

        // Batch insert
        foreach (array_chunk($tasks, 500) as $chunk) {
            AutoCampaignTask::insert($chunk);
        }

        $campaign->update(['tasks_total' => count($tasks)]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action'   => 'auto_campaign_launched',
            'details'  => [
                'campaign_id'   => $campaign->id,
                'name'          => $campaign->name,
                'tasks_total'   => count($tasks),
                'contact_types' => $data['contact_types'],
                'countries'     => count($data['countries']),
                'languages'     => $data['languages'],
            ],
        ]);

        return response()->json($campaign->load('tasks'), 201);
    }

    /**
     * Get campaign details with task breakdown.
     */
    public function show(AutoCampaign $campaign)
    {
        $campaign->load(['tasks' => function ($q) {
            $q->orderBy('priority');
        }]);

        // Summary by status
        $statusCounts = $campaign->tasks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Summary by country
        $countryCounts = $campaign->tasks()
            ->selectRaw('country, status, count(*) as count')
            ->groupBy('country', 'status')
            ->get()
            ->groupBy('country')
            ->map(fn($items) => $items->pluck('count', 'status'));

        // Alerts (recent activity logs)
        $alerts = ActivityLog::where('action', 'auto_campaign_alert')
            ->whereJsonContains('details->campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'campaign'       => $campaign,
            'status_counts'  => $statusCounts,
            'country_counts' => $countryCounts,
            'alerts'         => $alerts,
            'progress'       => $campaign->progress,
        ]);
    }

    /**
     * Pause a running campaign.
     */
    public function pause(AutoCampaign $campaign)
    {
        if ($campaign->status !== 'running') {
            return response()->json(['message' => 'La campagne n\'est pas en cours.'], 422);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json(['message' => 'Campagne mise en pause.', 'campaign' => $campaign]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(AutoCampaign $campaign)
    {
        if ($campaign->status !== 'paused') {
            return response()->json(['message' => 'La campagne n\'est pas en pause.'], 422);
        }

        // Prevent running two campaigns at once
        if (AutoCampaign::running()->where('id', '!=', $campaign->id)->exists()) {
            return response()->json(['message' => 'Une autre campagne est déjà en cours.'], 422);
        }

        $campaign->update([
            'status'                => 'running',
            'consecutive_failures'  => 0, // Reset circuit breaker
        ]);

        return response()->json(['message' => 'Campagne reprise.', 'campaign' => $campaign]);
    }

    /**
     * Update campaign settings (delay, retries) while running.
     */
    public function updateSettings(Request $request, AutoCampaign $campaign)
    {
        $data = $request->validate([
            'delay_between_tasks_seconds'   => 'sometimes|integer|min:30|max:3600',
            'max_retries'                   => 'sometimes|integer|min:1|max:5',
            'max_consecutive_failures'      => 'sometimes|integer|min:3|max:20',
        ]);

        $campaign->update($data);

        return response()->json([
            'message'  => 'Paramètres mis à jour.',
            'campaign' => $campaign,
        ]);
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(AutoCampaign $campaign)
    {
        if (in_array($campaign->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'La campagne est déjà terminée.'], 422);
        }

        // Cancel all pending/failed tasks
        $campaign->tasks()
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'skipped', 'error_message' => 'Campagne annulée']);

        $skippedCount = $campaign->tasks()->where('status', 'skipped')->count();
        $campaign->update([
            'status'        => 'cancelled',
            'tasks_skipped' => $skippedCount,
            'completed_at'  => now(),
        ]);

        return response()->json(['message' => 'Campagne annulée.', 'campaign' => $campaign]);
    }

    /**
     * Retry all failed tasks in a campaign (resets retry counter).
     */
    public function retryFailed(AutoCampaign $campaign)
    {
        $failedCount = $campaign->tasks()
            ->where('status', 'failed')
            ->whereNull('next_retry_at')
            ->update([
                'status'       => 'pending',
                'attempt'      => 0,
                'error_message' => null,
            ]);

        if ($failedCount > 0) {
            // Decrement tasks_failed counter and reset circuit breaker
            $campaign->update([
                'status'               => 'running',
                'tasks_failed'         => max(0, $campaign->tasks_failed - $failedCount),
                'consecutive_failures' => 0,
            ]);
        }

        return response()->json([
            'message'       => "{$failedCount} tâches remises en file d'attente.",
            'retried_count' => $failedCount,
        ]);
    }
}
