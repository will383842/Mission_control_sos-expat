<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        // Totaux par statut
        $total    = Influenceur::count();
        $byStatus = Influenceur::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Taux de réponse
        $contacted    = Influenceur::whereIn('status', ['contacted', 'negotiating', 'active', 'refused', 'inactive'])->count();
        $replied      = Contact::where('result', 'replied')->distinct('influenceur_id')->count('influenceur_id');
        $responseRate = $contacted > 0 ? round($replied / $contacted * 100, 1) : 0;

        // Taux de conversion
        $active         = Influenceur::where('status', 'active')->count();
        $prospects      = Influenceur::where('status', 'prospect')->count();
        $conversionRate = ($prospects + $active) > 0
            ? round($active / ($prospects + $active) * 100, 1)
            : 0;

        $newThisMonth = Influenceur::where('created_at', '>=', now()->startOfMonth())->count();

        // Évolution contacts (12 semaines)
        $contactsEvolution = Contact::select(
            DB::raw("TO_CHAR(date, 'IYYY-IW') as week"),
            DB::raw('count(*) as count')
        )
            ->where('date', '>=', now()->subWeeks(12))
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Répartition plateformes
        $byPlatform = Influenceur::select('primary_platform', DB::raw('count(*) as count'))
            ->groupBy('primary_platform')
            ->orderByDesc('count')
            ->get();

        // Taux de réponse par plateforme
        $responseByPlatform = DB::table('contacts')
            ->join('influenceurs', 'contacts.influenceur_id', '=', 'influenceurs.id')
            ->select(
                'influenceurs.primary_platform',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when contacts.result = 'replied' then 1 else 0 end) as replied")
            )
            ->groupBy('influenceurs.primary_platform')
            ->get()
            ->map(fn($r) => [
                'platform' => $r->primary_platform,
                'rate'     => $r->total > 0 ? round($r->replied / $r->total * 100, 1) : 0,
                'total'    => $r->total,
            ]);

        // Activité équipe ce mois
        $teamActivity = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->where('action', 'contact_added')
            ->where('created_at', '>=', now()->startOfMonth())
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get();

        // Funnel conversion
        $funnel = [
            ['stage' => 'Prospect',     'count' => $byStatus['prospect']     ?? 0],
            ['stage' => 'Contacté',     'count' => $byStatus['contacted']    ?? 0],
            ['stage' => 'Négociation',  'count' => $byStatus['negotiating']  ?? 0],
            ['stage' => 'Actif',        'count' => $byStatus['active']       ?? 0],
        ];

        // 10 dernières activités
        $recentActivity = ActivityLog::with(['user:id,name', 'influenceur:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(compact(
            'total', 'byStatus', 'responseRate', 'conversionRate',
            'newThisMonth', 'active', 'contactsEvolution',
            'byPlatform', 'responseByPlatform', 'teamActivity',
            'funnel', 'recentActivity'
        ));
    }

    /**
     * Admin-only: stats for all researchers.
     */
    public function researcherStats()
    {
        $researchers = User::where('role', 'researcher')
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'created_at')
            ->get();

        $stats = $researchers->map(function ($researcher) {
            $totalCreated = Influenceur::where('created_by', $researcher->id)->count();
            $createdToday = Influenceur::where('created_by', $researcher->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            $createdThisWeek = Influenceur::where('created_by', $researcher->id)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();
            $createdThisMonth = Influenceur::where('created_by', $researcher->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            // Active objective
            $objective = Objective::where('user_id', $researcher->id)
                ->where('is_active', true)
                ->latest()
                ->first();

            $objectiveData = null;
            if ($objective) {
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
                $periodCount = Influenceur::where('created_by', $researcher->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count();

                $objectiveData = [
                    'target_count'  => $objective->target_count,
                    'period'        => $objective->period,
                    'current_count' => $periodCount,
                    'percentage'    => $objective->target_count > 0
                        ? round($periodCount / $objective->target_count * 100, 1)
                        : 0,
                ];
            }

            return [
                'id'                 => $researcher->id,
                'name'               => $researcher->name,
                'email'              => $researcher->email,
                'total_created'      => $totalCreated,
                'created_today'      => $createdToday,
                'created_this_week'  => $createdThisWeek,
                'created_this_month' => $createdThisMonth,
                'objective'          => $objectiveData,
            ];
        });

        return response()->json($stats);
    }
}
