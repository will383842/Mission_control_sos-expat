<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $isResearcher = $request->user()->role === 'researcher';
        $userId       = $request->user()->id;

        // Base query scoped for researchers
        $baseInfluenceurQuery = Influenceur::query();
        if ($isResearcher) {
            $baseInfluenceurQuery->where('created_by', $userId);
        }
        if ($request->contact_type) {
            $baseInfluenceurQuery->where('contact_type', $request->contact_type);
        }

        // By contact type
        $byContactType = (clone $baseInfluenceurQuery)->select('contact_type', DB::raw('count(*) as count'))
            ->groupBy('contact_type')
            ->pluck('count', 'contact_type');

        // Totaux par statut
        $total    = (clone $baseInfluenceurQuery)->count();
        $byStatus = (clone $baseInfluenceurQuery)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Taux de réponse
        $contacted    = (clone $baseInfluenceurQuery)->whereIn('status', ['contacted', 'negotiating', 'active', 'refused', 'inactive'])->count();
        $repliedQuery = Contact::where('result', 'replied');
        if ($isResearcher) {
            $repliedQuery->whereHas('influenceur', fn($q) => $q->where('created_by', $userId));
        }
        $replied      = $repliedQuery->distinct('influenceur_id')->count('influenceur_id');
        $responseRate = $contacted > 0 ? round($replied / $contacted * 100, 1) : 0;

        // Taux de conversion
        $active         = (clone $baseInfluenceurQuery)->where('status', 'active')->count();
        $prospects      = (clone $baseInfluenceurQuery)->where('status', 'prospect')->count();
        $conversionRate = ($prospects + $active) > 0
            ? round($active / ($prospects + $active) * 100, 1)
            : 0;

        $newThisMonth = (clone $baseInfluenceurQuery)->where('created_at', '>=', now()->startOfMonth())->count();

        // Évolution contacts (12 semaines)
        $contactsEvolutionQuery = Contact::select(
            DB::raw("TO_CHAR(date, 'IYYY-IW') as week"),
            DB::raw('count(*) as count')
        )
            ->where('date', '>=', now()->subWeeks(12));
        if ($isResearcher) {
            $contactsEvolutionQuery->whereHas('influenceur', fn($q) => $q->where('created_by', $userId));
        }
        $contactsEvolution = $contactsEvolutionQuery
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Répartition plateformes
        $byPlatform = (clone $baseInfluenceurQuery)->select('primary_platform', DB::raw('count(*) as count'))
            ->groupBy('primary_platform')
            ->orderByDesc('count')
            ->get();

        // Taux de réponse par plateforme
        $responseByPlatformQuery = DB::table('contacts')
            ->join('influenceurs', 'contacts.influenceur_id', '=', 'influenceurs.id');
        if ($isResearcher) {
            $responseByPlatformQuery->where('influenceurs.created_by', $userId);
        }
        $responseByPlatform = $responseByPlatformQuery
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
        $teamActivityQuery = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->where('action', 'contact_added')
            ->where('created_at', '>=', now()->startOfMonth());
        if ($isResearcher) {
            $teamActivityQuery->where('user_id', $userId);
        }
        $teamActivity = $teamActivityQuery
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
        $recentActivityQuery = ActivityLog::with(['user:id,name', 'influenceur:id,name'])
            ->orderByDesc('created_at')
            ->limit(10);
        if ($isResearcher) {
            $recentActivityQuery->where('user_id', $userId);
        }
        $recentActivity = $recentActivityQuery->get();

        return response()->json(compact(
            'total', 'byStatus', 'byContactType', 'responseRate', 'conversionRate',
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
            ->select('id', 'name', 'email', 'last_login_at', 'created_at')
            ->get();

        $stats = $researchers->map(function ($researcher) {
            $baseQuery = Influenceur::where('created_by', $researcher->id);

            $totalCreated = (clone $baseQuery)->count();
            $validCount   = (clone $baseQuery)->validForObjective()->count();
            $createdToday = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            $createdThisWeek = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();
            $createdThisMonth = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            // Active objectives with progress
            $objectives = Objective::where('user_id', $researcher->id)
                ->active()
                ->orderByDesc('created_at')
                ->get();

            $objectivesData = $objectives->map(function ($objective) use ($researcher) {
                $query = Influenceur::where('created_by', $researcher->id)
                    ->validForObjective();

                if ($objective->contact_type) {
                    $query->where('contact_type', $objective->contact_type);
                }
                if (!empty($objective->countries)) {
                    $query->whereIn('country', $objective->countries);
                }
                if ($objective->language) {
                    $query->where('language', $objective->language);
                }
                if ($objective->niche) {
                    $query->where('niche', $objective->niche);
                }

                $currentCount = $query->count();
                $daysRemaining = max(0, (int) now()->startOfDay()->diffInDays($objective->deadline, false));

                return [
                    'id'             => $objective->id,
                    'contact_type'   => $objective->contact_type,
                    'continent'      => $objective->continent,
                    'countries'      => $objective->countries,
                    'language'       => $objective->language,
                    'niche'          => $objective->niche,
                    'target_count'   => $objective->target_count,
                    'deadline'       => $objective->deadline->toDateString(),
                    'current_count'  => $currentCount,
                    'percentage'     => $objective->target_count > 0
                        ? round($currentCount / $objective->target_count * 100, 1)
                        : 0,
                    'days_remaining' => $daysRemaining,
                ];
            });

            return [
                'id'                 => $researcher->id,
                'name'               => $researcher->name,
                'email'              => $researcher->email,
                'last_login_at'      => $researcher->last_login_at?->toIso8601String(),
                'total_created'      => $totalCreated,
                'valid_count'        => $validCount,
                'created_today'      => $createdToday,
                'created_this_week'  => $createdThisWeek,
                'created_this_month' => $createdThisMonth,
                'objectives'         => $objectivesData,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Admin-only: coverage stats — influenceurs by country, by language, world progress.
     */
    public function coverage()
    {
        // By country (all influenceurs, not just valid)
        $byCountry = Influenceur::select('country', DB::raw('count(*) as total'))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'country' => $row->country,
                'total'   => $row->total,
            ]);

        // By language
        $byLanguage = Influenceur::select('language', DB::raw('count(*) as total'))
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->groupBy('language')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'language' => $row->language,
                'total'    => $row->total,
            ]);

        // Distinct countries covered
        $countriesCovered = Influenceur::whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct('country')
            ->count('country');

        // Distinct languages covered
        $languagesCovered = Influenceur::whereNotNull('language')
            ->where('language', '!=', '')
            ->distinct('language')
            ->count('language');

        // Total influenceurs
        $totalInfluenceurs = Influenceur::count();

        // By continent mapping (server-side grouping of countries into continents)
        $continentMap = $this->getContinentMap();
        $byContinent = [];
        foreach ($byCountry as $row) {
            $countryLower = mb_strtolower($row['country']);
            $continent = $continentMap[$countryLower] ?? 'Autre';
            if (!isset($byContinent[$continent])) {
                $byContinent[$continent] = ['continent' => $continent, 'total' => 0, 'countries_count' => 0, 'countries' => []];
            }
            $byContinent[$continent]['total'] += $row['total'];
            $byContinent[$continent]['countries_count']++;
            $byContinent[$continent]['countries'][] = $row;
        }
        $byContinent = array_values($byContinent);
        usort($byContinent, fn($a, $b) => $b['total'] - $a['total']);

        return response()->json([
            'by_country'        => $byCountry,
            'by_language'       => $byLanguage,
            'by_continent'      => $byContinent,
            'countries_covered'  => $countriesCovered,
            'languages_covered'  => $languagesCovered,
            'total_influenceurs' => $totalInfluenceurs,
        ]);
    }

    /**
     * Admin-only: progress stats — per country, per contact_type, per language.
     * Shows total, with_email, with_phone, scraped counts + percentages.
     */
    public function progress()
    {
        // Progress by country
        $byCountry = Influenceur::select(
                'country',
                DB::raw('count(*) as total'),
                DB::raw('count(email) as with_email'),
                DB::raw('count(phone) as with_phone'),
                DB::raw('count(scraped_at) as scraped')
            )
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'country'       => $row->country,
                'total'         => $row->total,
                'with_email'    => $row->with_email,
                'email_pct'     => $row->total > 0 ? round($row->with_email / $row->total * 100, 1) : 0,
                'with_phone'    => $row->with_phone,
                'phone_pct'     => $row->total > 0 ? round($row->with_phone / $row->total * 100, 1) : 0,
                'scraped'       => $row->scraped,
            ]);

        // Progress by contact_type
        $byContactType = Influenceur::select(
                'contact_type',
                DB::raw('count(*) as total'),
                DB::raw('count(email) as with_email'),
                DB::raw('count(phone) as with_phone'),
                DB::raw('count(scraped_at) as scraped')
            )
            ->whereNotNull('contact_type')
            ->groupBy('contact_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'contact_type'  => $row->contact_type,
                'total'         => $row->total,
                'with_email'    => $row->with_email,
                'email_pct'     => $row->total > 0 ? round($row->with_email / $row->total * 100, 1) : 0,
                'with_phone'    => $row->with_phone,
                'phone_pct'     => $row->total > 0 ? round($row->with_phone / $row->total * 100, 1) : 0,
                'scraped'       => $row->scraped,
            ]);

        // Progress by language
        $byLanguage = Influenceur::select(
                'language',
                DB::raw('count(*) as total'),
                DB::raw('count(email) as with_email'),
                DB::raw('count(phone) as with_phone'),
                DB::raw('count(scraped_at) as scraped')
            )
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->groupBy('language')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'language'      => $row->language,
                'total'         => $row->total,
                'with_email'    => $row->with_email,
                'email_pct'     => $row->total > 0 ? round($row->with_email / $row->total * 100, 1) : 0,
                'with_phone'    => $row->with_phone,
                'phone_pct'     => $row->total > 0 ? round($row->with_phone / $row->total * 100, 1) : 0,
                'scraped'       => $row->scraped,
            ]);

        return response()->json([
            'by_country'      => $byCountry,
            'by_contact_type' => $byContactType,
            'by_language'     => $byLanguage,
        ]);
    }

    /**
     * Admin dashboard: global + per-type detailed stats.
     */
    public function adminDashboard()
    {
        $types = DB::table('contact_types')->orderBy('sort_order')->get(['value', 'label', 'icon', 'color', 'sort_order']);

        $stats = Influenceur::query()
            ->select(
                'contact_type',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN email IS NOT NULL THEN 1 ELSE 0 END) as with_email'),
                DB::raw('SUM(CASE WHEN phone IS NOT NULL THEN 1 ELSE 0 END) as with_phone'),
                DB::raw("SUM(CASE WHEN scraped_social::text LIKE '%_contact_form_url%' THEN 1 ELSE 0 END) as with_form"),
                DB::raw('SUM(CASE WHEN scraped_at IS NOT NULL THEN 1 ELSE 0 END) as scraped'),
                DB::raw('COUNT(DISTINCT country) as countries')
            )
            ->groupBy('contact_type')
            ->get()
            ->keyBy('contact_type');

        // Campaigns searched per type
        $searched = DB::table('auto_campaign_tasks')
            ->where('status', 'completed')
            ->select('contact_type', DB::raw('COUNT(DISTINCT country) as countries_searched'))
            ->groupBy('contact_type')
            ->pluck('countries_searched', 'contact_type');

        $perType = $types->map(function ($type) use ($stats, $searched) {
            $s = $stats->get($type->value);
            $total = $s->total ?? 0;
            $withEmail = $s->with_email ?? 0;
            $withForm = $s->with_form ?? 0;
            $contactable = $withEmail + $withForm;
            // Contacts without email AND without form
            $unreachable = $total - $contactable;

            return [
                'value'              => $type->value,
                'label'              => $type->label,
                'icon'               => $type->icon,
                'color'              => $type->color,
                'sort_order'         => $type->sort_order,
                'total'              => (int) $total,
                'with_email'         => (int) $withEmail,
                'with_phone'         => (int) ($s->with_phone ?? 0),
                'with_form'          => (int) $withForm,
                'contactable'        => (int) $contactable,
                'unreachable'        => (int) max(0, $unreachable),
                'scraped'            => (int) ($s->scraped ?? 0),
                'countries'          => (int) ($s->countries ?? 0),
                'countries_searched' => (int) ($searched[$type->value] ?? 0),
                'email_pct'          => $total > 0 ? round($withEmail / $total * 100) : 0,
                'contactable_pct'    => $total > 0 ? round($contactable / $total * 100) : 0,
            ];
        });

        // Global totals
        $globalTotal = $perType->sum('total');
        $globalEmails = $perType->sum('with_email');
        $globalPhones = $perType->sum('with_phone');
        $globalForms = $perType->sum('with_form');
        $globalContactable = $perType->sum('contactable');
        $globalUnreachable = $perType->sum('unreachable');

        // Per type + language breakdown
        $byTypeLang = Influenceur::query()
            ->select(
                'contact_type',
                'language',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN email IS NOT NULL THEN 1 ELSE 0 END) as with_email'),
                DB::raw("SUM(CASE WHEN scraped_social::text LIKE '%_contact_form_url%' THEN 1 ELSE 0 END) as with_form")
            )
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->groupBy('contact_type', 'language')
            ->orderBy('contact_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->map(fn($r) => [
                'contact_type' => $r->contact_type,
                'language'     => $r->language,
                'total'        => (int) $r->total,
                'with_email'   => (int) $r->with_email,
                'with_form'    => (int) $r->with_form,
                'email_pct'    => $r->total > 0 ? round($r->with_email / $r->total * 100) : 0,
            ]);

        return response()->json([
            'global' => [
                'total'           => $globalTotal,
                'with_email'      => $globalEmails,
                'with_phone'      => $globalPhones,
                'with_form'       => $globalForms,
                'contactable'     => $globalContactable,
                'unreachable'     => $globalUnreachable,
                'email_pct'       => $globalTotal > 0 ? round($globalEmails / $globalTotal * 100) : 0,
                'contactable_pct' => $globalTotal > 0 ? round($globalContactable / $globalTotal * 100) : 0,
            ],
            'per_type'      => $perType->values(),
            'per_type_lang' => $byTypeLang->values(),
        ]);
    }

    /**
     * Coverage matrix: for each (contact_type, country, language) shows:
     * - searched: bool (has an auto_campaign_task been completed for this combo?)
     * - contacts: int (how many contacts exist)
     * - with_email: int
     * - with_form: int (has _contact_form_url)
     *
     * Filterable by ?type=press&lang=fr
     */
    public function coverageMatrix(Request $request)
    {
        $typeFilter = $request->query('type');
        $langFilter = $request->query('lang');

        // 1. What has been searched (from auto_campaign_tasks)
        $searchedQuery = DB::table('auto_campaign_tasks')
            ->where('status', 'completed')
            ->select('contact_type', 'country', 'language',
                DB::raw('MAX(created_at) as last_searched_at'),
                DB::raw('SUM(contacts_found) as search_found'),
                DB::raw('SUM(contacts_imported) as search_imported')
            )
            ->groupBy('contact_type', 'country', 'language');

        if ($typeFilter) $searchedQuery->where('contact_type', $typeFilter);
        if ($langFilter) $searchedQuery->where('language', $langFilter);

        $searched = $searchedQuery->get()->keyBy(fn($r) => "{$r->contact_type}|{$r->country}|{$r->language}");

        // 2. What contacts we have (from influenceurs)
        $contactsQuery = Influenceur::query()
            ->select('contact_type', 'country', 'language',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN email IS NOT NULL THEN 1 ELSE 0 END) as with_email'),
                DB::raw('SUM(CASE WHEN phone IS NOT NULL THEN 1 ELSE 0 END) as with_phone'),
                DB::raw("SUM(CASE WHEN scraped_social::text LIKE '%_contact_form_url%' THEN 1 ELSE 0 END) as with_form")
            )
            ->whereNotNull('country')
            ->groupBy('contact_type', 'country', 'language');

        if ($typeFilter) $contactsQuery->where('contact_type', $typeFilter);
        if ($langFilter) $contactsQuery->where('language', $langFilter);

        $contacts = $contactsQuery->get()->keyBy(fn($r) => "{$r->contact_type}|{$r->country}|{$r->language}");

        // 3. Merge into matrix
        $allKeys = $searched->keys()->merge($contacts->keys())->unique();
        $matrix = [];

        foreach ($allKeys as $key) {
            [$type, $country, $lang] = explode('|', $key);
            $s = $searched->get($key);
            $c = $contacts->get($key);

            $total = $c->total ?? 0;
            $withEmail = $c->with_email ?? 0;

            $matrix[] = [
                'contact_type'    => $type,
                'country'         => $country,
                'language'        => $lang ?? 'fr',
                'searched'        => $s !== null,
                'last_searched_at' => $s->last_searched_at ?? null,
                'search_found'    => (int) ($s->search_found ?? 0),
                'search_imported' => (int) ($s->search_imported ?? 0),
                'contacts'        => (int) $total,
                'with_email'      => (int) $withEmail,
                'with_phone'      => (int) ($c->with_phone ?? 0),
                'with_form'       => (int) ($c->with_form ?? 0),
                'email_pct'       => $total > 0 ? round($withEmail / $total * 100) : 0,
                'contactable_pct' => $total > 0 ? round(($withEmail + ($c->with_form ?? 0)) / $total * 100) : 0,
            ];
        }

        // Sort by type, then country
        usort($matrix, fn($a, $b) => $a['contact_type'] <=> $b['contact_type'] ?: $a['country'] <=> $b['country']);

        // Summary by type
        $byType = collect($matrix)->groupBy('contact_type')->map(function ($rows, $type) {
            $countries = $rows->count();
            $searched = $rows->where('searched', true)->count();
            $totalContacts = $rows->sum('contacts');
            $totalEmails = $rows->sum('with_email');
            return [
                'type'             => $type,
                'countries'        => $countries,
                'countries_searched' => $searched,
                'coverage_pct'     => $countries > 0 ? round($searched / $countries * 100) : 0,
                'total_contacts'   => $totalContacts,
                'with_email'       => $totalEmails,
                'email_pct'        => $totalContacts > 0 ? round($totalEmails / $totalContacts * 100) : 0,
            ];
        })->values();

        return response()->json([
            'matrix'  => $matrix,
            'summary' => $byType,
            'filters' => [
                'type' => $typeFilter,
                'lang' => $langFilter,
            ],
        ]);
    }

    /**
     * Map lowercase country names to continents.
     */
    private function getContinentMap(): array
    {
        return [
            // Europe
            'france' => 'Europe', 'germany' => 'Europe', 'allemagne' => 'Europe',
            'uk' => 'Europe', 'united kingdom' => 'Europe', 'england' => 'Europe',
            'spain' => 'Europe', 'espagne' => 'Europe', 'italy' => 'Europe', 'italie' => 'Europe',
            'portugal' => 'Europe', 'belgium' => 'Europe', 'belgique' => 'Europe',
            'netherlands' => 'Europe', 'pays-bas' => 'Europe', 'holland' => 'Europe',
            'switzerland' => 'Europe', 'suisse' => 'Europe', 'austria' => 'Europe', 'autriche' => 'Europe',
            'sweden' => 'Europe', 'suède' => 'Europe', 'norway' => 'Europe', 'norvège' => 'Europe',
            'denmark' => 'Europe', 'danemark' => 'Europe', 'finland' => 'Europe', 'finlande' => 'Europe',
            'ireland' => 'Europe', 'irlande' => 'Europe', 'poland' => 'Europe', 'pologne' => 'Europe',
            'czech republic' => 'Europe', 'czechia' => 'Europe', 'république tchèque' => 'Europe',
            'romania' => 'Europe', 'roumanie' => 'Europe', 'hungary' => 'Europe', 'hongrie' => 'Europe',
            'greece' => 'Europe', 'grèce' => 'Europe', 'croatia' => 'Europe', 'croatie' => 'Europe',
            'bulgaria' => 'Europe', 'bulgarie' => 'Europe', 'serbia' => 'Europe', 'serbie' => 'Europe',
            'slovakia' => 'Europe', 'slovaquie' => 'Europe', 'slovenia' => 'Europe', 'slovénie' => 'Europe',
            'luxembourg' => 'Europe', 'malta' => 'Europe', 'malte' => 'Europe',
            'iceland' => 'Europe', 'islande' => 'Europe', 'cyprus' => 'Europe', 'chypre' => 'Europe',
            'estonia' => 'Europe', 'estonie' => 'Europe', 'latvia' => 'Europe', 'lettonie' => 'Europe',
            'lithuania' => 'Europe', 'lituanie' => 'Europe', 'ukraine' => 'Europe',
            'albania' => 'Europe', 'albanie' => 'Europe', 'montenegro' => 'Europe', 'monténégro' => 'Europe',
            'north macedonia' => 'Europe', 'macédoine du nord' => 'Europe',
            'bosnia' => 'Europe', 'bosnia and herzegovina' => 'Europe', 'bosnie' => 'Europe',
            'moldova' => 'Europe', 'moldavie' => 'Europe', 'belarus' => 'Europe', 'biélorussie' => 'Europe',
            'kosovo' => 'Europe', 'andorra' => 'Europe', 'andorre' => 'Europe',
            'monaco' => 'Europe', 'liechtenstein' => 'Europe', 'san marino' => 'Europe',

            // Africa
            'morocco' => 'Afrique', 'maroc' => 'Afrique', 'tunisia' => 'Afrique', 'tunisie' => 'Afrique',
            'algeria' => 'Afrique', 'algérie' => 'Afrique', 'egypt' => 'Afrique', 'égypte' => 'Afrique',
            'senegal' => 'Afrique', 'sénégal' => 'Afrique',
            'ivory coast' => 'Afrique', "cote d'ivoire" => 'Afrique', "côte d'ivoire" => 'Afrique',
            'cameroon' => 'Afrique', 'cameroun' => 'Afrique',
            'south africa' => 'Afrique', 'afrique du sud' => 'Afrique',
            'nigeria' => 'Afrique', 'ghana' => 'Afrique', 'kenya' => 'Afrique',
            'ethiopia' => 'Afrique', 'éthiopie' => 'Afrique', 'tanzania' => 'Afrique', 'tanzanie' => 'Afrique',
            'uganda' => 'Afrique', 'ouganda' => 'Afrique', 'mozambique' => 'Afrique',
            'madagascar' => 'Afrique', 'congo' => 'Afrique', 'rdc' => 'Afrique',
            'mali' => 'Afrique', 'burkina faso' => 'Afrique', 'niger' => 'Afrique',
            'guinea' => 'Afrique', 'guinée' => 'Afrique', 'benin' => 'Afrique', 'bénin' => 'Afrique',
            'togo' => 'Afrique', 'gabon' => 'Afrique', 'rwanda' => 'Afrique',
            'mauritius' => 'Afrique', 'maurice' => 'Afrique', 'reunion' => 'Afrique', 'réunion' => 'Afrique',
            'libya' => 'Afrique', 'libye' => 'Afrique', 'sudan' => 'Afrique', 'soudan' => 'Afrique',
            'zambia' => 'Afrique', 'zambie' => 'Afrique', 'zimbabwe' => 'Afrique',
            'botswana' => 'Afrique', 'namibia' => 'Afrique', 'namibie' => 'Afrique',
            'angola' => 'Afrique', 'chad' => 'Afrique', 'tchad' => 'Afrique',
            'somalia' => 'Afrique', 'somalie' => 'Afrique', 'eritrea' => 'Afrique', 'érythrée' => 'Afrique',
            'djibouti' => 'Afrique', 'comoros' => 'Afrique', 'comores' => 'Afrique',
            'mauritania' => 'Afrique', 'mauritanie' => 'Afrique',
            'sierra leone' => 'Afrique', 'liberia' => 'Afrique',
            'central african republic' => 'Afrique', 'centrafrique' => 'Afrique',
            'equatorial guinea' => 'Afrique', 'guinée équatoriale' => 'Afrique',
            'guinea-bissau' => 'Afrique', 'guinée-bissau' => 'Afrique',
            'cape verde' => 'Afrique', 'cap-vert' => 'Afrique',
            'sao tome and principe' => 'Afrique', 'são tomé-et-príncipe' => 'Afrique',
            'seychelles' => 'Afrique', 'malawi' => 'Afrique', 'lesotho' => 'Afrique',
            'eswatini' => 'Afrique', 'swaziland' => 'Afrique', 'gambia' => 'Afrique', 'gambie' => 'Afrique',
            'south sudan' => 'Afrique', 'soudan du sud' => 'Afrique',
            'democratic republic of the congo' => 'Afrique', 'republic of the congo' => 'Afrique',
            'burundi' => 'Afrique',

            // Americas
            'usa' => 'Amériques', 'united states' => 'Amériques', 'états-unis' => 'Amériques',
            'canada' => 'Amériques', 'mexico' => 'Amériques', 'mexique' => 'Amériques',
            'brazil' => 'Amériques', 'brésil' => 'Amériques',
            'argentina' => 'Amériques', 'argentine' => 'Amériques',
            'colombia' => 'Amériques', 'colombie' => 'Amériques',
            'chile' => 'Amériques', 'chili' => 'Amériques',
            'peru' => 'Amériques', 'pérou' => 'Amériques',
            'venezuela' => 'Amériques', 'ecuador' => 'Amériques', 'équateur' => 'Amériques',
            'bolivia' => 'Amériques', 'bolivie' => 'Amériques',
            'paraguay' => 'Amériques', 'uruguay' => 'Amériques',
            'costa rica' => 'Amériques', 'panama' => 'Amériques',
            'guatemala' => 'Amériques', 'honduras' => 'Amériques',
            'el salvador' => 'Amériques', 'nicaragua' => 'Amériques',
            'cuba' => 'Amériques', 'dominican republic' => 'Amériques', 'république dominicaine' => 'Amériques',
            'haiti' => 'Amériques', 'haïti' => 'Amériques',
            'jamaica' => 'Amériques', 'jamaïque' => 'Amériques',
            'trinidad and tobago' => 'Amériques', 'trinité-et-tobago' => 'Amériques',
            'puerto rico' => 'Amériques', 'porto rico' => 'Amériques',
            'guadeloupe' => 'Amériques', 'martinique' => 'Amériques', 'guyane' => 'Amériques',
            'french guiana' => 'Amériques', 'guyana' => 'Amériques', 'suriname' => 'Amériques',
            'belize' => 'Amériques', 'bahamas' => 'Amériques', 'barbados' => 'Amériques', 'barbade' => 'Amériques',

            // Asia
            'japan' => 'Asie', 'japon' => 'Asie', 'china' => 'Asie', 'chine' => 'Asie',
            'india' => 'Asie', 'inde' => 'Asie', 'south korea' => 'Asie', 'corée du sud' => 'Asie',
            'thailand' => 'Asie', 'thaïlande' => 'Asie',
            'vietnam' => 'Asie', 'indonesia' => 'Asie', 'indonésie' => 'Asie',
            'philippines' => 'Asie', 'malaysia' => 'Asie', 'malaisie' => 'Asie',
            'singapore' => 'Asie', 'singapour' => 'Asie',
            'taiwan' => 'Asie', 'taïwan' => 'Asie', 'hong kong' => 'Asie',
            'bangladesh' => 'Asie', 'pakistan' => 'Asie', 'sri lanka' => 'Asie',
            'nepal' => 'Asie', 'népal' => 'Asie', 'myanmar' => 'Asie', 'birmanie' => 'Asie',
            'cambodia' => 'Asie', 'cambodge' => 'Asie', 'laos' => 'Asie',
            'mongolia' => 'Asie', 'mongolie' => 'Asie',
            'uzbekistan' => 'Asie', 'ouzbékistan' => 'Asie',
            'kazakhstan' => 'Asie', 'kyrgyzstan' => 'Asie', 'kirghizistan' => 'Asie',
            'tajikistan' => 'Asie', 'tadjikistan' => 'Asie',
            'turkmenistan' => 'Asie', 'turkménistan' => 'Asie',
            'afghanistan' => 'Asie', 'maldives' => 'Asie', 'bhutan' => 'Asie', 'bhoutan' => 'Asie',
            'brunei' => 'Asie', 'timor-leste' => 'Asie', 'east timor' => 'Asie',
            'north korea' => 'Asie', 'corée du nord' => 'Asie',

            // Middle East
            'turkey' => 'Moyen-Orient', 'turquie' => 'Moyen-Orient',
            'saudi arabia' => 'Moyen-Orient', 'arabie saoudite' => 'Moyen-Orient',
            'uae' => 'Moyen-Orient', 'united arab emirates' => 'Moyen-Orient',
            'émirats arabes unis' => 'Moyen-Orient', 'emirats arabes unis' => 'Moyen-Orient',
            'qatar' => 'Moyen-Orient', 'kuwait' => 'Moyen-Orient', 'koweït' => 'Moyen-Orient',
            'bahrain' => 'Moyen-Orient', 'bahreïn' => 'Moyen-Orient',
            'oman' => 'Moyen-Orient', 'yemen' => 'Moyen-Orient', 'yémen' => 'Moyen-Orient',
            'iraq' => 'Moyen-Orient', 'irak' => 'Moyen-Orient',
            'iran' => 'Moyen-Orient', 'israel' => 'Moyen-Orient', 'israël' => 'Moyen-Orient',
            'palestine' => 'Moyen-Orient', 'jordan' => 'Moyen-Orient', 'jordanie' => 'Moyen-Orient',
            'lebanon' => 'Moyen-Orient', 'liban' => 'Moyen-Orient',
            'syria' => 'Moyen-Orient', 'syrie' => 'Moyen-Orient',
            'armenia' => 'Moyen-Orient', 'arménie' => 'Moyen-Orient',
            'georgia' => 'Moyen-Orient', 'géorgie' => 'Moyen-Orient',
            'azerbaijan' => 'Moyen-Orient', 'azerbaïdjan' => 'Moyen-Orient',

            // Oceania
            'australia' => 'Océanie', 'australie' => 'Océanie',
            'new zealand' => 'Océanie', 'nouvelle-zélande' => 'Océanie',
            'fiji' => 'Océanie', 'fidji' => 'Océanie',
            'papua new guinea' => 'Océanie', 'papouasie-nouvelle-guinée' => 'Océanie',
            'new caledonia' => 'Océanie', 'nouvelle-calédonie' => 'Océanie',
            'french polynesia' => 'Océanie', 'polynésie française' => 'Océanie',
            'samoa' => 'Océanie', 'tonga' => 'Océanie', 'vanuatu' => 'Océanie',
            'guam' => 'Océanie', 'palau' => 'Océanie', 'micronesia' => 'Océanie',
            'marshall islands' => 'Océanie', 'kiribati' => 'Océanie', 'tuvalu' => 'Océanie',
            'nauru' => 'Océanie', 'solomon islands' => 'Océanie',

            // Russia (transcontinental)
            'russia' => 'Europe', 'russie' => 'Europe',
        ];
    }

    /**
     * Count contacts for a specific category (and optionally a contact_type).
     * GET /api/stats/category-count?category=institutionnel[&contact_type=consulat]
     */
    public function categoryCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Influenceur::query();

        if ($request->user()->isResearcher()) {
            $query->where('created_by', $request->user()->id);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->contact_type) {
            $query->where('contact_type', $request->contact_type);
        }

        return response()->json(['count' => $query->count()]);
    }
}
