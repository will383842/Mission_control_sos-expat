<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerationSourceController extends Controller
{
    /**
     * List all source categories with counts (brut vs nettoyé).
     */
    public function categories(): JsonResponse
    {
        $data = Cache::remember('gen-source-categories', 300, function () {
            return DB::table('generation_source_categories as gsc')
                ->leftJoin('generation_source_items as gsi', 'gsi.category_slug', '=', 'gsc.slug')
                ->selectRaw("
                    gsc.id, gsc.slug, gsc.name, gsc.description, gsc.icon, gsc.sort_order,
                    COUNT(gsi.id) as total_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.is_cleaned = true) as cleaned_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.is_cleaned = false) as raw_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.processing_status = 'ready') as ready_items,
                    COUNT(DISTINCT gsi.country_slug) FILTER (WHERE gsi.country_slug IS NOT NULL) as countries,
                    COUNT(DISTINCT gsi.theme) FILTER (WHERE gsi.theme IS NOT NULL) as themes,
                    COUNT(DISTINCT gsi.sub_category) FILTER (WHERE gsi.sub_category IS NOT NULL) as sub_categories
                ")
                ->groupBy('gsc.id', 'gsc.slug', 'gsc.name', 'gsc.description', 'gsc.icon', 'gsc.sort_order')
                ->orderBy('gsc.sort_order')
                ->get();
        });

        return response()->json($data);
    }

    /**
     * Items in a category, with filters for sub_category, country, theme, status.
     */
    public function categoryItems(string $categorySlug, Request $request): JsonResponse
    {
        $query = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->select('id', 'source_type', 'source_id', 'title', 'country', 'country_slug', 'theme', 'sub_category', 'language', 'word_count', 'quality_score', 'is_cleaned', 'processing_status', 'used_count', 'data_json');

        // Filter: brut vs nettoyé
        if ($request->filled('cleaned')) {
            $query->where('is_cleaned', $request->boolean('cleaned'));
        }

        if ($request->filled('status')) {
            $query->where('processing_status', $request->input('status'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', $request->input('sub_category'));
        }

        if ($request->filled('country_slug')) {
            $query->where('country_slug', $request->input('country_slug'));
        }

        if ($request->filled('theme')) {
            $query->where('theme', $request->input('theme'));
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('title', 'ilike', '%' . $search . '%');
        }

        $sortBy = $request->input('sort', 'quality_score');
        $sortDir = $request->input('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $items = $query->paginate($request->input('per_page', 50));

        // Sub-categories breakdown for sidebar
        $subCategories = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->selectRaw('sub_category, COUNT(*) as count, COUNT(*) FILTER (WHERE is_cleaned) as cleaned, COUNT(*) FILTER (WHERE NOT is_cleaned) as raw')
            ->whereNotNull('sub_category')
            ->groupBy('sub_category')
            ->orderByDesc('count')
            ->get();

        // Countries in this category
        $countries = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->whereNotNull('country_slug')
            ->selectRaw('country, country_slug, COUNT(*) as count')
            ->groupBy('country', 'country_slug')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        // Themes in this category
        $themes = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->whereNotNull('theme')
            ->selectRaw('theme, COUNT(*) as count')
            ->groupBy('theme')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'items'          => $items,
            'sub_categories' => $subCategories,
            'countries'      => $countries,
            'themes'         => $themes,
        ]);
    }

    /**
     * Global stats: brut vs nettoyé across all categories.
     */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('gen-source-stats', 300, function () {
            $overall = DB::table('generation_source_items')
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE is_cleaned) as cleaned,
                    COUNT(*) FILTER (WHERE NOT is_cleaned) as raw,
                    COUNT(*) FILTER (WHERE processing_status = 'ready') as ready,
                    COUNT(*) FILTER (WHERE processing_status = 'used') as used,
                    COUNT(DISTINCT country_slug) FILTER (WHERE country_slug IS NOT NULL) as countries,
                    COUNT(DISTINCT theme) FILTER (WHERE theme IS NOT NULL) as themes
                ")
                ->first();

            $byStatus = DB::table('generation_source_items')
                ->selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->get();

            $bySourceType = DB::table('generation_source_items')
                ->selectRaw('source_type, COUNT(*) as count')
                ->groupBy('source_type')
                ->get();

            return [
                'overall'        => $overall,
                'by_status'      => $byStatus,
                'by_source_type' => $bySourceType,
            ];
        });

        return response()->json($data);
    }
}
