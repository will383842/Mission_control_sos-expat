<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Influenceur;
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
            DB::raw('YEARWEEK(date, 1) as week'),
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
                DB::raw('sum(case when contacts.result = "replied" then 1 else 0 end) as replied')
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
}
