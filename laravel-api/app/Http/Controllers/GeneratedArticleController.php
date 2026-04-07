<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSeoJob;
use App\Jobs\GenerateArticleJob;
use App\Jobs\PublishContentJob;
use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleVersion;
use App\Models\PublicationQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GeneratedArticleController extends Controller
{
    // ============================================================
    // CRUD
    // ============================================================

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'       => 'nullable|string|in:draft,review,published,generating,failed',
            'language'     => 'nullable|string|max:5',
            'country'      => 'nullable|string|max:100',
            'content_type' => 'nullable|string|in:article,guide,news,tutorial',
            'search'       => 'nullable|string|max:200',
            'sort_by'      => 'nullable|string|in:created_at,updated_at,published_at,seo_score,quality_score,word_count,title',
            'sort_dir'     => 'nullable|string|in:asc,desc',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $query = GeneratedArticle::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('content_type')) {
            $query->where('content_type', $request->input('content_type'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('keywords_primary', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $query->with(['faqs', 'images', 'seoAnalysis', 'creator:id,name']);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(GeneratedArticle $article): JsonResponse
    {
        $article->load([
            'faqs',
            'sources',
            'images',
            'versions',
            'seoAnalysis',
            'internalLinksOut',
            'externalLinks',
            'affiliateLinks',
            'translations:id,title,language,status,slug,parent_article_id',
            'generationLogs',
            'creator:id,name',
        ]);

        return response()->json($article);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic'                => 'required|string|max:500',
            'language'             => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'country'              => 'nullable|string|max:100',
            'content_type'         => 'nullable|string|in:article,guide,news,tutorial,statistics',
            'keywords'             => 'nullable|array',
            'keywords.*'           => 'string|max:100',
            'instructions'         => 'nullable|string|max:2000',
            'tone'                 => 'nullable|string|in:professional,casual,expert,friendly',
            'length'               => 'nullable|string|in:short,medium,long',
            'generate_faq'         => 'nullable|boolean',
            'faq_count'            => 'nullable|integer|min:1|max:20',
            'research_sources'     => 'nullable|boolean',
            'image_source'         => 'nullable|string|in:unsplash,dalle,none',
            'auto_internal_links'  => 'nullable|boolean',
            'auto_affiliate_links' => 'nullable|boolean',
            'translation_languages'   => 'nullable|array',
            'translation_languages.*' => 'string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'preset_id'            => 'nullable|integer|exists:generation_presets,id',
        ]);

        // Create a placeholder article immediately so the frontend can navigate to it
        $article = GeneratedArticle::create([
            'uuid'               => (string) Str::uuid(),
            'title'              => $validated['topic'],
            'slug'               => Str::slug($validated['topic']),
            'language'           => $validated['language'],
            'country'            => $validated['country'] ?? null,
            'content_type'       => $validated['content_type'] ?? 'article',
            'keywords_primary'   => $validated['keywords'][0] ?? null,
            'keywords_secondary' => $validated['keywords'] ?? [],
            'status'             => 'generating',
            'created_by'         => $request->user()->id,
        ]);

        // Dispatch async generation with the pre-created article ID
        GenerateArticleJob::dispatch(array_merge($validated, [
            'article_id' => $article->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json($article, 202);
    }

    public function update(Request $request, GeneratedArticle $article): JsonResponse
    {
        if (!in_array($article->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Only draft or review articles can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'title'              => 'nullable|string|max:300',
            'content_html'       => 'nullable|string',
            'excerpt'            => 'nullable|string|max:500',
            'meta_title'         => 'nullable|string|max:70',
            'meta_description'   => 'nullable|string|max:160',
            'keywords_primary'   => 'nullable|string|max:100',
            'keywords_secondary' => 'nullable|array',
            'status'             => 'nullable|string|in:draft,review',
        ]);

        // If content changed, create a new version and re-analyze SEO
        $contentChanged = isset($validated['content_html']) && $validated['content_html'] !== $article->content_html;

        if ($contentChanged) {
            $latestVersion = $article->versions()->max('version_number') ?? 0;

            GeneratedArticleVersion::create([
                'article_id'       => $article->id,
                'version_number'   => $latestVersion + 1,
                'content_html'     => $article->content_html,
                'meta_title'       => $article->meta_title,
                'meta_description' => $article->meta_description,
                'changes_summary'  => 'Manual edit',
                'created_by'       => $request->user()->id,
            ]);
        }

        $article->update($validated);

        // Recount words if content changed
        if ($contentChanged) {
            $plainText = strip_tags($article->content_html);
            $article->update([
                'word_count' => str_word_count($plainText),
                'reading_time_minutes' => max(1, (int) round(str_word_count($plainText) / 250)),
            ]);

            AnalyzeSeoJob::dispatch(GeneratedArticle::class, $article->id);
        }

        return response()->json($article->fresh()->load(['faqs', 'seoAnalysis', 'creator:id,name']));
    }

    public function destroy(GeneratedArticle $article): JsonResponse
    {
        $article->delete(); // soft delete

        return response()->json(null, 204);
    }

    // ============================================================
    // Publishing
    // ============================================================

    public function publish(Request $request, GeneratedArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_id'  => 'required|integer|exists:publishing_endpoints,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $queueItem = PublicationQueueItem::create([
            'publishable_type' => GeneratedArticle::class,
            'publishable_id'   => $article->id,
            'endpoint_id'      => $validated['endpoint_id'],
            'status'           => 'pending',
            'priority'         => $request->input('priority', 'default'),
            'scheduled_at'     => $validated['scheduled_at'] ?? null,
            'max_attempts'     => 5,
        ]);

        // Dispatch immediately if no scheduled time
        if (empty($validated['scheduled_at'])) {
            PublishContentJob::dispatch($queueItem->id);
        }

        return response()->json([
            'message'    => 'Article queued for publishing',
            'queue_item' => $queueItem->load('endpoint'),
        ], 202);
    }

    public function unpublish(GeneratedArticle $article): JsonResponse
    {
        $article->update([
            'status'       => 'draft',
            'published_at' => null,
        ]);

        return response()->json($article->fresh());
    }

    // ============================================================
    // Duplication
    // ============================================================

    public function duplicate(GeneratedArticle $article): JsonResponse
    {
        $newArticle = $article->replicate([
            'uuid', 'slug', 'status', 'published_at', 'published_url',
            'canonical_url', 'scheduled_at',
        ]);

        $newArticle->uuid = (string) Str::uuid();
        $newArticle->slug = $article->slug . '-copy-' . Str::random(4);
        $newArticle->status = 'draft';
        $newArticle->save();

        // Duplicate FAQs
        foreach ($article->faqs as $faq) {
            $newFaq = $faq->replicate();
            $newFaq->article_id = $newArticle->id;
            $newFaq->save();
        }

        return response()->json(
            $newArticle->load(['faqs', 'creator:id,name']),
            201
        );
    }

    // ============================================================
    // Bulk Operations
    // ============================================================

    public function bulkPublish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_ids'   => 'required|array|min:1|max:50',
            'article_ids.*' => 'integer|exists:generated_articles,id',
            'endpoint_id'   => 'required|integer|exists:publishing_endpoints,id',
        ]);

        $items = [];
        foreach ($validated['article_ids'] as $articleId) {
            $queueItem = PublicationQueueItem::create([
                'publishable_type' => GeneratedArticle::class,
                'publishable_id'   => $articleId,
                'endpoint_id'      => $validated['endpoint_id'],
                'status'           => 'pending',
                'priority'         => 'default',
                'max_attempts'     => 5,
            ]);

            PublishContentJob::dispatch($queueItem->id);
            $items[] = $queueItem;
        }

        return response()->json([
            'message' => count($items) . ' articles queued for publishing',
            'count'   => count($items),
        ], 202);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_ids'   => 'required|array|min:1|max:100',
            'article_ids.*' => 'integer|exists:generated_articles,id',
        ]);

        $count = GeneratedArticle::whereIn('id', $validated['article_ids'])->count();
        GeneratedArticle::whereIn('id', $validated['article_ids'])->delete(); // soft delete

        return response()->json([
            'message' => "{$count} articles deleted",
            'count'   => $count,
        ]);
    }

    // ============================================================
    // Versions
    // ============================================================

    public function versions(GeneratedArticle $article): JsonResponse
    {
        $versions = $article->versions()
            ->orderByDesc('version_number')
            ->get();

        return response()->json($versions);
    }

    public function restoreVersion(GeneratedArticle $article, GeneratedArticleVersion $version): JsonResponse
    {
        // Ensure version belongs to article
        if ($version->article_id !== $article->id) {
            return response()->json(['message' => 'Version does not belong to this article'], 422);
        }

        // Save current state as a new version before restoring
        $latestVersion = $article->versions()->max('version_number') ?? 0;

        GeneratedArticleVersion::create([
            'article_id'       => $article->id,
            'version_number'   => $latestVersion + 1,
            'content_html'     => $article->content_html,
            'meta_title'       => $article->meta_title,
            'meta_description' => $article->meta_description,
            'changes_summary'  => "Auto-saved before restoring version #{$version->version_number}",
            'created_by'       => request()->user()->id,
        ]);

        // Restore from version
        $article->update([
            'content_html'     => $version->content_html,
            'meta_title'       => $version->meta_title,
            'meta_description' => $version->meta_description,
        ]);

        // Recount words
        $plainText = strip_tags($article->content_html);
        $article->update([
            'word_count' => str_word_count($plainText),
            'reading_time_minutes' => max(1, (int) round(str_word_count($plainText) / 250)),
        ]);

        // Re-analyze SEO
        AnalyzeSeoJob::dispatch(GeneratedArticle::class, $article->id);

        return response()->json($article->fresh()->load(['faqs', 'versions', 'seoAnalysis', 'creator:id,name']));
    }
}
