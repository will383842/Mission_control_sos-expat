<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use App\Models\LinkedInPostComment;
use App\Services\AI\OpenAiService;
use App\Services\Social\LinkedInApiService;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Runs every 15 minutes via scheduler.
 *
 * For each published post (last 30 days):
 *  1. Fetch comments via LinkedIn API
 *  2. Store new (unseen) comments in linkedin_post_comments
 *  3. Generate 3 contextual reply variants using GPT-4o-mini
 *  4. Send Telegram notification with:
 *       - Comment author + text
 *       - 3 reply variant buttons
 *       - "✏️ Personnaliser" button (triggers custom reply flow)
 *       - "🔇 Ignorer" button
 *
 * Reply flow (via LinkedInTelegramController::webhook):
 *  - Tap variant   → posted immediately on LinkedIn
 *  - Tap Perso     → bot asks for text, user replies, bot posts it
 *  - Tap Ignorer   → marks as seen, no reply
 */
class CheckLinkedInCommentsCommand extends Command
{
    protected $signature   = 'linkedin:check-comments';
    protected $description = 'Poll LinkedIn comments on published posts and notify via Telegram';

    public function __construct(
        private LinkedInApiService   $api,
        private TelegramAlertService $telegram,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->api->isConfigured('personal') && !$this->api->isConfigured('page')) {
            return self::SUCCESS; // No token configured yet
        }

        if (!$this->telegram->isConfigured()) {
            Log::info('linkedin:check-comments: Telegram not configured, skipping');
            return self::SUCCESS;
        }

        // Published posts from last 30 days that have a LinkedIn URN
        $posts = LinkedInPost::where('status', 'published')
            ->where('published_at', '>=', now()->subDays(30))
            ->where(function ($q) {
                $q->whereNotNull('li_post_id_personal')
                  ->orWhereNotNull('li_post_id_page');
            })
            ->get();

        $newComments = 0;

        foreach ($posts as $post) {
            $newComments += $this->checkPost($post);
        }

        if ($newComments > 0) {
            $this->info("linkedin:check-comments: {$newComments} new comment(s) notified");
        }

