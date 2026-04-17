<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSocialPostJob;
use App\Jobs\PostSocialFirstCommentJob;
use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Models\Sondage;
use App\Models\SocialPost;
use App\Services\AI\OpenAiService;
use App\Services\Content\KnowledgeBaseService;
use App\Services\Social\SocialDriverManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Multi-platform publishing controller. Port of LinkedInController with a
 * leading {platform} route parameter that selects the driver.
 *
 * Every query on SocialPost is scoped by platform (forPlatform scope).
 * Publishing delegates to the driver resolved via SocialDriverManager.
 */
class SocialPublishingController extends Controller
{
    private const SOURCE_TYPES = 'article,faq,sondage,hot_take,myth,poll,serie,reactive,milestone,partner_story,counter_intuition,tip,news,case_study';
    private const DB_SOURCE_TYPES = ['article', 'faq', 'sondage'];

    public function __construct(private SocialDriverManager $manager) {}

    // ── Stats ──────────────────────────────────────────────────────────

    public function stats(string $platform): JsonResponse
    {
        $base = SocialPost::forPlatform($platform);

        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        $postsThisWeek = (clone $base)->where(function ($q) use ($weekStart, $weekEnd) {
            $q->whereBetween('scheduled_at', [$weekStart, $weekEnd])
              ->orWhereBetween('created_at', [$weekStart, $weekEnd]);
        })->count();

        $scheduled     = (clone $base)->whereIn('status', ['scheduled', 'pending_confirm'])->count();
        $published     = (clone $base)->where('status', 'published')->count();
        $generating    = (clone $base)->where('status', 'generating')->count();
        $totalReach    = (clone $base)->where('status', 'published')->sum('reach');
        $avgEngagement = (clone $base)->where('status', 'published')->avg('engagement_rate') ?? 0;

        $topDay = (clone $base)->where('status', 'published')
            ->selectRaw('day_type, AVG(engagement_rate) as avg_eng')
            ->groupBy('day_type')
            ->orderByDesc('avg_eng')
            ->value('day_type');

        $usedArticleIds = $this->usedSourceIds($platform, 'article');
        $usedFaqIds     = $this->usedSourceIds($platform, 'faq');
        $usedSondageIds = $this->usedSourceIds($platform, 'sondage');

        $availableArticles = GeneratedArticle::published()->whereNotIn('id', $usedArticleIds)->count();
        $availableFaqs     = QaEntry::published()->whereNotIn('id', $usedFaqIds)->count();
        $availableSondages = Sondage::whereIn('status', ['active', 'closed'])->whereNotIn('id', $usedSondageIds)->count();

        $upcoming = (clone $base)
            ->whereIn('status', ['scheduled', 'draft'])
            ->where(function ($q) {
                $q->whereBetween('scheduled_at', [now(), now()->addDays(30)])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'draft')->whereNull('scheduled_at');
                  });
            })
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->get(['id', 'day_type', 'lang', 'account_type', 'hook', 'scheduled_at', 'source_type', 'status', 'featured_image_url'])
            ->map(fn($p) => [
                'id'           => $p->id,
                'day_type'     => $p->day_type,
                'lang'         => $p->lang,
                'account'      => $p->account_type,
                'hook_preview' => mb_substr($p->hook, 0, 80),
                'scheduled_at' => $p->scheduled_at?->toISOString(),
                'source_type'  => $p->source_type,
                'status'       => $p->status,
                'has_image'    => !empty($p->featured_image_url),
            ]);

        $driver = $this->manager->driver($platform);

        return response()->json([
            'platform'             => $platform,
            'posts_this_week'      => $postsThisWeek,
            'posts_scheduled'      => $scheduled,
            'posts_published'      => $published,
            'posts_generating'     => $generating,
            'total_reach'          => (int) $totalReach,
            'avg_engagement_rate'  => round((float) $avgEngagement, 2),
            'top_performing_day'   => $topDay ?? 'monday',
            'available_articles'   => $availableArticles,
            'available_faqs'       => $availableFaqs,
            'available_sondages'   => $availableSondages,
            'upcoming_posts'       => $upcoming,
            'connected'            => $driver->isConfigured(),
            'token_status'         => $driver->getTokenStatus(),
        ]);
    }

    // ── Single post ────────────────────────────────────────────────────

    public function show(string $platform, int $post): JsonResponse
    {
        return response()->json($this->resolvePost($platform, $post));
    }

    // ── Queue (paginated) ──────────────────────────────────────────────

    public function queue(Request $request, string $platform): JsonResponse
    {
        $query = SocialPost::forPlatform($platform)->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = min((int) ($request->per_page ?? 25), 50);

        return response()->json($query->paginate($perPage));
    }

    // ── Auto-select best unpublished source ───────────────────────────

    public function autoSelect(Request $request, string $platform): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:' . self::SOURCE_TYPES,
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
            $usedIds = $this->usedSourceIds($platform, 'article');
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
            $usedIds = $this->usedSourceIds($platform, 'faq');
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
            $usedIds = $this->usedSourceIds($platform, 'sondage');
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

    public function generate(Request $request, string $platform): JsonResponse
    {
        $driver = $this->manager->driver($platform);

        $request->validate([
            'source_type'  => 'required|in:' . self::SOURCE_TYPES,
            'source_id'    => 'nullable|integer',
            'day_type'     => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'lang'         => 'required|in:fr,en,both',
            'account_type' => 'nullable|string|in:' . implode(',', $driver->supportedAccountTypes()),
        ]);

        // lang='both' → create TWO separate posts (FR + EN)
        if ($request->lang === 'both') {
            $posts = [];
            foreach (['fr', 'en'] as $langCode) {
                $posts[] = $this->createAndDispatch($platform, $request->all(), $langCode);
            }
            return response()->json($posts, 202);
        }

        return response()->json($this->createAndDispatch($platform, $request->all(), $request->lang), 202);
    }

    // ── Generate reply variants for a comment ─────────────────────────

    public function generateReplies(Request $request, string $platform, int $post): JsonResponse
    {
        $postModel = $this->resolvePost($platform, $post);

        $request->validate(['comment_text' => 'required|string|min:5|max:1000']);

        $lang        = $postModel->lang === 'both' ? 'fr' : $postModel->lang;
        $openai      = app(OpenAiService::class);
        $kb          = app(KnowledgeBaseService::class);
        $kbContext   = $kb->getLightPrompt($platform, null, $lang);
        $langLabel   = $lang === 'en' ? 'English' : 'français';
        $postPreview = mb_substr($postModel->hook . ' ' . $postModel->body, 0, 400);

        $systemPrompt = <<<SYSTEM
{$kbContext}

Tu es un expert en community management {$platform} pour SOS-Expat.com.
Tu génères des réponses aux commentaires : humaines, authentiques, sans être commercial.
SYSTEM;

        $userPrompt = <<<USER
Mon post {$platform} :
"{$postPreview}"

Commentaire reçu :
"{$request->comment_text}"

Génère exactement 10 variantes de réponse en {$langLabel}.
Règles :
- Chaque réponse est unique (ton différent : empathique / informatif / question de retour / humoristique / inspirant / reconnaissant / challenger...)
- 50-150 caractères chacune
- Humain, jamais robotique
- Jamais de hashtag dans une réponse à un commentaire
- Finir certaines variantes par une question pour relancer l'engagement

Retourne un JSON : { "variants": ["réponse1", "réponse2", ..., "réponse10"] }
USER;

        try {
            $result = $openai->complete($systemPrompt, $userPrompt, [
                'model'      => 'gpt-4o-mini',
                'max_tokens' => 800,
                'json_mode'  => true,
            ]);

            if (!($result['success'] ?? false)) {
                return response()->json(['error' => $result['error']], 500);
            }

            $data     = json_decode($result['content'] ?? '', true) ?? [];
            $variants = $data['variants'] ?? [];

            $postModel->update(['reply_variants' => $variants]);

            return response()->json([
                'post_id'  => $postModel->id,
                'platform' => $platform,
                'variants' => $variants,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Next free scheduling slot ──────────────────────────────────────

    public function nextSlot(Request $request, string $platform): JsonResponse
    {
        $request->validate(['lang' => 'nullable|in:fr,en']);
        $lang = $request->lang ?? 'fr';

        return response()->json([
            'platform'  => $platform,
            'next_slot' => $this->nextFreeSlot($platform, $lang)->toISOString(),
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function update(Request $request, string $platform, int $post): JsonResponse
    {
        $postModel = $this->resolvePost($platform, $post);
        $postModel->update($request->only([
            'hook', 'body', 'hashtags', 'first_comment',
            'lang', 'account_type', 'day_type', 'status',
        ]));
        return response()->json($postModel);
    }

    // ── Schedule ───────────────────────────────────────────────────────

    public function schedule(Request $request, string $platform, int $post): JsonResponse
    {
        $postModel = $this->resolvePost($platform, $post);
        $request->validate(['scheduled_at' => 'required|date|after:now']);
        $postModel->update([
            'status'         => 'scheduled',
            'scheduled_at'   => $request->scheduled_at,
            'auto_scheduled' => false,
        ]);
        return response()->json($postModel);
    }

    // ── Publish (manual — calls platform API) ─────────────────────────

    public function publish(string $platform, int $post): JsonResponse
    {
        $postModel = $this->resolvePost($platform, $post);
        $driver    = $this->manager->driver($platform);
        $accountType = $postModel->account_type ?: $driver->supportedAccountTypes()[0];

        if ($driver->requiresImage() && !$postModel->featured_image_url) {
            return response()->json([
                'error' => ucfirst($platform) . ' requires a featured_image_url for publishing.',
            ], 422);
        }

        if (!$driver->isConfigured($accountType)) {
            return response()->json([
                'error' => ucfirst($platform) . " token not configured or expired (account_type={$accountType}). Reconnect OAuth.",
            ], 503);
        }

        try {
            $platformPostId = $driver->publish($postModel, $accountType);

            if (!$platformPostId) {
                return response()->json([
                    'error' => ucfirst($platform) . ' API returned no post id. Check server logs.',
                ], 500);
            }

            $postModel->update([
                'platform_post_id'     => $platformPostId,
                'status'               => 'published',
                'published_at'         => now(),
                'first_comment_status' => ($postModel->first_comment && $driver->supportsFirstComment()) ? 'pending' : ($postModel->first_comment ? 'skipped' : null),
                'error_message'        => null,
            ]);

            // Schedule first comment 3 min after publication (if supported)
            if ($postModel->first_comment && $driver->supportsFirstComment()) {
                $delay = (int) config('social.auto_publish.first_comment_delay_seconds', 180);
                PostSocialFirstCommentJob::dispatch($postModel->id, $platform)
                    ->delay(now()->addSeconds($delay));
            }

            return response()->json($postModel->fresh());

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SocialPublishingController::publish failed', [
                'platform' => $platform,
                'post_id'  => $postModel->id,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function destroy(string $platform, int $post): JsonResponse
    {
        $postModel = $this->resolvePost($platform, $post);
        $postModel->delete();
        return response()->json(['message' => 'Post supprimé']);
    }

    // ── Private helpers ────────────────────────────────────────────────

    /** Load a SocialPost and guarantee it belongs to the URL platform. */
    private function resolvePost(string $platform, int $id): SocialPost
    {
        return SocialPost::forPlatform($platform)->findOrFail($id);
    }

    private function createAndDispatch(string $platform, array $params, string $langCode): SocialPost
    {
        $driver       = $this->manager->driver($platform);
        $sourceTitle  = $this->resolveSourceTitle($params['source_type'], $params['source_id'] ?? null);
        $accountType  = $params['account_type'] ?? $driver->supportedAccountTypes()[0];
        $scheduledAt  = $this->nextFreeSlot($platform, $langCode);

        $post = SocialPost::create([
            'platform'       => $platform,
            'source_type'    => $params['source_type'],
            'source_id'      => $params['source_id'] ?? null,
            'source_title'   => $sourceTitle,
            'day_type'       => $params['day_type'],
            'lang'           => $langCode,
            'account_type'   => $accountType,
            'hook'           => '',
            'body'           => '',
            'hashtags'       => [],
            'status'         => 'generating',
            'phase'          => (int) ($params['phase'] ?? 1),
            'scheduled_at'   => $scheduledAt,
            'auto_scheduled' => true,
        ]);

        GenerateSocialPostJob::dispatch($post->id, $platform);
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

    /** IDs already used for a given (platform, source_type) pair (dedup check). */
    private function usedSourceIds(string $platform, string $sourceType): \Illuminate\Support\Collection
    {
        return SocialPost::forPlatform($platform)
            ->whereIn('status', ['draft', 'scheduled', 'pending_confirm', 'published', 'generating'])
            ->where('source_type', $sourceType)
            ->whereNotNull('source_id')
            ->pluck('source_id');
    }

    /**
     * Find the next free posting slot for a given platform: Mon/Wed/Fri/Sat by default,
     * one post per calendar day. Saturday → 09:00 UTC; other days → 07:30 UTC.
     * Configurable via config('social.calendar').
     */
    public function nextFreeSlot(string $platform, string $lang = 'fr'): \Illuminate\Support\Carbon
    {
        $now = now();

        $calendarDays = config('social.calendar.default_days', ['monday', 'wednesday', 'friday', 'saturday']);
        $dayMap = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
        $publishDays = array_values(array_intersect_key($dayMap, array_flip($calendarDays)));
        if (!$publishDays) $publishDays = [1, 3, 5, 6];

        $defaultHour  = (int) config('social.calendar.default_hour_utc', 7);
        $saturdayHour = (int) config('social.calendar.saturday_hour_utc', 9);

        for ($dayOffset = 0; $dayOffset <= 28; $dayOffset++) {
            $candidate = $now->copy()->addDays($dayOffset);
            $dow = (int) $candidate->format('N');

            if (!in_array($dow, $publishDays, true)) continue;

            $hour   = ($dow === 6) ? $saturdayHour : $defaultHour;
            $minute = ($dow === 6) ? 0 : 30;
            $slot   = $candidate->copy()->setHour($hour)->setMinute($minute)->setSecond(0);

            if ($slot->isPast()) continue;

            $conflict = SocialPost::forPlatform($platform)
                ->whereIn('status', ['scheduled', 'generating'])
                ->whereDate('scheduled_at', $slot->toDateString())
                ->exists();

            if (!$conflict) return $slot;
        }

        // Absolute fallback: next matching day at default hour
        $next = now()->addDay();
        while (!in_array((int) $next->format('N'), $publishDays, true)) {
            $next->addDay();
        }
        return $next->setHour($defaultHour)->setMinute(30)->setSecond(0);
    }
}
