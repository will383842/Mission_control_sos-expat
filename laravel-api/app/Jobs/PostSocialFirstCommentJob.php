<?php

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\TelegramAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Post the first comment on a freshly-published social post.
 * Skips platforms whose driver has supportsFirstComment() === false (e.g. Threads).
 *
 * Scheduled after publication via
 *   config('social.auto_publish.first_comment_delay_seconds', 180).
 *
 * On failure, a Telegram notification is sent to the platform's dedicated bot
 * (the main post IS published — only the first_comment step failed).
 */
class PostSocialFirstCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries   = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $postId, public string $platform)
    {
        $this->onQueue(config("social.drivers.{$platform}.queue", 'default'));
    }

    public function handle(SocialDriverManager $manager): void
    {
        $post = SocialPost::find($this->postId);
        if (!$post || !$post->platform_post_id || empty($post->first_comment)) return;

        $driver = $manager->driver($post->platform);

        if (!$driver->supportsFirstComment()) {
            $post->update(['first_comment_status' => 'skipped']);
            return;
        }

        $accountType = $post->account_type ?: $driver->supportedAccountTypes()[0];

        try {
            $ok = $driver->postFirstComment(
                $post->platform_post_id,
                $post->first_comment,
                $accountType
            );

            $post->update([
                'first_comment_status'    => $ok ? 'posted' : 'failed',
                'first_comment_posted_at' => $ok ? now() : null,
            ]);

            if ($ok) {
                Log::info('PostSocialFirstCommentJob: posted', [
                    'platform'     => $post->platform,
                    'post_id'      => $post->id,
                    'account_type' => $accountType,
                ]);
                return;
            }

            Log::warning('PostSocialFirstCommentJob: failed — post may have been deleted remotely', [
                'platform'         => $post->platform,
                'post_id'          => $post->id,
                'platform_post_id' => $post->platform_post_id,
            ]);

            // Notify admin via platform's dedicated Telegram bot — main post IS published
            $this->notifyFailure($post);

        } catch (\Throwable $e) {
            Log::error('PostSocialFirstCommentJob failed with exception', [
                'platform' => $post->platform,
                'post_id'  => $this->postId,
                'error'    => $e->getMessage(),
            ]);
            $post->update(['first_comment_status' => 'failed']);
            $this->notifyFailure($post);
        }
    }

    private function notifyFailure(SocialPost $post): void
    {
        try {
            $telegram = app(TelegramAlertService::class, ['bot' => $post->platform]);
            if (!$telegram->isConfigured()) return;

            $hook     = mb_substr($post->hook ?? '', 0, 100);
            $platform = ucfirst($post->platform);

            $telegram->sendMessage(
                "⚠️ <b>{$platform} 1er commentaire échoué</b>\n\n"
                . "Post #{$this->postId}\n"
                . "<i>{$hook}</i>\n\n"
                . "Le post principal EST publié.\n"
                . "Seul le 1er commentaire a échoué (post supprimé manuellement ?).\n\n"
                . "→ Postez manuellement le commentaire si nécessaire."
            );
        } catch (\Throwable) {
            // don't let notification failure re-throw
        }
    }
}
