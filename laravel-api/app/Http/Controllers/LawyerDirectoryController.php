<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeLawyerDirectoryJob;
use App\Models\Lawyer;
use App\Models\LawyerDirectorySource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawyerDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lawyer::query();

        if ($request->filled('source')) {
            $query->where('source_slug', $request->input('source'));
        }
        if ($request->filled('country')) {
            $query->where('country_code', strtoupper($request->input('country')));
        }
        if ($request->filled('country_name')) {
            $query->where('country', 'ilike', '%' . $request->input('country_name') . '%');
        }
        if ($request->filled('city')) {
            $query->where('city', 'ilike', '%' . $request->input('city') . '%');
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('specialty')) {
            $query->where('specialty', 'ilike', '%' . $request->input('specialty') . '%');
        }
        if ($request->filled('immigration_only')) {
            $query->where('is_immigration_lawyer', true);
        }
        if ($request->filled('has_email')) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('firm_name', 'ilike', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $allowedSorts = ['full_name', 'country', 'city', 'language', 'specialty', 'firm_name', 'created_at', 'scraped_at'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Lawyer::findOrFail($id));
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Lawyer::query();
        if ($request->filled('source')) {
            $query->where('source_slug', $request->input('source'));
        }

        $total = (clone $query)->count();
        $withEmail = (clone $query)->whereNotNull('email')->where('email', '!=', '')->count();
        $withPhone = (clone $query)->whereNotNull('phone')->where('phone', '!=', '')->count();
        $immigration = (clone $query)->where('is_immigration_lawyer', true)->count();

        $byCountry = Lawyer::selectRaw('country, country_code, COUNT(*) as count')
            ->whereNotNull('country')
            ->groupBy('country', 'country_code')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        $byLanguage = Lawyer::selectRaw('language, COUNT(*) as count')
            ->whereNotNull('language')
            ->groupBy('language')
            ->orderByDesc('count')
            ->get();

        $bySpecialty = Lawyer::selectRaw('specialty, COUNT(*) as count')
            ->whereNotNull('specialty')
            ->where('specialty', '!=', '')
            ->groupBy('specialty')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $bySource = Lawyer::selectRaw('source_slug, COUNT(*) as count')
            ->groupBy('source_slug')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'total'        => $total,
            'with_email'   => $withEmail,
            'with_phone'   => $withPhone,
            'immigration'  => $immigration,
            'by_country'   => $byCountry,
            'by_language'  => $byLanguage,
            'by_specialty' => $bySpecialty,
            'by_source'    => $bySource,
        ]);
    }

    public function countries(): JsonResponse
    {
        $countries = Lawyer::selectRaw('country, country_code, language, COUNT(*) as count')
            ->whereNotNull('country')
            ->groupBy('country', 'country_code', 'language')
            ->orderByDesc('count')
            ->get();

        return response()->json($countries);
    }

    public function sources(): JsonResponse
    {
        $sources = LawyerDirectorySource::orderBy('name')->get();
        return response()->json($sources);
    }

    public function scrape(string $sourceSlug): JsonResponse
    {
        $source = LawyerDirectorySource::where('slug', $sourceSlug)->first();

        if (!$source) {
            return response()->json(['error' => "Source '{$sourceSlug}' not found"], 404);
        }

        if ($source->status === 'scraping') {
            return response()->json(['error' => 'Scraping already in progress'], 409);
        }

        ScrapeLawyerDirectoryJob::dispatch($sourceSlug);

        return response()->json(['message' => "Lawyer directory scraping started for {$source->name}"]);
    }

    public function scrapeAll(): JsonResponse
    {
        $sources = LawyerDirectorySource::where('robots_txt_ok', true)
            ->where('status', '!=', 'scraping')
            ->get();

        foreach ($sources as $source) {
            ScrapeLawyerDirectoryJob::dispatch($source->slug);
        }

        return response()->json([
            'message' => 'Scraping started for ' . $sources->count() . ' sources',
            'sources' => $sources->pluck('slug'),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $query = Lawyer::query();

        if ($request->filled('source')) $query->where('source_slug', $request->input('source'));
        if ($request->filled('country')) $query->where('country_code', strtoupper($request->input('country')));
        if ($request->filled('language')) $query->where('language', $request->input('language'));
        if ($request->filled('specialty')) $query->where('specialty', 'ilike', '%' . $request->input('specialty') . '%');
        if ($request->filled('immigration_only')) $query->where('is_immigration_lawyer', true);

        $lawyers = $query->select([
            'full_name', 'email', 'phone', 'firm_name', 'website',
            'country', 'country_code', 'city', 'region', 'address',
            'specialty', 'bar_association', 'language', 'source_slug',
        ])->limit(10000)->get();

        return response()->json($lawyers);
    }
}
