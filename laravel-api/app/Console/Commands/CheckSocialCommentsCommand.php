<?php

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Services\AI\OpenAiService;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Multi-platform comment poller — run every 15 minutes.
 *
 * For each enabled platform, for each published post in the last 30 days:
 *  1. Fetch comments via driver->getComments()
 *  2. Persist new ones in social_post_comments
 *  3. Generate 3 reply variants (GPT-4o-mini, language auto-detected)
 *  4. Send Telegram notification with variant buttons +
 *     "Personnaliser" / "Ignorer" (handled by SocialTelegramController — TBD)
 *
 * Notes:
 *  - The platform-specific Telegram webhook controller is not yet created;
 *    buttons with callback `social_{platform}_*` will be wired up in a later
 *    phase. Until then, notifications work (admin sees new comments) but
 *    tap actions are inert for non-LinkedIn platforms.
 */
class CheckSocialCommentsCommand extends Command
{
    protected $signature   = 'social:check-comments
                               {--platform= : Only this platform (default: all enabled)}';
    protected $description = 'Poll platform APIs for new comments, notify via Telegram';

    public function __construct(private SocialDriverManager $manager) {
        parent::__construct();
    }

    public function handle(): int
    {
        $platforms = $this->option('platform')
            ? [$this->option('platform')]
            : $this->manager->availablePlatforms();

        foreach ($platforms as $platform) {
            if (!$this->manager->isEnabled($platform)) continue;
            $this->checkPlatform($platform);
        }

        return self::SUCCESS;
    }

    private function checkPlatform(string $platform): void
    {
        $driver   = $this->manager->driver($platform);
        $telegram = app(TelegramAlertService::class, ['bot' => $platform]);

        if (!$driver->isConfigured()) return;
        if (!$telegram->isConfigured()) {
            Log::info("social:check-comments: {$platform} telegram not configured, skipping");
            return;
        }

        $posts = SocialPost::forPlatform($platform)
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(30))
            ->whereNotNull('platform_post_id')
            ->get();

        $newCount = 0;
        foreach ($posts as $post) {
            $newCount += $this->checkPost($post, $driver, $telegram);
        }

        if ($newCount > 0) {
            $this->info("social:check-comments: {$platform} -> {$newCount} new comment(s) notified");
        }
    }

    private function checkPost(SocialPost $post, $driver, TelegramAlertService $telegram): int
    {
        $accountType = $post->account_type ?: $driver->supportedAccountTypes()[0];
        $apiComments = $driver->getComments($post->platform_post_id, $accountType);

        if (empty($apiComments)) return 0;

        $newCount = 0;

        foreach ($apiComments as $c) {
            $pcid = $c['platform_comment_id'] ?? null;
            if (!$pcid) continue;

            if (SocialPostComment::where('platform', $post->platform)
                ->where('platform_comment_id', $pcid)->exists()) {
                continue;
            }

            // Ignore our own first_comment (heuristic: text starts with first_comment prefix)
            if ($post->first_comment
                && str_starts_with($c['text'] ?? '', mb_substr($post->first_comment, 0, 50))) {
                SocialPostComment::create([
                    'social_post_id'      => $post->id,
                    'platform'            => $post->platform,
                    'platform_comment_id' => $pcid,
                    'author_name'         => $c['author_name']        ?? null,
                    'author_platform_id'  => $c['author_platform_id'] ?? null,
                    'comment_text'        => $c['text']               ?? '',
                    'commented_at'        => $c['commented_at']       ?? now(),
                    'reply_source'        => 'manual',
                    'replied_at'          => now(),
                    'reply_text'          => $c['text']               ?? '',
                ]);
                continue;
            }

            $comment = SocialPostComment::create([
                'social_post_id'      => $post->id,
                'platform'            => $post->platform,
                'platform_comment_id' => $pcid,
                'author_name'         => $c['author_name']        ?? null,
                'author_platform_id'  => $c['author_platform_id'] ?? null,
                'comment_text'        => $c['text']               ?? '',
                'commented_at'        => $c['commented_at']       ?? now(),
            ]);

            $variants = $this->generateVariants($post, $c['text'] ?? '', $c['author_name'] ?? 'Anonymous');

            // Cache variants 10 min so the Telegram controller can fetch them on button tap
            try {
                Redis::setex("social_variants_{$post->platform}_{$comment->id}", 600, json_encode($variants));
            } catch (\Throwable) {}

            $msgId = $this->sendNotification($telegram, $comment, $post, $variants);

            $comment->update([
                'telegram_notified_at' => now(),
                'telegram_msg_id'      => $msgId,
            ]);

            $newCount++;
        }

        return $newCount;
    }

    private function generateVariants(SocialPost $post, string $commentText, string $authorName): array
    {
        try {
            $openai    = app(OpenAiService::class);
            $postHook  = mb_substr($post->hook ?? '', 0, 120);
            $firstName = explode(' ', trim($authorName))[0] ?? $authorName;
            $platform  = $post->platform;

            $result = $openai->complete(
                "You are an expert community manager for SOS-Expat.com (connecting expats with lawyers and experts in 197 countries). You generate short, authentic replies to {$platform} comments.",
                <<<USER
                {$platform} post (opening line): "{$postHook}"
                Comment from {$firstName}: "{$commentText}"

                CRITICAL RULE: Detect the language of the comment and reply EXCLUSIVELY in that SAME language.
                If the comment is in French → reply in French.
                If in English → reply in English. Etc.
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
                if (count($replies) >= 3) return array_slice($replies, 0, 3);
            }
        } catch (\Throwable $e) {
            Log::warning('social:check-comments: variant generation failed', [
                'error' => $e->getMessage(),
                'platform' => $post->platform,
            ]);
        }

        return [
            'Merci ! 🙏',
            'Bonne question — visitez SOS-Expat.com pour plus de détails.',
            'Et vous, quelle a été votre expérience ? 👇',
        ];
    }

    private function sendNotification(
        TelegramAlertService $telegram,
        SocialPostComment    $comment,
        SocialPost           $post,
        array                $variants,
    ): ?int {
        $platform = $post->platform;
        $author   = $comment->author_name ?? 'Inconnu';
        $text     = mb_substr($comment->comment_text, 0, 300);
        $postHook = mb_substr($post->hook ?? '', 0, 80);
        $timeAgo  = $comment->commented_at?->diffForHumans() ?? 'maintenant';
        $accLabel = $post->account_type ? ucfirst($post->account_type) : 'default';
        $platLbl  = ucfirst($platform);

        $msg = <<<HTML
        💬 <b>Nouveau commentaire {$platLbl} !</b>

        📌 Post : <i>{$postHook}...</i>
        🕐 {$timeAgo} · {$accLabel}

        <b>{$author} :</b>
        "{$text}"
        HTML;

        $buttons = [];
        foreach ($variants as $i => $v) {
            $label = mb_substr($v, 0, 45);
            $buttons[] = [
                ['text' => "↩️ {$label}", 'callback_data' => "social_{$platform}_replyv_{$comment->id}_{$i}"],
            ];
        }
        $buttons[] = [
            ['text' => '✏️ Personnaliser', 'callback_data' => "social_{$platform}_custm_{$comment->id}"],
            ['text' => '🔇 Ignorer',        'callback_data' => "social_{$platform}_ignr_{$comment->id}"],
        ];

        return $telegram->sendInlineKeyboard(trim($msg), $buttons);
    }
}
