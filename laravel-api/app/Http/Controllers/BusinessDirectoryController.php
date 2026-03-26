<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeBusinessDetailsJob;
use App\Jobs\ScrapeBusinessDirectoryJob;
use App\Models\ContentBusiness;
use App\Models\ContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContentBusiness::with('source:id,name,slug');

        // Filters
        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) $query->where('source_id', $source->id);
        }
        if ($request->filled('country')) {
            $query->where('country_slug', $request->input('country'));
        }
        if ($request->filled('city')) {
            $query->where('city', 'ilike', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('city')) . '%');
        }
        if ($request->filled('category')) {
            $query->where('category_slug', $request->input('category'));
        }
        if ($request->filled('subcategory')) {
            $query->where('subcategory_slug', $request->input('subcategory'));
        }
        if ($request->filled('has_email')) {
            $query->whereNotNull('contact_email')->where('contact_email', '!=', '');
        }
        if ($request->filled('has_website')) {
            $query->whereNotNull('website')->where('website', '!=', '');
        }
        if ($request->filled('is_premium')) {
            $query->where('is_premium', $request->boolean('is_premium'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                  ->orWhere('contact_name', 'ilike', '%' . $search . '%')
                  ->orWhere('contact_email', 'ilike', '%' . $search . '%')
                  ->orWhere('description', 'ilike', '%' . $search . '%');
            });
        }

        // Sort
        $allowedSorts = ['name', 'recommendations', 'country', 'city', 'category', 'created_at', 'scraped_at'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'recommendations';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(ContentBusiness::findOrFail($id));
    }

    public function stats(Request $request): JsonResponse
    {
        $query = ContentBusiness::query();
        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) $query->where('source_id', $source->id);
        }

        $total = (clone $query)->count();
        $withEmail = (clone $query)->whereNotNull('contact_email')->where('contact_email', '!=', '')->count();
        $withPhone = (clone $query)->whereNotNull('contact_phone')->where('contact_phone', '!=', '')->count();
        $withWebsite = (clone $query)->whereNotNull('website')->where('website', '!=', '')->count();
        $premium = (clone $query)->where('is_premium', true)->count();

        $byCountry = ContentBusiness::selectRaw('country, country_slug, COUNT(*) as count')
            ->groupBy('country', 'country_slug')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $byCategory = ContentBusiness::selectRaw('category, category_slug, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category', 'category_slug')
            ->orderByDesc('count')
            ->get();

        $byCity = ContentBusiness::selectRaw('city, country, COUNT(*) as count')
            ->whereNotNull('city')
            ->groupBy('city', 'country')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        return response()->json([
            'total'        => $total,
            'with_email'   => $withEmail,
            'with_phone'   => $withPhone,
            'with_website' => $withWebsite,
            'premium'      => $premium,
            'by_country'   => $byCountry,
            'by_category'  => $byCategory,
            'by_city'      => $byCity,
        ]);
    }

    public function scrape(string $sourceSlug): JsonResponse
    {
        $source = ContentSource::where('slug', $sourceSlug)->firstOrFail();
        ScrapeBusinessDirectoryJob::dispatch($source->id);
        return response()->json(['message' => 'Business directory scraping started']);
    }

    public function scrapeDetails(string $sourceSlug): JsonResponse
    {
        $source = ContentSource::where('slug', $sourceSlug)->firstOrFail();
        ScrapeBusinessDetailsJob::dispatch($source->id);
        return response()->json(['message' => 'Business details (emails) scraping started']);
    }

    public function countries(Request $request): JsonResponse
    {
        $query = ContentBusiness::selectRaw('country, country_slug, continent, COUNT(*) as count, SUM(CASE WHEN contact_email IS NOT NULL AND contact_email != \'\' THEN 1 ELSE 0 END) as with_email')
            ->groupBy('country', 'country_slug', 'continent')
            ->orderBy('continent')
            ->orderBy('country');

        return response()->json($query->get());
    }

    public function categories(): JsonResponse
    {
        $categories = ContentBusiness::selectRaw('category, category_slug, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category', 'category_slug')
            ->orderByDesc('count')
            ->get();

        $subcategories = ContentBusiness::selectRaw('category, subcategory, subcategory_slug, COUNT(*) as count')
            ->whereNotNull('subcategory')
            ->groupBy('category', 'subcategory', 'subcategory_slug')
            ->orderBy('category')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'categories'    => $categories,
            'subcategories' => $subcategories,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = ContentBusiness::query();

        if ($request->filled('country')) $query->where('country_slug', $request->input('country'));
        if ($request->filled('category')) $query->where('category_slug', $request->input('category'));
        if ($request->filled('has_email')) $query->whereNotNull('contact_email')->where('contact_email', '!=', '');
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('name', 'ilike', '%' . $search . '%');
        }

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nom', 'Contact', 'Email', 'Telephone', 'Site Web', 'Adresse', 'Ville', 'Pays', 'Continent', 'Categorie', 'Sous-categorie', 'Premium', 'Recommandations', 'Description']);

            $query->orderBy('country')->orderBy('name')->chunk(500, function ($businesses) use ($out) {
                foreach ($businesses as $biz) {
                    fputcsv($out, [
                        $biz->name,
                        $biz->contact_name,
                        $biz->contact_email,
                        $biz->contact_phone,
                        $biz->website,
                        $biz->address,
                        $biz->city,
                        $biz->country,
                        $biz->continent,
                        $biz->category,
                        $biz->subcategory,
                        $biz->is_premium ? 'Oui' : 'Non',
                        $biz->recommendations,
                        mb_substr($biz->description ?? '', 0, 300),
                    ]);
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="businesses-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
