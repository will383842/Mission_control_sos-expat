<?php

namespace App\Http\Controllers;

use App\Models\Influenceur;
use App\Models\Objective;
use Illuminate\Http\Request;

class ObjectiveController extends Controller
{
    /**
     * List objectives.
     * Admin sees all, researcher sees own.
     */
    public function index(Request $request)
    {
        $query = Objective::with(['user:id,name', 'creator:id,name']);

        if ($request->user()->isResearcher()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /**
     * Create objective (admin only — enforced via route middleware).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'target_count' => 'required|integer|min:1',
            'period'       => 'required|in:daily,weekly,monthly',
            'start_date'   => 'sometimes|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'is_active'    => 'sometimes|boolean',
        ]);

        $data['start_date']  = $data['start_date'] ?? now()->toDateString();
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
            'target_count' => 'sometimes|integer|min:1',
            'period'       => 'sometimes|in:daily,weekly,monthly',
            'start_date'   => 'sometimes|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
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
     * Return progress for a given user in the current period.
     * Compares influenceurs created in the period vs the objective target.
     */
    public function progress(Request $request)
    {
        $userId = $request->query('user_id', $request->user()->id);

        // Researchers can only see their own progress
        if ($request->user()->isResearcher() && (int) $userId !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $objective = Objective::where('user_id', $userId)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$objective) {
            return response()->json([
                'has_objective' => false,
                'message'       => 'Aucun objectif actif.',
            ]);
        }

        // Calculate period boundaries
        $now = now();
        switch ($objective->period) {
            case 'daily':
                $periodStart = $now->copy()->startOfDay();
                $periodEnd   = $now->copy()->endOfDay();
                break;
            case 'weekly':
                $periodStart = $now->copy()->startOfWeek();
                $periodEnd   = $now->copy()->endOfWeek();
                break;
            case 'monthly':
                $periodStart = $now->copy()->startOfMonth();
                $periodEnd   = $now->copy()->endOfMonth();
                break;
        }

        $count = Influenceur::where('created_by', $userId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        return response()->json([
            'has_objective'  => true,
            'objective_id'   => $objective->id,
            'target_count'   => $objective->target_count,
            'period'         => $objective->period,
            'current_count'  => $count,
            'percentage'     => $objective->target_count > 0
                ? round($count / $objective->target_count * 100, 1)
                : 0,
            'period_start'   => $periodStart->toDateString(),
            'period_end'     => $periodEnd->toDateString(),
        ]);
    }
}
