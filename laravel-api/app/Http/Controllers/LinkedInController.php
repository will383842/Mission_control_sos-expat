<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLinkedInPostJob;
use App\Models\GeneratedArticle;
use App\Models\LinkedInPost;
use App\Models\QaEntry;
use App\Models\Sondage;
use App\Services\AI\ClaudeService;
use App\Services\Content\AudienceContextService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LinkedInController extends Controller
{
    // ── Stats ──────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        // Grouped OR to avoid incorrect operator precedence
        $postsThisWeek = LinkedInPost::where(function ($q) use ($weekStart, $weekEnd) {
            $q->whereBetween('scheduled_at', [$weekStart, $weekEnd])
              ->orWhereBetween('created_at', [$weekStart, $weekEnd]);
        })->count();

        $scheduled     = LinkedInPost::where('status', 'scheduled')->count();
        $published     = LinkedInPost::where('status', 'published')->count();
        $generating    = LinkedInPost::where('status', 'generating')->count();
        $totalReach    = LinkedInPost::where('status', 'published')->sum('reach');
        $avgEngagement = LinkedInPost::where('status', 'published')->avg('engagement_rate') ?? 0;

        $topDay = LinkedInPost::where('status', 'published')
            ->selectRaw('day_type, AVG(engagement_rate) as avg_eng')
            ->groupBy('day_type')
            ->orderByDesc('avg_eng')
            ->value('day_type');

        $usedArticleIds = $this->usedSourceIds('article');
        $usedFaqIds     = $this->usedSourceIds('faq');
        $usedSondageIds = $this->usedSourceIds('sondage');

        $availableArticles = GeneratedArticle::published()->whereNotIn('id', $usedArticleIds)->count();
        $availableFaqs     = QaEntry::published()->whereNotIn('id', $usedFaqIds)->count();
        $availableSondages = Sondage::whereIn('status', ['active', 'closed'])->whereNotIn('id', $usedSondageIds)->count();

        // Upcoming scheduled posts (next 7 days)
        $upcoming = LinkedInPost::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
            ->orderBy('scheduled_at')
            ->get(['id', 'day_type', 'lang', 'account', 'hook', 'scheduled_at', 'source_type'])
            ->map(fn($p) => [
                'id'           => $p->id,
                'day_type'     => $p->day_type,
                'lang'         => $p->lang,
                'account'      => $p->account,
                'hook_preview' => mb_substr($p->hook, 0, 80),
                'scheduled_at' => $p->scheduled_at?->toISOString(),
                'source_type'  => $p->source_type,
            ]);

        return response()->json([
            'posts_this_week'      => $postsThisWeek,
            'posts_scheduled'      => $scheduled,
            'posts_published'      => $published,
            'posts_generating'     => $generating,
            'total_reach'          => (int) $totalReach,
            'avg_engagement_rate'  => round($avgEngagement, 2),
            'top_performing_day'   => $topDay ?? 'monday',
            'available_articles'   => $availableArticles,
            'available_faqs'       => $availableFaqs,
            'available_sondages'   => $availableSondages,
            'upcoming_posts'       => $upcoming,
            'linkedin_connected'   => false, // TODO: check OAuth token
        ]);
    }

    // ── Queue (paginated) ──────────────────────────────────────────────

    public function queue(Request $request): JsonResponse
    {
        $query = LinkedInPost::latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = min((int) ($request->per_page ?? 25), 50);

        return response()->json($query->paginate($perPage));
    }

    // ── Auto-select best unpublished source ───────────────────────────

    private const DB_SOURCE_TYPES = ['article', 'faq', 'sondage'];

    public function autoSelect(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:article,faq,sondage,hot_take,myth,poll,serie,reactive,milestone,partner_story,counter_intuition,tip,news,case_study',
            'lang'        => 'required|in:fr,en,both',
        ]);

        $lang       = $request->lang === 'both' ? 'fr' : $request->lang;
        $sourceType = $request->source_type;

        if (!in_array($sourceType, self::DB_SOURCE_TYPES, true)) {
            return response()->json([
                'found'           => true,
                'source_type'     => $sourceType,
                'source_id'       => null,
                'title'           => 'Génération libre — aucune source requise',
                'available_count' => 999,
            ]);
        }

        if ($sourceType === 'article') {
            $usedIds = $this->usedSourceIds('article');
            $source  = GeneratedArticle::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->orderByDesc('editorial_score')
                ->first(['id', 'title', 'language', 'country', 'editorial_score', 'quality_score']);

            return response()->json([
                'found'           => $source !== null,
                'source_type'     => 'article',
                'source_id'       => $source?->id,
                'title'           => $source?->title,
                'country'         => $source?->country,
                'editorial_score' => $source?->editorial_score,
                'quality_score'   => $source?->quality_score,
                'available_count' => GeneratedArticle::published()->where('language', $lang)->whereNotIn('id', $usedIds)->count(),
            ]);
        }

        if ($sourceType === 'faq') {
            $usedIds = $this->usedSourceIds('faq');
            $source  = QaEntry::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->orderByDesc('seo_score')
                ->first(['id', 'question', 'language', 'country', 'seo_score']);

            return response()->json([
                'found'           => $source !== null,
                'source_type'     => 'faq',
                'source_id'       => $source?->id,
                'title'           => $source?->question,
                'country'         => $source?->country,
                'seo_score'       => $source?->seo_score,
                'available_count' => QaEntry::published()->where('language', $lang)->whereNotIn('id', $usedIds)->count(),
            ]);
        }

        if ($sourceType === 'sondage') {
            $usedIds = $this->usedSourceIds('sondage');
            $source  = Sondage::whereIn('status', ['active', 'closed'])
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->latest()
                ->first(['id', 'title', 'status', 'language']);

            return response()->json([
                'found'           => $source !== null,
                'source_type'     => 'sondage',
                'source_id'       => $source?->id,
                'title'           => $source?->title,
                'available_count' => Sondage::whereIn('status', ['active', 'closed'])->where('language', $lang)->whereNotIn('id', $usedIds)->count(),
            ]);
        }

        return response()->json(['found' => false, 'source_type' => $sourceType, 'available_count' => 0]);
    }

    // ── Generate (async) — fixes lang='both' ──────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:article,faq,sondage,hot_take,myth,poll,serie,reactive,milestone,partner_story,counter_intuition,tip,news,case_study',
            'source_id'   => 'nullable|integer',
            'day_type'    => 'required|in:monday,tuesday,wednesday,thursday,friday',
            'lang'        => 'required|in:fr,en,both',
            'account'     => 'required|in:page,personal,both',
        ]);

        // lang='both' → create TWO separate posts (FR + EN)
        if ($request->lang === 'both') {
            $posts = [];
            foreach (['fr', 'en'] as $langCode) {
                $posts[] = $this->createAndDispatch($request->all(), $langCode);
            }
            return response()->json($posts, 202);
        }

        // Single language
        $post = $this->createAndDispatch($request->all(), $request->lang);
        return response()->json($post, 202);
    }

    // ── Generate reply variants for a comment ─────────────────────────

    public function generateReplies(Request $request, LinkedInPost $post): JsonResponse
    {
        $request->validate(['comment_text' => 'required|string|min:5|max:1000']);

        $lang    = $post->lang === 'both' ? 'fr' : $post->lang;
        $claude  = app(ClaudeService::class);
        $kb      = app(KnowledgeBaseService::class);

        $kbContext  = $kb->getLightPrompt('linkedin', null, $lang);
        $langLabel  = $lang === 'en' ? 'English' : 'français';
        $postPreview = mb_substr($post->hook . ' ' . $post->body, 0, 400);

        $systemPrompt = <<<SYSTEM
{$kbContext}

Tu es un expert en community management LinkedIn pour SOS-Expat.com.
Tu génères des réponses aux commentaires LinkedIn : humaines, authentiques, sans être commercial.
SYSTEM;

        $userPrompt = <<<USER
Mon post LinkedIn :
"{$postPreview}"

Commentaire reçu :
"{$request->comment_text}"

Génère exactement 10 variantes de réponse en {$langLabel}.
Règles :
- Chaque réponse est unique (ton différent : empathique / informatif / question de retour / humoristique / inspirant / reconnaissant / challengers...)
- 50-150 caractères chacune
- Humain, jamais robotique
- Jamais de hashtag dans une réponse à un commentaire
- Finir certaines variantes par une question pour relancer l'engagement

Retourne un JSON : { "variants": ["réponse1", "réponse2", ..., "réponse10"] }
USER;

        try {
            $result = $claude->complete($systemPrompt, $userPrompt, [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 800,
                'json_mode'  => true,
            ]);

            if (!($result['success'] ?? false)) {
                return response()->json(['error' => $result['error']], 500);
            }

            $data     = json_decode($result['content'] ?? '', true) ?? [];
            $variants = $data['variants'] ?? [];

            // Save to post for Telegram notification when API is connected
            $post->update(['reply_variants' => $variants]);

            return response()->json([
                'post_id'  => $post->id,
                'variants' => $variants,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Next free scheduling slot ──────────────────────────────────────

    public function nextSlot(Request $request): JsonResponse
    {
        $request->validate(['lang' => 'nullable|in:fr,en']);
        $lang = $request->lang ?? 'fr';

        return response()->json([
            'next_slot' => $this->nextFreeSlot($lang)->toISOString(),
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function update(Request $request, LinkedInPost $post): JsonResponse
    {
        $post->update($request->only(['hook', 'body', 'hashtags', 'first_comment', 'lang', 'account', 'day_type', 'status']));
        return response()->json($post);
    }

    // ── Schedule ───────────────────────────────────────────────────────

    public function schedule(Request $request, LinkedInPost $post): JsonResponse
    {
        $request->validate(['scheduled_at' => 'required|date|after:now']);
        $post->update([
            'status'         => 'scheduled',
            'scheduled_at'   => $request->scheduled_at,
            'auto_scheduled' => false, // manually scheduled
        ]);
        return response()->json($post);
    }

    // ── Publish (manual / OAuth future) ───────────────────────────────

    public function publish(LinkedInPost $post): JsonResponse
    {
        // TODO: LinkedIn API v2 — publishViaLinkedInApi($post) when OAuth is configured
        $post->update([
            'status'                => 'published',
            'published_at'          => now(),
            'first_comment_status'  => $post->first_comment ? 'pending' : null,
        ]);

        return response()->json($post);
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function destroy(LinkedInPost $post): JsonResponse
    {
        $post->delete();
        return response()->json(['message' => 'Post supprimé']);
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function createAndDispatch(array $params, string $langCode): LinkedInPost
    {
        $sourceTitle = $this->resolveSourceTitle($params['source_type'], $params['source_id'] ?? null);

        $post = LinkedInPost::create([
            'source_type'  => $params['source_type'],
            'source_id'    => $params['source_id'] ?? null,
            'source_title' => $sourceTitle,
            'day_type'     => $params['day_type'],
            'lang'         => $langCode,
            'account'      => $params['account'],
            'hook'         => '',
            'body'         => '',
            'hashtags'     => [],
            'status'       => 'generating',
            'phase'        => (int) ($params['phase'] ?? 1),
        ]);

        GenerateLinkedInPostJob::dispatch($post->id);
        return $post;
    }

    private function resolveSourceTitle(string $type, ?int $id): ?string
    {
        if (!$id) return null;
        try {
            return match ($type) {
                'article' => GeneratedArticle::find($id)?->title,
                'faq'     => QaEntry::find($id)?->question,
                'sondage' => Sondage::find($id)?->title,
                default   => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** IDs already used for a given source_type (dedup check) */
    private function usedSourceIds(string $sourceType): \Illuminate\Support\Collection
    {
        return LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', $sourceType)
            ->whereNotNull('source_id')
            ->pluck('source_id');
    }

    /**
     * Find the next free posting slot: Mon-Fri at 07:30 or 12:15.
     * Skips slots already taken by another post (same lang, within 30 min).
     */
    public function nextFreeSlot(string $lang = 'fr'): \Illuminate\Support\Carbon
    {
        $slots = ['07:30', '12:15'];
        $now   = now();

        for ($dayOffset = 0; $dayOffset <= 21; $dayOffset++) {
            $candidate = $now->copy()->addDays($dayOffset);
            if ($candidate->isWeekend()) continue;

            foreach ($slots as $time) {
                [$h, $m] = explode(':', $time);
                $slot = $candidate->copy()->setHour((int)$h)->setMinute((int)$m)->setSecond(0);

                if ($slot->isPast()) continue;

                // Check no post of the same language is already scheduled within ±30 min
                $conflict = LinkedInPost::where('status', 'scheduled')
                    ->where('lang', $lang)
                    ->whereBetween('scheduled_at', [
                        $slot->copy()->subMinutes(30),
                        $slot->copy()->addMinutes(30),
                    ])
                    ->exists();

                if (!$conflict) return $slot;
            }
        }

        // Absolute fallback: next weekday at 07:30
        return now()->addDay()->setHour(7)->setMinute(30)->setSecond(0);
    }
}
