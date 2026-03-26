<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeForumQuestionsJob;
use App\Models\ContentQuestion;
use App\Models\ContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContentQuestion::with('source:id,name,slug');

        if ($request->filled('country')) $query->where('country_slug', $request->input('country'));
        if ($request->filled('continent')) $query->where('continent', $request->input('continent'));
        if ($request->filled('status')) $query->where('article_status', $request->input('status'));
        if ($request->filled('min_views')) $query->where('views', '>=', (int) $request->input('min_views'));
        if ($request->filled('min_replies')) $query->where('replies', '>=', (int) $request->input('min_replies'));
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('title', 'ilike', '%' . $search . '%');
        }

        $allowedSorts = ['views', 'replies', 'title', 'country', 'created_at', 'scraped_at'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'views';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->orderBy($sort, $direction)->paginate($perPage));
    }

    public function stats(): JsonResponse
    {
        $total = ContentQuestion::count();

        $byCountry = ContentQuestion::selectRaw('country, country_slug, COUNT(*) as count, SUM(views) as total_views')
            ->groupBy('country', 'country_slug')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $byStatus = ContentQuestion::selectRaw('article_status, COUNT(*) as count')
            ->groupBy('article_status')
            ->get();

        $topViewed = ContentQuestion::orderByDesc('views')
            ->limit(20)
            ->select('id', 'title', 'country', 'views', 'replies', 'article_status')
            ->get();

        return response()->json([
            'total'      => $total,
            'by_country' => $byCountry,
            'by_status'  => $byStatus,
            'top_viewed' => $topViewed,
        ]);
    }

    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $question = ContentQuestion::findOrFail($id);
        $validated = $request->validate([
            'article_status' => 'required|in:new,planned,writing,published,skipped',
            'article_notes'  => 'nullable|string|max:2000',
        ]);
        $question->update($validated);
        return response()->json($question);
    }

    public function scrape(string $sourceSlug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $sourceSlug)->firstOrFail();
        $language = $request->input('language', 'fr');
        ScrapeForumQuestionsJob::dispatch($source->id, $language);
        return response()->json(['message' => "Forum Q&A scraping started ({$language})"]);
    }
}
