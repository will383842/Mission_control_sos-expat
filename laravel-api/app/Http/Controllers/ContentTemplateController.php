<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateArticleJob;
use App\Models\ContentTemplate;
use App\Models\ContentTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentTemplateController extends Controller
{
    /** GET /content-gen/templates */
    public function index(Request $request): JsonResponse
    {
        $query = ContentTemplate::withCount(['items', 'items as pending_count' => fn ($q) => $q->where('status', 'pending'), 'items as generated_count' => fn ($q) => $q->where('status', 'published')]);

        if ($request->filled('preset_type')) {
            $query->where('preset_type', $request->input('preset_type'));
        }

        $templates = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($templates);
    }

    /** POST /content-gen/templates */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'preset_type' => 'required|string|in:mots-cles,longues-traines,rec-avocats,rec-expats,visa-pays,cout-vie,custom',
            'content_type' => 'required|string|in:article,guide,tutorial,news,qa,comparative',
            'title_template' => 'required|string|max:500',
            'variables' => 'nullable|array',
            'expansion_mode' => 'required|string|in:manual,all_countries,selected_countries,custom_list',
            'expansion_values' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'tone' => 'nullable|string|max:30',
            'article_length' => 'nullable|string|in:short,medium,long,extra_long',
            'generation_instructions' => 'nullable|string',
            'generate_faq' => 'nullable|boolean',
            'faq_count' => 'nullable|integer|min:0|max:15',
            'research_sources' => 'nullable|boolean',
            'auto_internal_links' => 'nullable|boolean',
            'auto_affiliate_links' => 'nullable|boolean',
            'auto_translate' => 'nullable|boolean',
            'image_source' => 'nullable|string|in:unsplash,dalle',
        ]);

        $data['created_by'] = $request->user()?->id;
        $template = ContentTemplate::create($data);

        // Auto-expand items if not manual
        if ($template->expansion_mode !== 'manual') {
            $this->expandTemplate($template);
        }

        return response()->json($template->load('items'), 201);
    }

    /** GET /content-gen/templates/{id} */
    public function show(int $id): JsonResponse
    {
        $template = ContentTemplate::with(['items' => fn ($q) => $q->orderByRaw("CASE status WHEN 'generating' THEN 0 WHEN 'pending' THEN 1 WHEN 'optimizing' THEN 2 WHEN 'published' THEN 3 WHEN 'failed' THEN 4 WHEN 'skipped' THEN 5 END")])->findOrFail($id);

        return response()->json($template);
    }

    /** PUT /content-gen/templates/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = ContentTemplate::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:1000',
            'title_template' => 'sometimes|string|max:500',
            'expansion_mode' => 'sometimes|string|in:manual,all_countries,selected_countries,custom_list',
            'expansion_values' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'tone' => 'nullable|string|max:30',
            'article_length' => 'nullable|string|in:short,medium,long,extra_long',
            'generation_instructions' => 'nullable|string',
            'generate_faq' => 'nullable|boolean',
            'faq_count' => 'nullable|integer|min:0|max:15',
            'research_sources' => 'nullable|boolean',
            'auto_translate' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($data);

        return response()->json($template->fresh('items'));
    }

    /** DELETE /content-gen/templates/{id} */
    public function destroy(int $id): JsonResponse
    {
        $template = ContentTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template supprime.']);
    }

    /** POST /content-gen/templates/{id}/expand — Re-expand items from template variables */
    public function expand(int $id): JsonResponse
    {
        $template = ContentTemplate::findOrFail($id);
        $count = $this->expandTemplate($template);

        return response()->json([
            'message' => "{$count} items generes.",
            'total_items' => $template->fresh()->total_items,
        ]);
    }

    /** POST /content-gen/templates/{id}/add-items — Add manual keywords/items */
    public function addItems(Request $request, int $id): JsonResponse
    {
        $template = ContentTemplate::findOrFail($id);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string|max:500',
        ]);

        $added = 0;
        foreach ($data['items'] as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) continue;

            // Check duplicate
            $exists = ContentTemplateItem::where('template_id', $template->id)
                ->where('expanded_title', $keyword)
                ->exists();

            if (!$exists) {
                ContentTemplateItem::create([
                    'template_id' => $template->id,
                    'expanded_title' => $keyword,
                    'variable_values' => [],
                    'status' => 'pending',
                ]);
                $added++;
            }
        }

        $template->increment('total_items', $added);

        return response()->json([
            'message' => "{$added} items ajoutes.",
            'total_items' => $template->fresh()->total_items,
        ]);
    }

    /** POST /content-gen/templates/{id}/generate — Generate articles for pending items */
    public function generate(Request $request, int $id): JsonResponse
    {
        $template = ContentTemplate::findOrFail($id);

        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $limit = $data['limit'] ?? 10;

        $query = $template->items()->where('status', 'pending');

        if (!empty($data['item_ids'])) {
            $query->whereIn('id', $data['item_ids']);
        }

        $items = $query->limit($limit)->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Aucun item en attente.'], 422);
        }

        $dispatched = 0;
        foreach ($items as $item) {
            $item->update(['status' => 'generating']);

            $country = $item->variable_values['pays_code']
                ?? $item->variable_values['country_code']
                ?? null;

            $params = [
                'topic' => $item->expanded_title,
                'language' => $template->language ?? 'fr',
                'content_type' => $template->content_type ?? 'article',
                'tone' => $template->tone ?? 'professional',
                'length' => $template->article_length ?? 'medium',
                'generate_faq' => $template->generate_faq,
                'faq_count' => $template->faq_count ?? 6,
                'research_sources' => $template->research_sources,
                'auto_internal_links' => $template->auto_internal_links,
                'auto_affiliate_links' => $template->auto_affiliate_links,
                'image_source' => $template->image_source ?? 'unsplash',
                'instructions' => $template->generation_instructions,
                'source_slug' => "template_{$template->id}",
            ];

            if ($country) {
                $params['country'] = $country;
            }

            if (!empty($item->variable_values)) {
                $params['keywords'] = array_values($item->variable_values);
            }

            GenerateArticleJob::dispatch($params)->onQueue('content');
            $dispatched++;
        }

        return response()->json([
            'message' => "{$dispatched} articles en generation.",
            'dispatched' => $dispatched,
        ]);
    }

    /** POST /content-gen/templates/items/{itemId}/skip */
    public function skipItem(int $itemId): JsonResponse
    {
        $item = ContentTemplateItem::findOrFail($itemId);
        $item->update(['status' => 'skipped']);

        return response()->json(['message' => 'Item ignore.']);
    }

    /** POST /content-gen/templates/items/{itemId}/reset */
    public function resetItem(int $itemId): JsonResponse
    {
        $item = ContentTemplateItem::findOrFail($itemId);
        $item->update(['status' => 'pending', 'error_message' => null]);

        return response()->json(['message' => 'Item reinitialise.']);
    }

    // ─── PRIVATE ──────────────────────────────────────────────

    private function expandTemplate(ContentTemplate $template): int
    {
        $expansions = $template->expand();
        $added = 0;

        foreach ($expansions as $exp) {
            $exists = ContentTemplateItem::where('template_id', $template->id)
                ->where('expanded_title', $exp['expanded_title'])
                ->exists();

            if (!$exists) {
                ContentTemplateItem::create([
                    'template_id' => $template->id,
                    'expanded_title' => $exp['expanded_title'],
                    'variable_values' => $exp['variable_values'],
                    'status' => 'pending',
                ]);
                $added++;
            }
        }

        $template->update(['total_items' => $template->items()->count()]);

        return $added;
    }
}
