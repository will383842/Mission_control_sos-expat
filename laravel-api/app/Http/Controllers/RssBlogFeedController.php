<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeBloggerRssFeedsJob;
use App\Models\RssBlogFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Option D — P5 : Controller CRUD pour gérer les feeds RSS de blogs.
 * Tout sous middleware role:admin.
 */
class RssBlogFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RssBlogFeed::query();

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }
        if ($request->filled('search')) {
            $needle = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'ILIKE', $needle)
                  ->orWhere('url', 'ILIKE', $needle)
                  ->orWhere('base_url', 'ILIKE', $needle);
            });
        }

        $feeds = $query->orderBy('name')->paginate(50);

        return response()->json($feeds);
    }

    public function show(RssBlogFeed $feed): JsonResponse
    {
        return response()->json(['feed' => $feed]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $feed = RssBlogFeed::create($data);

        return response()->json(['feed' => $feed], 201);
    }

    public function update(Request $request, RssBlogFeed $feed): JsonResponse
    {
        $data = $this->validated($request, false, $feed->id);
        $feed->update($data);

        return response()->json(['feed' => $feed->fresh()]);
    }

    public function destroy(RssBlogFeed $feed): JsonResponse
    {
        // Soft delete logique : désactiver plutôt que supprimer
        $feed->update(['active' => false]);

        return response()->json([
            'message' => 'Feed désactivé.',
            'feed'    => $feed->fresh(),
        ]);
    }

    public function scrape(RssBlogFeed $feed): JsonResponse
    {
        ScrapeBloggerRssFeedsJob::dispatch($feed->id);

        return response()->json([
            'dispatched' => true,
            'feed_id'    => $feed->id,
            'message'    => "Scrape dispatché pour '{$feed->name}'.",
        ], 202);
    }

    private function validated(Request $request, bool $isCreate, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:rss_blog_feeds,url';
        if ($ignoreId) {
            $uniqueRule .= ",{$ignoreId}";
        }

        $required = $isCreate ? 'required' : 'sometimes';

        return $request->validate([
            'name'                    => "{$required}|string|max:255",
            'url'                     => "{$required}|url|max:500|{$uniqueRule}",
            'base_url'                => 'nullable|url|max:500',
            'language'                => 'sometimes|string|max:5',
            'country'                 => 'nullable|string|max:100',
            'category'                => 'nullable|string|max:100',
            'active'                  => 'sometimes|boolean',
            'fetch_about'             => 'sometimes|boolean',
            'fetch_pattern_inference' => 'sometimes|boolean',
            'fetch_interval_hours'    => 'sometimes|integer|min:1|max:168',
            'notes'                   => 'nullable|string|max:2000',
        ]);
    }
}
