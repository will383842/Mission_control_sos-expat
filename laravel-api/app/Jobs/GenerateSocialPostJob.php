<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Models\SocialPost;
use App\Models\Sondage;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\SocialPromptBuilder;
use App\Services\Social\TelegramAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a social post (hook + body + hashtags + first_comment + image)
 * for the platform stored on $post->platform.
 *
 * Pipeline (per platform):
 *   1. Load source (article/faq/sondage from DB, or empty for free types)
 *   2. SocialPromptBuilder → platform-specific system + user prompts (best practices 2026)
 *   3. AI fallback chain : GPT-4o → Claude Sonnet → fail (no template — quality matters)
 *   4. Quality loop : up to 3 attempts, scored 0-100, critique fed back to AI
 *   5. Image search Unsplash (mandatory for Instagram via driver->requiresImage())
 *   6. Update SocialPost → status='scheduled' (or 'draft' if QA below threshold)
 *
 * Per-platform queue routing : social_linkedin / social_facebook / ...
 */
class GenerateSocialPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;
    public array $backoff = [30, 120];

    /** Free-generation source types (no DB row required) */
    private const FREE_TYPES = [
        'hot_take', 'myth', 'poll', 'serie', 'reactive', 'milestone',
        'partner_story', 'counter_intuition', 'tip', 'news', 'case_study',
    ];

    public function __construct(public int $postId, public string $platform)
    {
        $this->onQueue(config("social.drivers.{$platform}.queue", 'default'));
    }

    public function handle(
        OpenAiService $openai,
        UnsplashService $unsplash,
        SocialDriverManager $manager,
        SocialPromptBuilder $builder,
    ): void {
        $post = SocialPost::find($this->postId);
        if (!$post) {
            Log::warning('GenerateSocialPostJob: post not found', ['id' => $this->postId]);
            return;
        }

        // Cross-check platform consistency
        if ($post->platform !== $this->platform) {
            Log::warning('GenerateSocialPostJob: platform mismatch — using post->platform', [
                'job_platform' => $this->platform,
                'post_platform' => $post->platform,
            ]);
        }
        $platform = $post->platform;
        $driver = $manager->driver($platform);

        try {
            $lang = $post->lang === 'both' ? 'fr' : $post->lang;

            // ── 1. Fetch source content ──────────────────────────────────
            $source = $this->fetchSource($platform, $post->source_type, $post->source_id, $lang);

            // ── 2. Alert if DB source exhausted, fall back to free type ──
            if (empty($source['content']) && !in_array($post->source_type, self::FREE_TYPES, true)) {
                $this->notifyFailure(
                    $post,
                    "⚠️ Source épuisée — plus de <code>{$post->source_type}</code> disponible en {$lang}.\n"
                    . "Post #{$post->id} basculé en hot_take pour préserver la production."
                );
                $post->source_type = 'hot_take';
            }

            // ── 3. Build platform-specific prompt ────────────────────────
            $prompts = $builder->build($driver, $post, $source, $lang);

            // ── 4. Generate with quality loop (up to 3 attempts) ─────────
            $data = $this->generateWithQualityLoop($openai, $prompts, $post, $driver, $lang);

            $freshStatus = $post->fresh()->status ?? '';
            if ($freshStatus === 'draft' || $freshStatus === 'failed') return;

            if (!$data) {
                Log::error('GenerateSocialPostJob: no usable data after quality loop', ['post_id' => $post->id]);
                return;
            }

            // ── 5. Sanitize hashtags + body ──────────────────────────────
            $hashtags = $this->sanitizeHashtags($data['hashtags'] ?? [], $source['hashtag_seeds'] ?? []);
            $body = $this->sanitizeBody($data['body'] ?? '', $source);
            $hook = trim($data['hook'] ?? '');
            $firstComment = !empty($data['first_comment']) ? trim($data['first_comment']) : null;

            // ── 6. Featured image (mandatory for Instagram) ──────────────
            $featuredImage = $source['image_url'] ?? null;
            $imageAttribution = null;
            if (!$featuredImage && $unsplash->isConfigured()) {
                $imgQuery = $this->buildUnsplashQuery($post->source_type, $source['keywords'] ?? '', $source['country'] ?? '', $post->id);
                $imgResult = $unsplash->searchUnique($imgQuery, 1, 'landscape');
                if (($imgResult['success'] ?? false) && !empty($imgResult['images'])) {
                    $img = $imgResult['images'][0];
                    $featuredImage = $img['url'] ?? null;
                    $imageAttribution = $img['attribution'] ?? null;
                }
            }

            // Instagram: image is non-negotiable. If we still don't have one → fail clearly
            if ($driver->requiresImage() && !$featuredImage) {
                $this->failPost($post, "Aucune image disponible (Unsplash vide ?) — Instagram requiert obligatoirement une image.");
                return;
            }

            // Append Unsplash attribution to body (API requirement)
            if ($imageAttribution && !str_contains($body, 'Unsplash')) {
                $body .= "\n\n📸 " . $imageAttribution;
            }

            // ── 7. Save post → directly scheduled ───────────────────────
            $post->update([
                'hook'                  => $hook ?: $this->defaultHook($post->day_type, $lang),
                'body'                  => $body,
                'hashtags'              => $hashtags,
                'first_comment'         => $firstComment,
                'featured_image_url'    => $featuredImage,
                'first_comment_status'  => $firstComment && $driver->supportsFirstComment() ? 'pending' : null,
                'status'                => 'scheduled',
                'auto_scheduled'        => true,
                'error_message'         => null,
                'source_id'             => !in_array($post->source_type, self::FREE_TYPES, true)
                                            ? ($source['source_db_id'] ?? $post->source_id)
                                            : $post->source_id,
                'source_title'          => $source['title'] ?? $post->source_title,
            ]);

            Log::info('GenerateSocialPostJob: done', [
                'post_id'  => $post->id,
                'platform' => $platform,
                'day'      => $post->day_type,
                'source'   => $post->source_type,
                'lang'     => $lang,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateSocialPostJob: failed', [
                'post_id' => $post->id,
                'platform' => $platform ?? 'unknown',
                'error'   => $e->getMessage(),
            ]);
            $post->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // AI generation : GPT-4o → Claude Sonnet → fail
    // ══════════════════════════════════════════════════════════════════

    private function generateWithFallback(
        OpenAiService $openai,
        array $prompts,
        SocialPost $post,
    ): array {
        $system = $prompts['system'];
        $user   = $prompts['user'];

        // Level 1: GPT-4o
        if ($openai->isConfigured()) {
            $r = $openai->complete($system, $user, [
                'model'       => 'gpt-4o',
                'max_tokens'  => 1800,
                'temperature' => 0.78,
                'json_mode'   => true,
            ]);

            if ($r['success'] ?? false) {
                $data = json_decode($r['content'] ?? '', true);
                if ($this->isValidPayload($data)) {
                    Log::info('GenerateSocialPostJob: generated via GPT-4o', ['post_id' => $post->id]);
                    return $data;
                }
            }
            Log::warning('GenerateSocialPostJob: GPT-4o failed', [
                'post_id' => $post->id,
                'error'   => mb_substr($r['error'] ?? '', 0, 200),
            ]);
        }

        // Level 2: Claude fallback
        try {
            $claude = app(ClaudeService::class);
            if ($claude->isConfigured()) {
                $r2 = $claude->complete($system, $user, [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1800,
                    'json_mode'  => true,
                ]);
                if ($r2['success'] ?? false) {
                    $data2 = json_decode($r2['content'] ?? '', true);
                    if ($this->isValidPayload($data2)) {
                        Log::info('GenerateSocialPostJob: generated via Claude (fallback)', ['post_id' => $post->id]);
                        $this->notifyFailure($post, 'GPT-4o quota — Claude utilisé en fallback');
                        return $data2;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateSocialPostJob: Claude fallback exception', [
                'post_id' => $post->id, 'error' => $e->getMessage(),
            ]);
        }

        // Level 3: both unavailable → mark failed (do NOT publish template content)
        Log::error('GenerateSocialPostJob: both AI services failed', ['post_id' => $post->id]);
        $post->update([
            'status'        => 'failed',
            'error_message' => 'GPT-4o + Anthropic indisponibles. Rechargez les crédits.',
        ]);
        $this->notifyFailure(
            $post,
            "🚨 <b>Post #{$post->id} ANNULÉ — crédits API épuisés</b>\n\n"
            . "Rechargez OpenAI + Anthropic puis :\n"
            . "<code>php artisan social:fill-calendar --platform={$post->platform}</code>"
        );

        return [];
    }

    private function isValidPayload($data): bool
    {
        return is_array($data)
            && isset($data['hook'], $data['body'])
            && is_string($data['hook']) && mb_strlen(trim($data['hook'])) > 0
            && is_string($data['body']) && mb_strlen(trim($data['body'])) > 50;
    }

    // ══════════════════════════════════════════════════════════════════
    // Quality loop : 3 attempts with critique
    // ══════════════════════════════════════════════════════════════════

    private function generateWithQualityLoop(
        OpenAiService $openai,
        array $prompts,
        SocialPost $post,
        $driver,
        string $lang,
    ): ?array {
        $best = null;
        $bestScore = 0;
        $threshold = 75;
        $maxRounds = 3;
        $currentPrompts = $prompts;

        for ($round = 1; $round <= $maxRounds; $round++) {
            $data = $this->generateWithFallback($openai, $currentPrompts, $post);

            if (empty($data)) return null; // both AIs down — failPost already called

            $score = $this->scorePost($data, $driver);
            $critique = $this->buildCritique($data, $driver);

            Log::info('GenerateSocialPostJob: QA score', [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'round'   => $round,
                'score'   => $score,
            ]);

            if ($score > $bestScore) { $bestScore = $score; $best = $data; }
            if ($score >= $threshold || $round === $maxRounds) break;

            // Append critique for next round
            $langLabel = $lang === 'en' ? 'English' : 'français';
            $currentPrompts['user'] = $prompts['user']
                . "\n\nCRITIQUE PRÉCÉDENTE (score: {$score}/100) :\n{$critique}\n\n"
                . "Régénère le post en {$langLabel} en corrigeant UNIQUEMENT ces points. "
                . "Garde le même sujet et la structure JSON.";
        }

        // Below score 50 → put in draft for manual review
        if ($bestScore < 50) {
            $post->update([
                'status'        => 'draft',
                'error_message' => "QA score {$bestScore}/100 après 3 tentatives — révision manuelle.",
            ]);
            $this->notifyFailure(
                $post,
                "🔴 Post #{$post->id} ({$post->platform}) en DRAFT — score {$bestScore}/100\n→ Review manuelle dans Mission Control"
            );
            return null;
        }

        return $best;
    }

    // ══════════════════════════════════════════════════════════════════
    // Scoring : per-platform thresholds
    // ══════════════════════════════════════════════════════════════════

    private function scorePost(array $data, $driver): int
    {
        $hook = $data['hook'] ?? '';
        $body = $data['body'] ?? '';
        $hashtags = $data['hashtags'] ?? [];
        $hookLen = mb_strlen($hook);
        $totalLen = mb_strlen($hook . "\n\n" . $body);
        $hashCount = count(is_array($hashtags) ? $hashtags : []);

        $platform = $driver->platform();
        $score = 0;

        // Hook length per platform
        $maxHook = match ($platform) {
            'linkedin'  => 140,
            'facebook'  => 80,
            'threads'   => 80,
            'instagram' => 138,
            default     => 140,
        };
        if ($hookLen > 0 && $hookLen <= $maxHook)        $score += 20;
        elseif ($hookLen > 0 && $hookLen <= $maxHook * 1.2) $score += 10;

        // Body length per platform
        [$minBody, $maxBody] = match ($platform) {
            'linkedin'  => [900, 1700],
            'facebook'  => [200, 1000],
            'threads'   => [50, $driver->maxContentLength()],
            'instagram' => [400, $driver->maxContentLength()],
            default     => [200, 1500],
        };
        if ($totalLen >= $minBody && $totalLen <= $maxBody) $score += 20;
        elseif ($totalLen >= $minBody * 0.7 && $totalLen <= $maxBody * 1.2) $score += 10;

        // Hashtag count per platform
        [$minTag, $maxTag] = match ($platform) {
            'linkedin'  => [3, 5],
            'facebook'  => [1, 2],
            'threads'   => [1, 2],
            'instagram' => [3, 5],
            default     => [3, 5],
        };
        if ($hashCount >= $minTag && $hashCount <= $maxTag) $score += 15;
        elseif ($hashCount >= 1) $score += 5;

        // Voice / tone (LinkedIn = 1st person, others more flexible)
        if ($platform === 'linkedin' && preg_match('/\bje\b|\bj\'|\bI /i', $hook . ' ' . $body)) $score += 15;
        if ($platform !== 'linkedin') $score += 15; // not strict outside LinkedIn

        // Brand pollution (LinkedIn forbids in body, others more relaxed)
        $hasBrand = preg_match('/sos.?expat/i', $body);
        if ($platform === 'linkedin' && !$hasBrand) $score += 15;
        elseif ($platform !== 'linkedin' && substr_count(strtolower($body), 'sos-expat') <= 2) $score += 15;

        // No commercial cliches
        $cliches = preg_match('/résultat \?|le secret de|cliquez ici|n\'hésitez pas|likez si|partagez si/i', $body);
        if (!$cliches) $score += 10;

        // Threads hard cap
        if ($platform === 'threads' && $totalLen > 500) $score -= 30;

        return max(0, min(100, $score));
    }

    private function buildCritique(array $data, $driver): string
    {
        $issues = [];
        $hook = $data['hook'] ?? '';
        $body = $data['body'] ?? '';
        $hashtags = $data['hashtags'] ?? [];
        $hookLen = mb_strlen($hook);
        $totalLen = mb_strlen($hook . "\n\n" . $body);
        $platform = $driver->platform();

        $maxHook = match ($platform) {
            'linkedin' => 140, 'facebook' => 80, 'threads' => 80, 'instagram' => 138, default => 140,
        };
        if ($hookLen > $maxHook) $issues[] = "HOOK TROP LONG ({$hookLen} chars, max {$maxHook}).";
        if ($hookLen === 0) $issues[] = "HOOK MANQUANT.";

        if ($platform === 'threads' && $totalLen > 500) {
            $issues[] = "DÉPASSEMENT 500 chars HARD ({$totalLen}). Threads rejettera. Coupe agressivement.";
        }
        if ($platform === 'instagram' && $totalLen > 2200) {
            $issues[] = "DÉPASSEMENT 2200 chars Instagram ({$totalLen}). Coupe ou Meta tronquera.";
        }
        if ($platform === 'linkedin' && $totalLen < 900) {
            $issues[] = "CORPS TROP COURT pour LinkedIn ({$totalLen}, min 900). Développe l'insight terrain.";
        }
        if ($platform === 'facebook' && $totalLen > 1000) {
            $issues[] = "TROP LONG pour Facebook ({$totalLen}, max ~1000). Coupe les transitions.";
        }

        $hashCount = count(is_array($hashtags) ? $hashtags : []);
        $maxTag = match ($platform) { 'linkedin' => 5, 'instagram' => 5, default => 2 };
        if ($hashCount > $maxTag) $issues[] = "TROP DE HASHTAGS ({$hashCount}, max {$maxTag} pour {$platform}).";

        if ($platform === 'linkedin' && preg_match('/sos.?expat/i', $body)) {
            $issues[] = "MARQUE INTERDITE dans body LinkedIn (sauf URL finale).";
        }

        if (preg_match('/résultat \?|le secret de|n\'hésitez pas|cliquez ici/i', $body)) {
            $issues[] = "CLICHÉS COMMERCIAUX détectés. Supprime-les.";
        }

        return empty($issues) ? '' : "Corrections obligatoires :\n• " . implode("\n• ", $issues);
    }

    // ══════════════════════════════════════════════════════════════════
    // Sanitization
    // ══════════════════════════════════════════════════════════════════

    private function sanitizeHashtags(array $raw, array $seeds): array
    {
        $clean = [];
        foreach ($raw as $tag) {
            if (!is_string($tag)) continue;
            $tag = ltrim(trim($tag), '#');
            $tag = preg_replace('/[^a-zA-Z0-9_]/u', '', $tag);
            if (strlen($tag) > 0 && strlen($tag) <= 40) $clean[] = strtolower($tag);
        }
        if (empty($clean) && !empty($seeds)) {
            $clean = array_slice(array_map(fn($s) => preg_replace('/[^a-zA-Z0-9_]/u', '', strtolower($s)), $seeds), 0, 3);
        }
        return array_values(array_unique($clean));
    }

    private function sanitizeBody(string $body, array $source): string
    {
        // Strip placeholder URL brackets the AI sometimes produces
        $body = preg_replace('/\[URL[^\]]{0,60}\]/u', $source['url'] ?? '', $body);
        $body = preg_replace('/\[https?:[^\]]{0,100}\]/u', '', $body);
        return rtrim($body);
    }

    private function defaultHook(string $dayType, string $lang): string
    {
        $hooks = [
            'fr' => "Retour terrain : ce que l'expatriation m'a appris cette semaine 👇",
            'en' => "From the field: what expat life taught me this week 👇",
        ];
        return $hooks[$lang] ?? $hooks['fr'];
    }

    // ══════════════════════════════════════════════════════════════════
    // Source loading (DB scoped per-platform for dedup)
    // ══════════════════════════════════════════════════════════════════

    private function fetchSource(string $platform, string $type, ?int $id, string $lang): array
    {
        $empty = [
            'source_db_id'  => null,
            'title'         => 'SOS-Expat.com',
            'content'       => '',
            'keywords'      => 'expatriation, expat, visa, étranger, avocat',
            'hashtag_seeds' => ['expatriation', 'expat'],
            'url'           => '',
            'image_url'     => null,
            'country'       => '',
        ];

        if (in_array($type, self::FREE_TYPES, true)) {
            // Attach a related article URL/image as supporting context
            $related = $this->bestArticle($platform, $lang);
            if ($related) {
                return array_merge($empty, [
                    'url'           => $related['url'],
                    'image_url'     => $related['image_url'],
                    'keywords'      => $related['keywords'] ?: $empty['keywords'],
                    'hashtag_seeds' => $related['hashtag_seeds'] ?: $empty['hashtag_seeds'],
                ]);
            }
            return $empty;
        }

        if (!$id) {
            return match ($type) {
                'article' => $this->bestArticle($platform, $lang) ?? $empty,
                'faq'     => $this->bestFaq($platform, $lang) ?? $empty,
                'sondage' => $this->bestSondage($platform, $lang) ?? $empty,
                default   => $empty,
            };
        }

        return match ($type) {
            'article' => $this->fetchArticle($id) ?? $empty,
            'faq'     => $this->fetchFaq($id) ?? $empty,
            'sondage' => $this->fetchSondage($id) ?? $empty,
            default   => $empty,
        };
    }

    private function bestArticle(string $platform, string $lang): ?array
    {
        $usedIds = SocialPost::forPlatform($platform)
            ->whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'article')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $a = GeneratedArticle::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('editorial_score')
            ->first();

        return $a ? $this->articleToSource($a) : null;
    }

    private function bestFaq(string $platform, string $lang): ?array
    {
        $usedIds = SocialPost::forPlatform($platform)
            ->whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'faq')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $f = QaEntry::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('seo_score')
            ->first();

        return $f ? $this->faqToSource($f) : null;
    }

    private function bestSondage(string $platform, string $lang): ?array
    {
        $usedIds = SocialPost::forPlatform($platform)
            ->whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'sondage')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $s = Sondage::whereIn('status', ['active', 'closed'])
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->latest()
            ->first();

        return $s ? $this->sondageToSource($s) : null;
    }

    private function fetchArticle(int $id): ?array
    {
        $a = GeneratedArticle::find($id);
        return $a ? $this->articleToSource($a) : null;
    }

    private function fetchFaq(int $id): ?array
    {
        $f = QaEntry::find($id);
        return $f ? $this->faqToSource($f) : null;
    }

    private function fetchSondage(int $id): ?array
    {
        $s = Sondage::with('questions')->find($id);
        return $s ? $this->sondageToSource($s) : null;
    }

    // ── Source → array converters ──────────────────────────────────────

    private function articleToSource(GeneratedArticle $a): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($a->content_html ?? '')));
        $plain = mb_substr($plain, 0, 800);
        $primary = $a->keywords_primary ?? '';
        $secondary = is_array($a->keywords_secondary)
            ? implode(', ', array_slice($a->keywords_secondary, 0, 5)) : '';
        $allKeys = trim($primary . ($secondary ? ', ' . $secondary : ''));
        $seeds = array_values(array_filter(array_map('trim', explode(',', $allKeys))));

        return [
            'source_db_id'  => $a->id,
            'title'         => $a->title ?? '',
            'content'       => $plain,
            'keywords'      => $allKeys,
            'hashtag_seeds' => array_slice($seeds, 0, 5),
            'url'           => $a->external_url ?? $a->canonical_url ?? '',
            'image_url'     => $a->featured_image_url ?? null,
            'country'       => $a->country ?? '',
        ];
    }

    private function faqToSource(QaEntry $f): array
    {
        $answer = trim(preg_replace('/\s+/', ' ', ($f->answer_short ?? '') . ' ' . strip_tags($f->answer_detailed_html ?? '')));
        $answer = mb_substr($answer, 0, 800);
        $primary = $f->keywords_primary ?? '';
        $secondary = is_array($f->keywords_secondary)
            ? implode(', ', array_slice($f->keywords_secondary, 0, 4)) : '';
        $allKeys = trim($primary . ($secondary ? ', ' . $secondary : ''));
        $seeds = array_values(array_filter(array_map('trim', explode(',', $allKeys))));

        return [
            'source_db_id'  => $f->id,
            'title'         => $f->question ?? '',
            'content'       => $answer,
            'keywords'      => $allKeys,
            'hashtag_seeds' => array_slice($seeds, 0, 4),
            'url'           => $f->external_url ?? $f->canonical_url ?? '',
            'image_url'     => null,
            'country'       => $f->country ?? '',
        ];
    }

    private function sondageToSource(Sondage $s): array
    {
        $questions = $s->questions ?? collect();
        $qText = $questions->take(5)->map(function ($q) {
            $opts = is_array($q->options) ? ' → Options: ' . implode(' / ', array_slice($q->options, 0, 4)) : '';
            return $q->text . $opts;
        })->implode("\n");
        $content = mb_substr("Sondage : {$s->title}\n\nQuestions :\n{$qText}", 0, 1200);

        return [
            'source_db_id'  => $s->id,
            'title'         => $s->title ?? 'Sondage SOS-Expat',
            'content'       => $content,
            'keywords'      => 'sondage, expatriation, étude',
            'hashtag_seeds' => ['sondage', 'expatriation'],
            'url'           => '',
            'image_url'     => null,
            'country'       => '',
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════

    private function buildUnsplashQuery(string $sourceType, string $keywords, string $country, int $postId): string
    {
        $kws = array_slice(array_filter(array_map('trim', explode(',', $keywords))), 0, 2);
        $base = $kws ? implode(' ', $kws) : 'travel expat lifestyle';
        return $country ? trim($base . ' ' . $country) : $base;
    }

    private function notifyFailure(SocialPost $post, string $message): void
    {
        try {
            $telegram = app(TelegramAlertService::class, ['bot' => $post->platform]);
            if ($telegram->isConfigured()) {
                $telegram->sendMessage($message);
            }
        } catch (\Throwable) {
            // never let notification failure break the job
        }
    }

    private function failPost(SocialPost $post, string $reason): void
    {
        $post->update(['status' => 'failed', 'error_message' => $reason]);
        $this->notifyFailure($post, "❌ Post #{$post->id} ({$post->platform}) échoué : {$reason}");
    }
}