        return self::SUCCESS;
    }

    // ── Per-post check ─────────────────────────────────────────────────

    private function checkPost(LinkedInPost $post): int
    {
        // Determine which URN + account to use
        $postUrn     = $post->li_post_id_personal ?? $post->li_post_id_page;
        $accountType = $post->li_post_id_personal ? 'personal' : 'page';

        if (!$postUrn) return 0;

        $apiComments = $this->api->getComments($postUrn, $accountType);
        if (empty($apiComments)) return 0;

        $newCount = 0;

        foreach ($apiComments as $c) {
            $urn = $c['urn'];

            // Skip if already seen
            if (LinkedInPostComment::where('comment_urn', $urn)->exists()) continue;

            // Ignore our own first_comment (same actor as the post author)
            // Simple heuristic: if comment text starts with the first_comment text
            if ($post->first_comment && str_starts_with($c['text'], mb_substr($post->first_comment, 0, 50))) {
                // Store silently (no Telegram notification)
                LinkedInPostComment::create([
                    'linkedin_post_id'     => $post->id,
                    'comment_urn'          => $urn,
                    'author_name'          => $c['author_name'],
                    'author_urn'           => $c['author_urn'],
                    'comment_text'         => $c['text'],
                    'commented_at'         => $c['commented_at'],
                    'reply_source'         => 'manual', // our own comment, no need to reply
                    'replied_at'           => now(),
                    'reply_text'           => $c['text'],
                ]);
                continue;
            }

            // Store the comment
            $comment = LinkedInPostComment::create([
                'linkedin_post_id' => $post->id,
                'comment_urn'      => $urn,
                'author_name'      => $c['author_name'],
                'author_urn'       => $c['author_urn'],
                'comment_text'     => $c['text'],
                'commented_at'     => $c['commented_at'],
            ]);

            // Generate 3 quick reply variants
            $variants = $this->generateVariants($post, $c['text'], $c['author_name']);

            // Cache variants in Redis (10 min TTL) so TelegramController can retrieve them
            try {
                Redis::setex("li_variants_{$comment->id}", 600, json_encode($variants));
            } catch (\Throwable) {}

            // Send Telegram notification
            $msgId = $this->sendTelegramNotification($comment, $post, $postUrn, $accountType, $variants);

            // Update comment with notification timestamp + message id
            $comment->update([
                'telegram_notified_at' => now(),
                'telegram_msg_id'      => $msgId,
            ]);

            $newCount++;
        }

        return $newCount;
    }

    // ── Generate reply variants with GPT-4o-mini ───────────────────────

    /**
     * Generates 3 contextual reply variants in the SAME LANGUAGE as the comment.
     * Language is auto-detected by GPT-4o-mini — no explicit language check needed.
     * This handles FR, EN, ES, DE, AR, ZH, etc. automatically.
     */
    private function generateVariants(LinkedInPost $post, string $commentText, string $authorName): array
    {
        try {
            $openai   = app(OpenAiService::class);
            $postHook = mb_substr($post->hook ?? '', 0, 120);
            $firstName = explode(' ', trim($authorName))[0] ?? $authorName;

            $result = $openai->complete(
                "You are an expert community manager for SOS-Expat.com (connecting expats with lawyers and experts in 197 countries). You generate short, authentic replies to LinkedIn comments.",
                <<<USER
                LinkedIn post (opening line): "{$postHook}"
                Comment from {$firstName}: "{$commentText}"

                CRITICAL RULE: Detect the language of the comment and reply EXCLUSIVELY in that SAME language.
                If the comment is in French → reply in French.
                If in English → reply in English.
                If in Spanish → reply in Spanish.
                If in Arabic → reply in Arabic. Etc.
                NEVER reply in a different language than the comment.

                Generate exactly 3 reply variants:
                - Short (40-100 characters each)
                - Human, never robotic or generic
                - Varied tones: 1 warm/thankful, 1 informative/valuable, 1 question back to them
                - No hashtags
                - Use first name only if mentioning the author

                Return ONLY valid JSON: {"detected_lang": "fr|en|es|...", "replies": ["reply1", "reply2", "reply3"]}
                USER,
                ['model' => 'gpt-4o-mini', 'max_tokens' => 300, 'json_mode' => true]
            );

            if ($result['success'] ?? false) {
                $data    = json_decode($result['content'] ?? '', true);
                $replies = $data['replies'] ?? [];
                if (count($replies) >= 3) {
                    Log::info('CheckLinkedInCommentsCommand: variants generated', [
                        'detected_lang' => $data['detected_lang'] ?? 'unknown',
                        'author'        => $authorName,
                    ]);
                    return array_slice($replies, 0, 3);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CheckLinkedInCommentsCommand: variant generation failed', ['error' => $e->getMessage()]);
        }

        // Language-agnostic fallbacks (short, work in any context)
        return [
            'Merci ! 🙏',
            'Bonne question — visitez SOS-Expat.com pour plus de détails.',
            'Et vous, quelle a été votre expérience ? 👇',
        ];
    }

    // ── Send Telegram notification ─────────────────────────────────────

    private function sendTelegramNotification(
        LinkedInPostComment $comment,
        LinkedInPost        $post,
        string              $postUrn,
        string              $accountType,
        array               $variants,
    ): ?int {
        $author     = $comment->author_name ?? 'Inconnu';
        $text       = mb_substr($comment->comment_text, 0, 300);
        $postHook   = mb_substr($post->hook ?? '', 0, 80);
        $timeAgo    = $comment->commented_at?->diffForHumans() ?? 'maintenant';
        $accLabel   = $accountType === 'page' ? '🏢 Page' : '👤 Perso';

        $msg = <<<HTML
        💬 <b>Nouveau commentaire LinkedIn !</b>

        📌 Post : <i>{$postHook}...</i>
        🕐 {$timeAgo} · {$accLabel}

        <b>{$author} :</b>
        "{$text}"
        HTML;

        // Build inline keyboard
        $buttons = [];

        // Row 1-3: reply variants (one per row for readability)
        foreach ($variants as $i => $v) {
            $label = mb_substr($v, 0, 45); // Telegram button label max
            $buttons[] = [
                ['text' => "↩️ {$label}", 'callback_data' => "li_replyv_{$comment->id}_{$i}"],
            ];
        }

        // Last row: Personnaliser + Ignorer
        $buttons[] = [
            ['text' => '✏️ Personnaliser',  'callback_data' => "li_custm_{$comment->id}"],
            ['text' => '🔇 Ignorer',         'callback_data' => "li_ignr_{$comment->id}"],
        ];

        return $this->telegram->sendInlineKeyboard(trim($msg), $buttons);
    }
}
