<?php

namespace App\Http\Controllers;

use App\Models\CountryDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CountryDirectoryController extends Controller
{
    /**
     * List all countries with their link counts.
     */
    public function countries(): JsonResponse
    {
        $data = Cache::remember('directory:countries', 3600, function () {
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
     * Full directory for a specific country.
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
            return response()->json(['error' => 'Country not found'], 404);
        }

        // Also include global resources
        $global = CountryDirectory::query()
            ->where('is_active', true)
            ->where('country_code', 'XX')
            ->orderBy('category')
            ->orderByDesc('trust_score')
            ->get();

        return response()->json([
            'country' => [
                'code' => $entries->first()->country_code,
                'name' => $entries->first()->country_name,
                'slug' => $entries->first()->country_slug,
                'continent' => $entries->first()->continent,
                'emergency_number' => $entries->first()->emergency_number,
            ],
            'entries' => $entries->groupBy('category'),
            'global' => $global->groupBy('category'),
        ]);
    }

    /**
     * Export directory as external_links format for the blog.
     * Used by blog sync job.
     */
    public function exportForBlog(Request $request): JsonResponse
    {
        $query = CountryDirectory::query()->where('is_active', true);

        if ($request->filled('country')) {
            $query->where('country_code', strtoupper($request->input('country')));
        }

        $entries = $query->orderBy('country_code')->orderBy('category')->get();

        $blogLinks = $entries->map(fn (CountryDirectory $e) => [
            'keyword' => $e->country_code === 'XX'
                ? "annuaire:{$e->category}"
                : "annuaire:" . strtolower($e->country_code) . ":{$e->category}" . ($e->sub_category ? "-{$e->sub_category}" : ''),
            'url' => $e->url,
            'domain' => $e->domain,
            'rel_attribute' => $e->rel_attribute,
            'is_trusted' => $e->is_official,
            'is_active' => true,
        ]);

        return response()->json($blogLinks->values());
    }

    /**
     * Stats overview.
     */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('directory:stats', 3600, function () {
            return [
                'total_entries' => CountryDirectory::where('is_active', true)->count(),
                'countries' => CountryDirectory::where('is_active', true)->where('country_code', '!=', 'XX')->distinct('country_code')->count('country_code'),
                'with_address' => CountryDirectory::where('is_active', true)->whereNotNull('address')->count(),
                'with_phone' => CountryDirectory::where('is_active', true)->whereNotNull('phone')->count(),
                'with_email' => CountryDirectory::where('is_active', true)->whereNotNull('email')->count(),
                'official' => CountryDirectory::where('is_active', true)->where('is_official', true)->count(),
                'by_continent' => CountryDirectory::where('is_active', true)->where('country_code', '!=', 'XX')
                    ->selectRaw('continent, COUNT(DISTINCT country_code) as countries, COUNT(*) as links')
                    ->groupBy('continent')
                    ->get(),
                'by_category' => CountryDirectory::where('is_active', true)
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->orderByDesc('count')
                    ->get(),
            ];
        });

        return response()->json($data);
    }
}
