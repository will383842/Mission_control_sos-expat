<?php

namespace App\Http\Controllers;

use App\Models\Influenceur;
use App\Models\Objective;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ObjectiveController extends Controller
{
    /**
     * List objectives.
     * Admin sees all active objectives grouped by user.
     * Researcher sees only their own active objectives.
     */
    public function index(Request $request)
    {
        $query = Objective::with(['user:id,name', 'creator:id,name'])->active();

        if ($request->user()->isResearcher()) {
            $query->where('user_id', $request->user()->id);
        }

        $objectives = $query->orderByDesc('created_at')->get();

        // Admin: group by user
        if (!$request->user()->isResearcher()) {
            return response()->json($objectives->groupBy('user_id'));
        }

        return response()->json($objectives);
    }

    /**
     * Create objective (admin only — enforced via route middleware).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'country'      => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:10',
            'niche'        => 'nullable|string|max:255',
            'target_count' => 'required|integer|min:1',
            'deadline'     => 'required|date|after:today',
        ]);

        $data['created_by'] = $request->user()->id;

        $objective = Objective::create($data);

        return response()->json($objective->load(['user:id,name', 'creator:id,name']), 201);
    }

    /**
     * Update objective (admin only).
     */
    public function update(Request $request, Objective $objective)
    {
        $data = $request->validate([
            'country'      => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:10',
            'niche'        => 'nullable|string|max:255',
            'target_count' => 'sometimes|integer|min:1',
            'deadline'     => 'sometimes|date|after:today',
            'is_active'    => 'sometimes|boolean',
        ]);

        $objective->update($data);

        return response()->json($objective->load(['user:id,name', 'creator:id,name']));
    }

    /**
     * Delete objective (admin only).
     */
    public function destroy(Objective $objective)
    {
        $objective->delete();

        return response()->json(null, 204);
    }

    /**
     * Return progress for a given user's active objectives.
     * Admin can check any user via ?user_id=, researcher sees own only.
     */
    public function progress(Request $request)
    {
        $userId = $request->query('user_id', $request->user()->id);

        // Researchers can only see their own progress
        if ($request->user()->isResearcher() && (int) $userId !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $objectives = Objective::where('user_id', $userId)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        if ($objectives->isEmpty()) {
            return response()->json([
                'has_objectives'  => false,
                'message'         => 'Aucun objectif actif.',
                'objectives'      => [],
                'global_progress' => [
                    'total_current' => 0,
                    'total_target'  => 0,
                    'percentage'    => 0,
                ],
            ]);
        }

        $totalTarget = 0;
        $totalValid  = 0;

        $objectivesData = $objectives->map(function ($objective) use ($userId, &$totalTarget, &$totalValid) {
            $query = Influenceur::where('created_by', $userId)
                ->validForObjective();

            // Apply filters if set on the objective
            if ($objective->country) {
                $query->where('country', $objective->country);
            }
            if ($objective->language) {
                $query->where('language', $objective->language);
            }
            if ($objective->niche) {
                $query->where('niche', $objective->niche);
            }

            $currentCount = $query->count();
            $daysRemaining = max(0, (int) now()->startOfDay()->diffInDays($objective->deadline, false));
            $percentage = $objective->target_count > 0
                ? round($currentCount / $objective->target_count * 100, 1)
                : 0;

            $totalTarget += $objective->target_count;
            $totalValid  += $currentCount;

            return [
                'id'             => $objective->id,
                'country'        => $objective->country,
                'language'       => $objective->language,
                'niche'          => $objective->niche,
                'target_count'   => $objective->target_count,
                'deadline'       => $objective->deadline->toDateString(),
                'current_count'  => $currentCount,
                'percentage'     => $percentage,
                'days_remaining' => $daysRemaining,
                'is_overdue'     => $daysRemaining === 0 && $objective->deadline->isPast(),
            ];
        });

        $globalPercentage = $totalTarget > 0
            ? round($totalValid / $totalTarget * 100, 1)
            : 0;

        return response()->json([
            'has_objectives'  => true,
            'objectives'      => $objectivesData,
            'global_progress' => [
                'total_current' => $totalValid,
                'total_target'  => $totalTarget,
                'percentage'    => $globalPercentage,
            ],
        ]);
    }
}
