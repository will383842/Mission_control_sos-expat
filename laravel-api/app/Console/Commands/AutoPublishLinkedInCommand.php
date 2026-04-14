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
 *   account=page     → publish page,     status=published
 *   account=both     → Step 1: publish personal, set page_publish_after=now+4h30
 *                    → Step 2: at page_publish_after, publish page, status=published
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

        // ── Step 1: posts ready for FIRST publish ────────────────────
        $ready = LinkedInPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->whereNull('li_post_id_personal')
            ->whereNull('li_post_id_page')
            ->get();

        foreach ($ready as $post) {
            if ($confirmMode) {
                $this->sendTelegramConfirm($post);
            } else {
                $this->publishFirst($post);
            }
        }

        // ── Step 2: account=both, ready for PAGE publish (4h30 later) ─
        $pageReady = LinkedInPost::where('status', 'scheduled')
            ->whereNotNull('li_post_id_personal')
            ->whereNull('li_post_id_page')
            ->whereNotNull('page_publish_after')
            ->where('page_publish_after', '<=', now())
            ->get();

        foreach ($pageReady as $post) {
            $this->publishPage($post);
        }

        $total = $ready->count() + $pageReady->count();
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
            if ($post->account === 'page') {
                $urn = $this->api->publish($post, 'page');
                if (!$urn) { $this->failPost($post, 'LinkedIn page API returned no URN'); return; }
                $post->update(['li_post_id_page' => $urn, 'status' => 'published', 'published_at' => now()]);
                $this->scheduleFirstComment($post, $urn, 'page');

            } elseif ($post->account === 'personal') {
                $urn = $this->api->publish($post, 'personal');
                if (!$urn) { $this->failPost($post, 'LinkedIn personal API returned no URN'); return; }
                $post->update(['li_post_id_personal' => $urn, 'status' => 'published', 'published_at' => now()]);
                $this->scheduleFirstComment($post, $urn, 'personal');

            } elseif ($post->account === 'both') {
                $urn = $this->api->publish($post, 'personal');
                if (!$urn) { $this->failPost($post, 'LinkedIn personal API returned no URN (both)'); return; }
                $post->update([
                    'li_post_id_personal' => $urn,
                    'page_publish_after'  => now()->addHours(4)->addMinutes(30),
                ]);
                $this->scheduleFirstComment($post, $urn, 'personal');
                Log::info('linkedin:auto-publish: personal done, page in 4h30', ['post_id' => $post->id]);
            }

        } catch (\Throwable $e) {
            $this->failPost($post, $e->getMessage());
        }
    }

    // ── Page publish (account=both, step 2) ────────────────────────────

    private function publishPage(LinkedInPost $post): void
    {
        try {
            $urn = $this->api->publish($post, 'page');
            if (!$urn) {
                $post->update(['publish_error_page' => 'LinkedIn page API returned no URN']);
                return;
            }
            $post->update([
                'li_post_id_page'   => $urn,
                'page_published_at' => now(),
                'status'            => 'published',
                'published_at'      => $post->published_at ?? now(),
            ]);
            $this->scheduleFirstComment($post, $urn, 'page');
            Log::info('linkedin:auto-publish: page published', ['post_id' => $post->id, 'urn' => $urn]);

        } catch (\Throwable $e) {
            $post->update(['publish_error_page' => $e->getMessage()]);
            Log::error('linkedin:auto-publish: page failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
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
