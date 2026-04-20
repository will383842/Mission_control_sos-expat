<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSeoJob;
use App\Jobs\PublishContentJob;
use App\Models\LandingCtaLink;
use App\Models\LandingPage;
use App\Models\PublicationQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    /**
     * GET /api/landings/stats
     *
     * Breakdown of published landings by generation_source and language.
     * Used by the Landing Generator dashboard to surface backfill totals
     * that are not counted by the AI-campaign pipeline.
     */
    public function stats(): JsonResponse
    {
        $bySource = LandingPage::query()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->selectRaw('generation_source, COUNT(*) AS n')
            ->groupBy('generation_source')
            ->pluck('n', 'generation_source')
            ->toArray();

        $byLanguage = LandingPage::query()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->selectRaw('language, COUNT(*) AS n')
            ->groupBy('language')
            ->pluck('n', 'language')
            ->toArray();

        return response()->json([
            'total_published'         => array_sum($bySource),
            'ai_generated'            => (int) ($bySource['ai_generated'] ?? 0),
            'deterministic_backfill'  => (int) ($bySource['deterministic_backfill'] ?? 0),
            'manual'                  => (int) ($bySource['manual'] ?? 0),
            'by_language'             => $byLanguage,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'            => 'nullable|string|in:draft,review,scheduled,published,generating,failed,archived',
            'language'          => 'nullable|string|max:5',
            'country'           => 'nullable|string|max:100',
            'country_code'      => 'nullable|string|max:5',
            'page_type'         => 'nullable|string|max:50',
            'audience_type'     => 'nullable|string|in:clients,lawyers,helpers,matching',
            'generation_source' => 'nullable|string|in:manual,ai_generated',
            'template_id'       => 'nullable|string|max:100',
            'search'            => 'nullable|string|max:200',
            'sort_by'           => 'nullable|string|in:created_at,updated_at,published_at,seo_score,title',
            'sort_dir'          => 'nullable|string|in:asc,desc',
            'per_page'          => 'nullable|integer|min:1|max:100',
        ]);

        $query = LandingPage::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('country_code')) {
            $query->where('country_code', strtoupper($request->input('country_code')));
        }
        if ($request->filled('page_type')) {
            $query->where('page_type', $request->input('page_type'));
        }
        // Landing Generator filters
        if ($request->filled('audience_type')) {
            $query->where('audience_type', $request->input('audience_type'));
        }
        if ($request->filled('generation_source')) {
            $query->where('generation_source', $request->input('generation_source'));
        }
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->input('template_id'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('keyword_primary', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $query->with(['ctaLinks', 'seoAnalysis', 'creator:id,name']);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(LandingPage $landing): JsonResponse
    {
        $landing->load([
            'ctaLinks',
            'seoAnalysis',
            'translations:id,title,language,status,slug,parent_id',
            'generationLogs',
            'creator:id,name',
        ]);

        return response()->json($landing);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:300',
            'language'         => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'country'          => 'nullable|string|max:100',
            'page_type'        => 'nullable|string|max:50',
            'content_html'     => 'nullable|string',
            'excerpt'          => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'keyword_primary'  => 'nullable|string|max:100',
            'tone'             => 'nullable|string|in:professional,casual,expert,friendly',
            'sections'         => 'nullable|array',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';

        $landing = LandingPage::create($validated);

        return response()->json($landing->load('creator:id,name'), 201);
    }

    public function update(Request $request, LandingPage $landing): JsonResponse
    {
        if (!in_array($landing->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Only draft or review landing pages can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'title'            => 'nullable|string|max:300',
            'content_html'     => 'nullable|string',
            'excerpt'          => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'keyword_primary'  => 'nullable|string|max:100',
            'sections'         => 'nullable|array',
            'status'           => 'nullable|string|in:draft,review',
        ]);

        $contentChanged = isset($validated['content_html']) && $validated['content_html'] !== $landing->content_html;

        $landing->update($validated);

        if ($contentChanged) {
            AnalyzeSeoJob::dispatch(LandingPage::class, $landing->id);
        }

        return response()->json($landing->fresh()->load(['ctaLinks', 'seoAnalysis', 'creator:id,name']));
    }

    public function destroy(LandingPage $landing): JsonResponse
    {
        $landing->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, LandingPage $landing): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_id'  => 'required|integer|exists:publishing_endpoints,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $queueItem = PublicationQueueItem::create([
            'publishable_type' => LandingPage::class,
            'publishable_id'   => $landing->id,
            'endpoint_id'      => $validated['endpoint_id'],
            'status'           => 'pending',
            'priority'         => $request->input('priority', 'default'),
            'scheduled_at'     => $validated['scheduled_at'] ?? null,
            'max_attempts'     => 5,
        ]);

        if (empty($validated['scheduled_at'])) {
            PublishContentJob::dispatch($queueItem->id);
        }

        return response()->json([
            'message'    => 'Landing page queued for publishing',
            'queue_item' => $queueItem->load('endpoint'),
        ], 202);
    }

    // ============================================================
    // CTA Management
    // ============================================================

    public function manageCtas(Request $request, LandingPage $landing): JsonResponse
    {
        $validated = $request->validate([
            'ctas'              => 'required|array',
            'ctas.*.id'         => 'nullable|integer|exists:landing_cta_links,id',
            'ctas.*.label'      => 'required|string|max:100',
            'ctas.*.url'        => 'required|url|max:500',
            'ctas.*.style'      => 'nullable|string|in:primary,secondary,outline,ghost',
            'ctas.*.position'   => 'nullable|string|max:50',
            'ctas.*.sort_order' => 'nullable|integer|min:0',
            'ctas.*.is_active'  => 'nullable|boolean',
        ]);

        $existingIds = [];

        foreach ($validated['ctas'] as $index => $ctaData) {
            if (!empty($ctaData['id'])) {
                // Update existing
                $cta = LandingCtaLink::where('id', $ctaData['id'])
                    ->where('landing_page_id', $landing->id)
                    ->first();

                if ($cta) {
                    $cta->update([
                        'text'       => $ctaData['label'],
                        'url'        => $ctaData['url'],
                        'style'      => $ctaData['style'] ?? 'primary',
                        'position'   => $ctaData['position'] ?? 'hero',
                        'sort_order' => $ctaData['sort_order'] ?? $index,
                    ]);
                    $existingIds[] = $cta->id;
                }
            } else {
                // Create new
                $cta = LandingCtaLink::create([
                    'landing_page_id' => $landing->id,
                    'text'            => $ctaData['label'],
                    'url'             => $ctaData['url'],
                    'style'           => $ctaData['style'] ?? 'primary',
                    'position'        => $ctaData['position'] ?? 'hero',
                    'sort_order'      => $ctaData['sort_order'] ?? $index,
                ]);
                $existingIds[] = $cta->id;
            }
        }

        // Remove CTAs not included in the payload
        LandingCtaLink::where('landing_page_id', $landing->id)
            ->whereNotIn('id', $existingIds)
            ->delete();

        return response()->json($landing->fresh()->load('ctaLinks'));
    }
}
