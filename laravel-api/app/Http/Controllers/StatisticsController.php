<?php

namespace App\Http\Controllers;

use App\Models\StatisticsDataset;
use App\Services\AI\ClaudeService;
use App\Services\PerplexitySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StatisticsController extends Controller
{
    // ── 5 themes ────────────────────────────────────────────
    private const THEMES = [
        'expatries'      => ['en' => 'expatriates', 'fr' => 'expatriés'],
        'voyageurs'      => ['en' => 'travelers',   'fr' => 'voyageurs'],
        'nomades'        => ['en' => 'digital nomads', 'fr' => 'nomades numériques'],
        'etudiants'      => ['en' => 'international students', 'fr' => 'étudiants internationaux'],
        'investisseurs'  => ['en' => 'foreign investors', 'fr' => 'investisseurs étrangers'],
    ];

    // ============================================================
    // LIST / STATS / DETAIL
    // ============================================================

    /**
     * GET /content-gen/statistics
     * List datasets with filters + pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StatisticsDataset::query();

        if ($theme = $request->query('theme')) {
            $query->where('theme', $theme);
        }
        if ($country = $request->query('country_code')) {
            $query->where('country_code', $country);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $searchLower = mb_strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(topic) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(country_name) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $query->orderByDesc('updated_at');

        return response()->json(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * GET /content-gen/statistics/stats
     * Dashboard counters.
     */
    public function stats(): JsonResponse
    {
        $total     = StatisticsDataset::count();
        $draft     = StatisticsDataset::where('status', 'draft')->count();
        $validated = StatisticsDataset::where('status', 'validated')->count();
        $published = StatisticsDataset::where('status', 'published')->count();
        $failed    = StatisticsDataset::where('status', 'failed')->count();

        $byTheme = StatisticsDataset::selectRaw('theme, count(*) as count')
            ->groupBy('theme')
            ->pluck('count', 'theme');

        $byCountry = StatisticsDataset::selectRaw('country_code, country_name, count(*) as count')
            ->whereNotNull('country_code')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $avgConfidence = (int) StatisticsDataset::avg('confidence_score');

        return response()->json([
            'total'          => $total,
            'draft'          => $draft,
            'validated'      => $validated,
            'published'      => $published,
            'failed'         => $failed,
            'by_theme'       => $byTheme,
            'by_country'     => $byCountry,
            'avg_confidence' => $avgConfidence,
            'themes'         => self::THEMES,
        ]);
    }

    /**
     * GET /content-gen/statistics/{id}
     */
    public function show(int $id): JsonResponse
    {
        $dataset = StatisticsDataset::findOrFail($id);
        return response()->json($dataset);
    }

    // ============================================================
    // CRUD
    // ============================================================

    /**
     * POST /content-gen/statistics
     * Create a dataset manually.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic'        => 'required|string|max:255',
            'theme'        => 'required|string|in:' . implode(',', array_keys(self::THEMES)),
            'country_code' => 'nullable|string|size:2',
            'country_name' => 'nullable|string|max:100',
            'title'        => 'required|string|max:500',
            'stats'        => 'nullable|array',
            'sources'      => 'nullable|array',
            'language'     => 'nullable|string|max:5',
        ]);

        $data['stats']   = $data['stats'] ?? [];
        $data['sources'] = $data['sources'] ?? [];
        $data['status']  = 'draft';

        $dataset = StatisticsDataset::create($data);

        return response()->json($dataset, 201);
    }

    /**
     * PUT /content-gen/statistics/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dataset = StatisticsDataset::findOrFail($id);

        $data = $request->validate([
            'topic'        => 'sometimes|string|max:255',
            'theme'        => 'sometimes|string|in:' . implode(',', array_keys(self::THEMES)),
            'country_code' => 'nullable|string|size:2',
            'country_name' => 'nullable|string|max:100',
            'title'        => 'sometimes|string|max:500',
            'summary'      => 'nullable|string',
            'stats'        => 'nullable|array',
            'sources'      => 'nullable|array',
            'analysis'     => 'nullable|array',
            'confidence_score' => 'nullable|integer|min:0|max:100',
            'status'       => 'sometimes|string|in:draft,validated,generating,published,failed',
        ]);

        $dataset->update($data);

        return response()->json($dataset->fresh());
    }

    /**
     * DELETE /content-gen/statistics/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        StatisticsDataset::findOrFail($id)->delete();
        return response()->json(['deleted' => true]);
    }

    // ============================================================
    // RESEARCH — Perplexity search for statistics
    // ============================================================

    /**
     * POST /content-gen/statistics/research
     * Search Perplexity for real statistics on a topic + country.
     */
    public function research(Request $request, PerplexitySearchService $perplexity): JsonResponse
    {
        $data = $request->validate([
            'theme'        => 'required|string|in:' . implode(',', array_keys(self::THEMES)),
            'country_code' => 'nullable|string|size:2',
            'country_name' => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:5',
        ]);

        if (!$perplexity->isConfigured()) {
            return response()->json(['error' => 'Perplexity API not configured'], 503);
        }

        $themeLabel = self::THEMES[$data['theme']]['en'] ?? $data['theme'];
        $country = $data['country_name'] ?? 'worldwide';
        $lang = $data['language'] ?? 'en';

        // Build a research-oriented prompt (not contact-oriented)
        $query = "Find the latest verified statistics about {$themeLabel} in {$country}. "
            . "Include: total numbers, growth trends, demographics, top nationalities, economic impact. "
            . "For EACH statistic, provide: the exact number, the year of the data, and the source (organization name + URL). "
            . "Focus on data from: UN, OECD, World Bank, Eurostat, national statistics offices, IMF. "
            . "Return at least 8-10 distinct statistics with their sources.";

        // Use the raw Perplexity API with a statistics-focused system prompt
        $systemPrompt = "You are a statistics research assistant. Your role is to find VERIFIED statistical data.\n\n"
            . "For EACH statistic found, use EXACTLY this format (one block per stat, separated by a blank line):\n\n"
            . "STAT: [exact number or percentage]\n"
            . "LABEL: [what this statistic measures]\n"
            . "YEAR: [year of the data]\n"
            . "SOURCE_NAME: [organization name]\n"
            . "SOURCE_URL: [URL where the data can be verified]\n"
            . "CONTEXT: [one sentence of context]\n\n"
            . "RULES:\n"
            . "- Only include statistics you can cite with a real source\n"
            . "- Never invent numbers — if unsure, skip\n"
            . "- Include the most recent data available\n"
            . "- Cover different aspects: population, economics, demographics, trends";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.perplexity.api_key'),
                'Content-Type'  => 'application/json',
            ])->timeout(90)->post('https://api.perplexity.ai/chat/completions', [
                'model'    => config('services.perplexity.model', 'sonar'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $query],
                ],
                'max_tokens'       => 4000,
                'temperature'      => 0.3,
                'return_citations' => true,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'Perplexity API error: HTTP ' . $response->status()], 502);
            }

            $result = $response->json();
            $text = $result['choices'][0]['message']['content'] ?? '';
            $citations = $result['citations'] ?? [];

            // Parse structured stats from Perplexity response
            $parsedStats = $this->parsePerplexityStats($text);
            $parsedSources = $this->extractSources($parsedStats, $citations);

            // Auto-create or update dataset
            $dataset = StatisticsDataset::updateOrCreate(
                [
                    'topic'        => $data['theme'] . '_' . ($data['country_code'] ?? 'global'),
                    'country_code' => $data['country_code'] ?? null,
                    'language'     => $lang,
                ],
                [
                    'theme'              => $data['theme'],
                    'country_name'       => $data['country_name'] ?? null,
                    'title'              => "Statistics: " . ucfirst($themeLabel) . " in " . $country,
                    'stats'              => $parsedStats,
                    'sources'            => $parsedSources,
                    'source_count'       => count($parsedSources),
                    'status'             => 'draft',
                    'last_researched_at' => now(),
                ]
            );

            return response()->json([
                'dataset'    => $dataset,
                'raw_text'   => $text,
                'citations'  => $citations,
                'stats_found' => count($parsedStats),
            ]);

        } catch (\Throwable $e) {
            Log::error('Statistics research failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /content-gen/statistics/research-batch
     * Research statistics for multiple countries at once.
     */
    public function researchBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme'     => 'required|string|in:' . implode(',', array_keys(self::THEMES)),
            'countries' => 'required|array|min:1|max:20',
            'countries.*.code' => 'required|string|size:2',
            'countries.*.name' => 'required|string|max:100',
        ]);

        $queued = 0;
        foreach ($data['countries'] as $country) {
            // Check if already researched recently (< 7 days)
            $existing = StatisticsDataset::where('theme', $data['theme'])
                ->where('country_code', $country['code'])
                ->where('last_researched_at', '>', now()->subDays(7))
                ->exists();

            if ($existing) continue;

            // Dispatch job (or mark for sequential processing)
            \App\Jobs\ResearchStatisticsJob::dispatch(
                $data['theme'],
                $country['code'],
                $country['name']
            );
            $queued++;
        }

        return response()->json([
            'queued'  => $queued,
            'skipped' => count($data['countries']) - $queued,
            'message' => "{$queued} recherches lancees",
        ]);
    }

    // ============================================================
    // VALIDATE — Claude cross-source analysis
    // ============================================================

    /**
     * POST /content-gen/statistics/{id}/validate
     * Use Claude to cross-reference and validate stats.
     */
    public function validateDataset(int $id, ClaudeService $claude): JsonResponse
    {
        $dataset = StatisticsDataset::findOrFail($id);

        if (!$claude->isConfigured()) {
            return response()->json(['error' => 'Claude API not configured'], 503);
        }

        $statsJson = json_encode($dataset->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $systemPrompt = "You are a data analyst. Analyze the following statistics dataset and provide:\n"
            . "1. A confidence score (0-100) based on source reliability and data consistency\n"
            . "2. Any inconsistencies between sources\n"
            . "3. A brief analysis summary highlighting key insights\n"
            . "4. Recommendations for which stats are most reliable\n\n"
            . "Return your response as JSON with keys: confidence_score (int), inconsistencies (array of strings), "
            . "summary (string), reliable_stats (array of indices), key_insights (array of strings)";

        $userPrompt = "Topic: {$dataset->title}\nCountry: {$dataset->country_name}\nTheme: {$dataset->theme}\n\n"
            . "Statistics data:\n{$statsJson}";

        $result = $claude->complete($systemPrompt, $userPrompt, [
            'model'       => 'claude-sonnet-4-6',
            'max_tokens'  => 2000,
            'temperature' => 0.3,
        ]);

        if (!$result['success']) {
            return response()->json(['error' => 'Claude validation failed: ' . ($result['error'] ?? 'unknown')], 502);
        }

        // Parse Claude's JSON response (robust: handles markdown code blocks, malformed JSON)
        $content = trim($result['content']);
        $analysis = null;

        // Try extracting from markdown code block first
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) {
            $analysis = @json_decode(trim($m[1]), true);
        }

        // Fallback: try the full content as JSON
        if (!is_array($analysis)) {
            $analysis = @json_decode($content, true);
        }

        // Fallback: try extracting first JSON object (non-greedy)
        if (!is_array($analysis) && preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $jsonMatch)) {
            $analysis = @json_decode($jsonMatch[0], true);
        }

        // Final fallback: text-only response
        if (!is_array($analysis)) {
            Log::warning('Statistics validate: Claude returned non-JSON', ['content_preview' => substr($content, 0, 200)]);
            $analysis = ['summary' => substr($content, 0, 500), 'confidence_score' => 50, 'parse_error' => true];
        }

        // Ensure minimum structure
        $analysis = array_merge([
            'confidence_score' => 50,
            'inconsistencies'  => [],
            'summary'          => '',
            'reliable_stats'   => [],
            'key_insights'     => [],
        ], $analysis);

        $dataset->update([
            'analysis'         => $analysis,
            'confidence_score' => $analysis['confidence_score'] ?? 50,
            'summary'          => $analysis['summary'] ?? null,
            'status'           => ($analysis['confidence_score'] ?? 0) >= 60 ? 'validated' : 'draft',
        ]);

        return response()->json([
            'dataset'  => $dataset->fresh(),
            'analysis' => $analysis,
        ]);
    }

    // ============================================================
    // GENERATE — Create article from validated dataset
    // ============================================================

    /**
     * POST /content-gen/statistics/{id}/generate
     * Generate an article from a validated statistics dataset.
     */
    public function generate(int $id): JsonResponse
    {
        $dataset = StatisticsDataset::findOrFail($id);

        if (!in_array($dataset->status, ['validated', 'draft', 'failed'])) {
            return response()->json(['error' => 'Dataset must be validated, draft, or failed to generate'], 422);
        }

        $dataset->update(['status' => 'generating']);

        $statsFormatted = collect($dataset->stats)->map(function ($stat, $i) {
            return "- {$stat['label']}: {$stat['value']} ({$stat['year'] ?? 'N/A'}) — Source: {$stat['source_name'] ?? 'N/A'}";
        })->implode("\n");

        $sourcesFormatted = collect($dataset->sources)->map(function ($src) {
            return "- {$src['name']}: {$src['url']}";
        })->implode("\n");

        $topic = "Statistical analysis: {$dataset->title}\n\n"
            . "Key statistics:\n{$statsFormatted}\n\n"
            . "Sources:\n{$sourcesFormatted}\n\n"
            . ($dataset->summary ? "Analysis summary: {$dataset->summary}\n\n" : '')
            . "Write a comprehensive, data-driven article using these verified statistics. "
            . "Include data visualizations descriptions, trend analysis, and actionable insights. "
            . "Cite every statistic with its source. "
            . "Target audience: people interested in {$dataset->theme} topics"
            . ($dataset->country_name ? " in {$dataset->country_name}" : ' worldwide') . ".";

        try {
            $params = [
                'topic'                => $topic,
                'language'             => $dataset->language,
                'country'              => $dataset->country_name,
                'content_type'         => 'statistics',
                'tone'                 => 'professional',
                'length'               => 'long',
                'generate_faq'         => true,
                'faq_count'            => 6,
                'research_sources'     => false, // stats already researched via Perplexity
                'image_source'         => 'unsplash',
                'auto_internal_links'  => true,
                'auto_affiliate_links' => false,
                'keywords'             => [$dataset->theme, $dataset->country_name ?? 'global'],
            ];

            // Use the existing article generation service
            $genController = app(\App\Http\Controllers\GeneratedArticleController::class);
            $fakeRequest = Request::create('/content-gen/articles', 'POST', $params);
            // Forward the authenticated user so GeneratedArticleController::store() can access $request->user()->id
            $currentUser = request()->user();
            $fakeRequest->setUserResolver(fn () => $currentUser);
            $response = $genController->store($fakeRequest);
            $articleData = json_decode($response->getContent(), true);

            $dataset->update([
                'status'               => 'published',
                'generated_article_id' => $articleData['id'] ?? null,
            ]);

            return response()->json([
                'dataset' => $dataset->fresh(),
                'article' => $articleData,
            ]);

        } catch (\Throwable $e) {
            Log::error('Statistics article generation failed', [
                'dataset_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            $dataset->update(['status' => 'failed']);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /content-gen/statistics/generate-batch
     * Generate articles from multiple validated datasets.
     */
    public function generateBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dataset_ids' => 'required|array|min:1|max:20',
            'dataset_ids.*' => 'integer|exists:statistics_datasets,id',
        ]);

        $queued = 0;
        foreach ($data['dataset_ids'] as $id) {
            $dataset = StatisticsDataset::find($id);
            if ($dataset && in_array($dataset->status, ['validated', 'draft', 'failed'])) {
                $dataset->update(['status' => 'generating']);
                \App\Jobs\GenerateStatisticsArticleJob::dispatch($id, request()->user()->id ?? 0);
                $queued++;
            }
        }

        return response()->json(['queued' => $queued]);
    }

    /**
     * GET /content-gen/statistics/themes
     * List available themes with counts.
     */
    public function themes(): JsonResponse
    {
        $counts = StatisticsDataset::selectRaw('theme, count(*) as total, '
            . "sum(case when status = 'published' then 1 else 0 end) as published, "
            . "sum(case when status = 'validated' then 1 else 0 end) as validated, "
            . "sum(case when status = 'draft' then 1 else 0 end) as draft")
            ->groupBy('theme')
            ->get()
            ->keyBy('theme');

        $themes = [];
        foreach (self::THEMES as $key => $labels) {
            $row = $counts->get($key);
            $themes[] = [
                'key'       => $key,
                'label_en'  => $labels['en'],
                'label_fr'  => $labels['fr'],
                'total'     => $row->total ?? 0,
                'published' => $row->published ?? 0,
                'validated' => $row->validated ?? 0,
                'draft'     => $row->draft ?? 0,
            ];
        }

        return response()->json($themes);
    }

    /**
     * GET /content-gen/statistics/coverage
     * Coverage matrix: which countries have been researched per theme.
     */
    public function coverage(): JsonResponse
    {
        $data = StatisticsDataset::selectRaw('country_code, country_name, theme, status, confidence_score')
            ->whereNotNull('country_code')
            ->get()
            ->groupBy('country_code')
            ->map(function ($items, $code) {
                $first = $items->first();
                return [
                    'country_code' => $code,
                    'country_name' => $first->country_name,
                    'themes'       => $items->pluck('status', 'theme')->toArray(),
                    'avg_confidence' => (int) $items->avg('confidence_score'),
                ];
            })
            ->values();

        return response()->json($data);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function parsePerplexityStats(string $text): array
    {
        $stats = [];
        $blocks = preg_split('/\n\s*\n/', $text);

        foreach ($blocks as $block) {
            if (strlen($block) > 5000) continue;

            $stat = [];
            if (preg_match('/STAT:\s*(.+)/i', $block, $m))        $stat['value'] = mb_substr(trim($m[1]), 0, 500);
            if (preg_match('/LABEL:\s*(.+)/i', $block, $m))       $stat['label'] = mb_substr(trim($m[1]), 0, 500);
            if (preg_match('/YEAR:\s*(\d{4})/i', $block, $m))     $stat['year'] = $m[1];
            if (preg_match('/SOURCE_NAME:\s*(.+)/i', $block, $m)) $stat['source_name'] = mb_substr(trim($m[1]), 0, 300);
            if (preg_match('/SOURCE_URL:\s*(.+)/i', $block, $m)) {
                $url = trim($m[1]);
                $stat['source_url'] = filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
            }
            if (preg_match('/CONTEXT:\s*(.+)/i', $block, $m))     $stat['context'] = mb_substr(trim($m[1]), 0, 500);

            if (!empty($stat['value']) && !empty($stat['label'])) {
                $stats[] = $stat;
            }
        }

        if (empty($stats)) {
            Log::warning('parsePerplexityStats: no stats parsed', [
                'text_length' => strlen($text),
                'preview'     => substr($text, 0, 300),
            ]);
        }

        return $stats;
    }

    private function extractSources(array $stats, array $citations): array
    {
        $sources = [];
        $seen = [];

        // From parsed stats
        foreach ($stats as $stat) {
            $name = $stat['source_name'] ?? null;
            $url = $stat['source_url'] ?? null;
            if ($name && !in_array($name, $seen)) {
                $sources[] = ['name' => $name, 'url' => $url, 'accessed_at' => now()->toDateString()];
                $seen[] = $name;
            }
        }

        // From Perplexity citations
        foreach ($citations as $url) {
            $domain = parse_url($url, PHP_URL_HOST) ?? $url;
            if (!in_array($domain, $seen)) {
                $sources[] = ['name' => $domain, 'url' => $url, 'accessed_at' => now()->toDateString()];
                $seen[] = $domain;
            }
        }

        return $sources;
    }
}
