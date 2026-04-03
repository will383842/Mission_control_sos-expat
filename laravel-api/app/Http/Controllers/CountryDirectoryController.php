<?php

namespace App\Http\Controllers;

use App\Models\CountryDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CountryDirectoryController extends Controller
{
    // ── Lecture ────────────────────────────────────────────────────────────────

    /**
     * Liste tous les pays avec leurs compteurs.
     */
    public function countries(): JsonResponse
    {
        $data = Cache::remember('directory:countries', 60, function () {
            return CountryDirectory::query()
                ->where('is_active', true)
                ->where('country_code', '!=', 'XX')
                ->selectRaw("
                    country_code, country_name, country_slug, continent,
                    COUNT(*) as total_links,
                    COUNT(*) FILTER (WHERE is_official) as official_links,
                    COUNT(*) FILTER (WHERE address IS NOT NULL) as with_address,
                    COUNT(*) FILTER (WHERE phone IS NOT NULL) as with_phone,
                    COUNT(DISTINCT category) as categories_count,
                    MAX(emergency_number) as emergency_number
                ")
                ->groupBy('country_code', 'country_name', 'country_slug', 'continent')
                ->orderBy('continent')
                ->orderBy('country_name')
                ->get();
        });

        return response()->json($data);
    }

    /**
     * Liste toutes les nationalités ayant des ambassades importées.
     */
    public function nationalities(): JsonResponse
    {
        $data = Cache::remember('directory:nationalities', 60, function () {
            return CountryDirectory::query()
                ->where('is_active', true)
                ->whereNotNull('nationality_code')
                ->where('category', 'ambassade')
                ->selectRaw('nationality_code, nationality_name, COUNT(*) as embassy_count')
                ->groupBy('nationality_code', 'nationality_name')
                ->orderBy('nationality_name')
                ->get();
        });

        return response()->json($data);
    }

    /**
     * Annuaire complet d'un pays hôte (toutes catégories).
     */
    public function country(string $countryCode): JsonResponse
    {
        $entries = CountryDirectory::query()
            ->where('is_active', true)
            ->where('country_code', strtoupper($countryCode))
            ->orderBy('category')
            ->orderByDesc('trust_score')
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['error' => 'Pays non trouvé'], 404);
        }

        $global = CountryDirectory::query()
            ->where('is_active', true)
            ->where('country_code', 'XX')
            ->orderBy('category')
            ->orderByDesc('trust_score')
            ->get();

        $first = $entries->first();

        return response()->json([
            'country' => [
                'code'             => $first->country_code,
                'name'             => $first->country_name,
                'slug'             => $first->country_slug,
                'continent'        => $first->continent,
                'emergency_number' => $first->emergency_number,
            ],
            'entries' => $entries->groupBy('category'),
            'global'  => $global->groupBy('category'),
        ]);
    }

    /**
     * Ambassades d'une nationalité dans un pays hôte (ou toutes si pas de filtre pays).
     * GET /country-directory/embassies?nationality=DE&host_country=TH&lang=fr
     */
    public function embassies(Request $request): JsonResponse
    {
        $query = CountryDirectory::query()
            ->where('is_active', true)
            ->where('category', 'ambassade')
            ->whereNotNull('nationality_code');

        if ($request->filled('nationality')) {
            $query->where('nationality_code', strtoupper($request->input('nationality')));
        }
        if ($request->filled('host_country')) {
            $query->where('country_code', strtoupper($request->input('host_country')));
        }

        $lang    = $request->input('lang', 'fr');
        $entries = $query->orderBy('country_name')->get();

        // Injecter le titre traduit si demandé
        if ($lang !== 'fr') {
            $entries = $entries->map(function (CountryDirectory $e) use ($lang) {
                $arr = $e->toArray();
                $arr['title_translated'] = $e->getTitle($lang);
                $arr['description_translated'] = $e->getDescription($lang);
                return $arr;
            });
        }

        return response()->json($entries->values());
    }

    /**
     * Statistiques globales de l'annuaire.
     */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('directory:stats', 60, function () {
            return [
                'total_entries'       => CountryDirectory::where('is_active', true)->count(),
                'countries'           => CountryDirectory::where('is_active', true)->where('country_code', '!=', 'XX')->distinct('country_code')->count('country_code'),
                'nationalities'       => CountryDirectory::where('is_active', true)->whereNotNull('nationality_code')->distinct('nationality_code')->count('nationality_code'),
                'ambassades_total'    => CountryDirectory::where('is_active', true)->where('category', 'ambassade')->count(),
                'with_address'        => CountryDirectory::where('is_active', true)->whereNotNull('address')->count(),
                'with_phone'          => CountryDirectory::where('is_active', true)->whereNotNull('phone')->count(),
                'with_email'          => CountryDirectory::where('is_active', true)->whereNotNull('email')->count(),
                'with_gps'            => CountryDirectory::where('is_active', true)->whereNotNull('latitude')->count(),
                'official'            => CountryDirectory::where('is_active', true)->where('is_official', true)->count(),
                'by_continent'        => CountryDirectory::where('is_active', true)->where('country_code', '!=', 'XX')
                    ->selectRaw('continent, COUNT(DISTINCT country_code) as countries, COUNT(*) as links')
                    ->groupBy('continent')
                    ->get(),
                'by_category'         => CountryDirectory::where('is_active', true)
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->orderByDesc('count')
                    ->get(),
                'top_nationalities'   => CountryDirectory::where('is_active', true)
                    ->where('category', 'ambassade')
                    ->whereNotNull('nationality_code')
                    ->selectRaw('nationality_code, nationality_name, COUNT(*) as count')
                    ->groupBy('nationality_code', 'nationality_name')
                    ->orderByDesc('count')
                    ->limit(20)
                    ->get(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Export vers le blog (format external_links).
     */
    public function exportForBlog(Request $request): JsonResponse
    {
        $query = CountryDirectory::query()->where('is_active', true);

        if ($request->filled('country')) {
            $query->where('country_code', strtoupper($request->input('country')));
        }

        $entries = $query->orderBy('country_code')->orderBy('category')->get();

        $blogLinks = $entries->map(fn (CountryDirectory $e) => [
            'keyword'      => $e->country_code === 'XX'
                ? "annuaire:{$e->category}"
                : "annuaire:" . strtolower($e->country_code) . ":{$e->category}" . ($e->sub_category ? "-{$e->sub_category}" : ''),
            'url'          => $e->url,
            'domain'       => $e->domain,
            'rel_attribute'=> $e->rel_attribute,
            'is_trusted'   => $e->is_official,
            'is_active'    => true,
        ]);

        return response()->json($blogLinks->values());
    }

    // ── CRUD ───────────────────────────────────────────────────────────────────

    /**
     * Créer une entrée.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        // Auto-dériver le domain depuis l'url si absent
        if (empty($validated['domain']) && !empty($validated['url'])) {
            $host = parse_url($validated['url'], PHP_URL_HOST) ?: '';
            $validated['domain'] = preg_replace('/^www\./', '', $host);
        }

        $entry = CountryDirectory::create($validated);
        $this->clearCaches();

        return response()->json($entry, 201);
    }

    /**
     * Mettre à jour une entrée.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entry     = CountryDirectory::findOrFail($id);
        $validated = $request->validate($this->rules(false));

        $entry->update($validated);
        $this->clearCaches();

        return response()->json($entry->fresh());
    }

    /**
     * Supprimer (soft-delete via is_active) ou suppression physique.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = CountryDirectory::findOrFail($id);

        if ($request->boolean('soft', true)) {
            $entry->update(['is_active' => false]);
        } else {
            $entry->delete();
        }

        $this->clearCaches();

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    // ── Privés ─────────────────────────────────────────────────────────────────

    private function rules(bool $required = true): array
    {
        $req = fn(string $rule) => $required ? "required|{$rule}" : $rule;

        return [
            'country_code'      => $req('string|size:2'),
            'country_name'      => $req('string|max:100'),
            'country_slug'      => $req('string|max:100'),
            'continent'         => $req('string|max:50'),
            'nationality_code'  => 'nullable|string|size:2',
            'nationality_name'  => 'nullable|string|max:100',
            'category'          => $req('string|max:50'),
            'sub_category'      => 'nullable|string|max:100',
            'title'             => $req('string|max:300'),
            'url'               => $req('url|max:1000'),
            'domain'            => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'language'          => 'nullable|string|max:10',
            'translations'      => 'nullable|array',
            'translations.*.title'       => 'nullable|string|max:300',
            'translations.*.description' => 'nullable|string',
            'address'           => 'nullable|string|max:500',
            'city'              => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:100',
            'phone_emergency'   => 'nullable|string|max:100',
            'email'             => 'nullable|email|max:255',
            'opening_hours'     => 'nullable|string|max:500',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'emergency_number'  => 'nullable|string|max:50',
            'trust_score'       => 'nullable|integer|min:0|max:100',
            'is_official'       => 'boolean',
            'is_active'         => 'boolean',
            'anchor_text'       => 'nullable|string|max:300',
            'rel_attribute'     => 'nullable|string|max:50',
        ];
    }

    private function clearCaches(): void
    {
        Cache::forget('directory:countries');
        Cache::forget('directory:stats');
        Cache::forget('directory:nationalities');
    }
}
