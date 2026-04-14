<?php

namespace App\Console\Commands;

use App\Jobs\PostLinkedInFirstCommentJob;
use App\Models\LinkedInPost;
use App\Services\Social\LinkedInApiService;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every 5 minutes via scheduler.
 *
 * ─ Mode auto-publish (default, LINKEDIN_TELEGRAM_CONFIRM=false) ─────────
 *   Publishes directly on LinkedIn when scheduled_at <= now().
 *   account=personal → publish personal, status=published
 *   (page/both disabled — Community Management API not yet approved by LinkedIn)
 *
 * ─ Mode Telegram 1-tap confirm (LINKEDIN_TELEGRAM_CONFIRM=true) ─────────
 *   ToS-compliant interim mode while Community Management API approval pending.
 *   Instead of auto-publishing, sends a Telegram preview with ✅/❌ buttons.
 *   Status → pending_confirm (shown in queue as "En attente de confirmation").
 *   When admin taps ✅ → LinkedInTelegramController::webhook() → publishes.
 *   When admin taps ❌ → post rescheduled +1 day.
 *
 * After each publish: dispatch delayed job to post first_comment after 3 min.
 */
class AutoPublishLinkedInCommand extends Command
{
    protected $signature   = 'linkedin:auto-publish';
    protected $description = 'Auto-publish (or confirm via Telegram) scheduled LinkedIn posts';

    public function __construct(
        private LinkedInApiService  $api,
        private TelegramAlertService $telegram,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $confirmMode = (bool) config('services.linkedin.telegram_confirm', false);

        // ── Posts ready for publish (personal profile only) ──────────
        $ready = LinkedInPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->whereNull('li_post_id_personal')
            ->get();

        foreach ($ready as $post) {
            if ($confirmMode) {
                $this->sendTelegramConfirm($post);
            } else {
                $this->publishFirst($post);
            }
        }

        $total = $ready->count();
        if ($total > 0) {
            $this->info("linkedin:auto-publish: processed {$total} posts (confirm_mode=" . ($confirmMode ? 'on' : 'off') . ')');
        }

        return self::SUCCESS;
    }

    // ── Telegram 1-tap confirm ──────────────────────────────────────────

    private function sendTelegramConfirm(LinkedInPost $post): void
    {
        if (!$this->telegram->isConfigured()) {
            // Fallback to direct publish if Telegram not configured
            $this->publishFirst($post);
            return;
        }

        $hook     = mb_substr($post->hook ?? '', 0, 120);
        $body     = mb_substr($post->body ?? '', 0, 200);
        $hashStr  = implode(' ', array_map(fn($h) => "#{$h}", array_slice($post->hashtags ?? [], 0, 3)));
        $account  = strtoupper($post->account ?? 'both');
        $lang     = strtoupper($post->lang ?? 'fr');
        $dayType  = ucfirst($post->day_type ?? '');
        $slot     = $post->scheduled_at?->format('H:i') ?? '';

        $preview = <<<TEXT
        📣 <b>LinkedIn — Publication prête !</b>

        🕐 Programmé : <b>{$slot}</b> | {$dayType} | {$lang} | {$account}

        <b>{$hook}</b>

        {$body}...

        {$hashStr}
        TEXT;

        $buttons = [
            [
                ['text' => '✅ Publier maintenant',  'callback_data' => "li_confirm_{$post->id}"],
                ['text' => '❌ Repousser +1 jour',   'callback_data' => "li_skip_{$post->id}"],
            ],
        ];

        $msgId = $this->telegram->sendInlineKeyboard(trim($preview), $buttons);

        $post->update([
            'status'              => 'pending_confirm',
            'error_message'       => null,
            'li_telegram_msg_id'  => $msgId,
        ]);

        Log::info('linkedin:auto-publish: Telegram confirm sent', [
            'post_id'    => $post->id,
            'message_id' => $msgId,
        ]);
    }

    // ── Direct publish (no confirmation) ───────────────────────────────

    private function publishFirst(LinkedInPost $post): void
    {
        try {
            // Always publish on personal profile (Community Management API not yet approved)
            $urn = $this->api->publish($post, 'personal');
            if (!$urn) { $this->failPost($post, 'LinkedIn personal API returned no URN'); return; }
            $post->update(['li_post_id_personal' => $urn, 'status' => 'published', 'published_at' => now()]);
            $this->scheduleFirstComment($post, $urn, 'personal');

        } catch (\Throwable $e) {
            $this->failPost($post, $e->getMessage());
        }
    }

    // ── Delayed first comment (3 min after post) ───────────────────────

    private function scheduleFirstComment(LinkedInPost $post, string $postUrn, string $accountType): void
    {
        if (!$post->first_comment) return;
        PostLinkedInFirstCommentJob::dispatch($post->id, $postUrn, $accountType)->delay(now()->addMinutes(3));
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function failPost(LinkedInPost $post, string $reason): void
    {
        $post->update(['status' => 'failed', 'error_message' => $reason]);
        Log::error('linkedin:auto-publish: failed', ['post_id' => $post->id, 'reason' => $reason]);
    }
}
