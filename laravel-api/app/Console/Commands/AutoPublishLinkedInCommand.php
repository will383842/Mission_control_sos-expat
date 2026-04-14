<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use App\Services\Social\LinkedInApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every 5 minutes via scheduler.
 *
 * Logic:
 *   account=personal → publish personal, set li_post_id_personal, status=published
 *   account=page     → publish page,     set li_post_id_page,     status=published
 *   account=both     → Step 1: publish personal now, set page_publish_after = now+4h30
 *                    → Step 2: at page_publish_after, publish page, status=published
 *
 * After publishing: dispatch delayed job to post first_comment after 3 min.
 */
class AutoPublishLinkedInCommand extends Command
{
    protected $signature   = 'linkedin:auto-publish';
    protected $description = 'Auto-publish scheduled LinkedIn posts via LinkedIn API v2';

    public function __construct(private LinkedInApiService $api) {
        parent::__construct();
    }

    public function handle(): int
    {
        // ── Step 1: posts ready for FIRST publish (personal or page) ──
        $ready = LinkedInPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->whereNull('li_post_id_personal')
            ->whereNull('li_post_id_page')
            ->get();

        foreach ($ready as $post) {
            $this->publishFirst($post);
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
            $this->info("linkedin:auto-publish: processed {$total} posts");
        }

        return self::SUCCESS;
    }

    // ── First publish (personal or page or both-step1) ─────────────────

    private function publishFirst(LinkedInPost $post): void
    {
        try {
            if ($post->account === 'page') {
                $urn = $this->api->publish($post, 'page');
                if (!$urn) {
                    $this->failPost($post, 'LinkedIn page API returned no URN');
                    return;
                }
                $post->update([
                    'li_post_id_page' => $urn,
                    'status'          => 'published',
                    'published_at'    => now(),
                ]);
                $this->scheduleFirstComment($post, $urn, 'page');

            } elseif ($post->account === 'personal') {
                $urn = $this->api->publish($post, 'personal');
                if (!$urn) {
                    $this->failPost($post, 'LinkedIn personal API returned no URN');
                    return;
                }
                $post->update([
                    'li_post_id_personal' => $urn,
                    'status'              => 'published',
                    'published_at'        => now(),
                ]);
                $this->scheduleFirstComment($post, $urn, 'personal');

            } elseif ($post->account === 'both') {
                // Publish personal first
                $urn = $this->api->publish($post, 'personal');
                if (!$urn) {
                    $this->failPost($post, 'LinkedIn personal API returned no URN (both)');
                    return;
                }
                // Schedule page for 4h30 later
                $post->update([
                    'li_post_id_personal' => $urn,
                    'page_publish_after'  => now()->addHours(4)->addMinutes(30),
                    // status stays 'scheduled' until page is also published
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
                'li_post_id_page'  => $urn,
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

        \App\Jobs\PostLinkedInFirstCommentJob::dispatch(
            $post->id,
            $postUrn,
            $accountType
        )->delay(now()->addMinutes(3));
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function failPost(LinkedInPost $post, string $reason): void
    {
        $post->update([
            'status'        => 'failed',
            'error_message' => $reason,
        ]);
        Log::error('linkedin:auto-publish: failed', [
            'post_id' => $post->id,
            'reason'  => $reason,
        ]);
    }
}
