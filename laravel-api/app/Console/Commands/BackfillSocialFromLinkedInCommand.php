<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use App\Models\LinkedInPostComment;
use App\Models\LinkedInToken;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot cutover command — copies linkedin_* data into social_* with
 * platform='linkedin'. Idempotent: running it twice updates existing rows
 * instead of creating duplicates (match key = platform + platform_post_id
 * for posts, platform + account_type for tokens, platform + platform_comment_id
 * for comments).
 *
 * Usage:
 *   php artisan social:backfill-from-linkedin --dry-run     # just counts
 *   php artisan social:backfill-from-linkedin               # actual copy
 *   php artisan social:backfill-from-linkedin --chunk=100   # smaller chunks
 *   php artisan social:backfill-from-linkedin --only=posts  # posts only
 *
 * After a successful run you can retire the old `/api/content-gen/linkedin/*`
 * code path and rename `linkedin_*` tables to `_legacy_linkedin_*`.
 */
class BackfillSocialFromLinkedInCommand extends Command
{
    protected $signature   = 'social:backfill-from-linkedin
                               {--dry-run        : Show counts without writing}
                               {--chunk=200      : Rows per chunk}
                               {--only=          : Restrict to one section (posts|tokens|comments)}';

    protected $description = 'Copy linkedin_* rows into social_* tables (platform=linkedin).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(20, min(1000, (int) $this->option('chunk')));
        $only   = $this->option('only') ?: null;

        $this->info('=== social:backfill-from-linkedin' . ($dryRun ? ' [DRY RUN]' : '') . ' ===');

        $run = fn(string $section, callable $cb) => ($only === null || $only === $section) ? $cb() : 0;

        $stats = [
            'tokens'   => $run('tokens',   fn() => $this->backfillTokens($dryRun)),
            'posts'    => $run('posts',    fn() => $this->backfillPosts($dryRun, $chunk)),
            'comments' => $run('comments', fn() => $this->backfillComments($dryRun, $chunk)),
        ];

        $this->line('');
        $this->info('Summary:');
        foreach ($stats as $section => $count) {
            $this->line("   {$section}: {$count} row(s)");
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no rows written. Rerun without --dry-run to commit.');
        } else {
            $this->info('Backfill complete. Next step: deploy the frontend on /social/linkedin and retire legacy code.');
        }

        return self::SUCCESS;
    }

    // ── Tokens ────────────────────────────────────────────────────────

    private function backfillTokens(bool $dryRun): int
    {
        $rows = LinkedInToken::all();

        if ($dryRun) {
            $this->line("   tokens candidates: {$rows->count()}");
            return $rows->count();
        }

        $done = 0;
        foreach ($rows as $old) {
            SocialToken::updateOrCreate(
                [
                    'platform'     => 'linkedin',
                    'account_type' => $old->account_type,
                ],
                [
                    // access_token / refresh_token encrypted mutators re-encrypt on save.
                    // We read the decrypted value from the old model and let the new
                    // model re-encrypt it, so the ciphertext stays valid under its own key.
                    'access_token'             => $old->access_token,
                    'refresh_token'            => $old->refresh_token,
                    'expires_at'               => $old->expires_at,
                    'refresh_token_expires_at' => $old->refresh_token_expires_at,
                    'platform_user_id'         => $old->linkedin_id,
                    'platform_user_name'       => $old->linkedin_name,
                    'scope'                    => $old->scope,
                    'metadata'                 => null,
                ]
            );
            $done++;
        }

        $this->line("   tokens backfilled: {$done}");
        return $done;
    }

    // ── Posts ─────────────────────────────────────────────────────────

    private function backfillPosts(bool $dryRun, int $chunk): int
    {
        $total = LinkedInPost::count();
        $this->line("   posts candidates: {$total}");

        if ($dryRun) return $total;

        $done = 0;
        LinkedInPost::chunk($chunk, function ($rows) use (&$done) {
            foreach ($rows as $old) {
                $this->upsertPost($old);
                $done++;
            }
            $this->output->write("\r   posts backfilled: {$done}");
        });
        $this->line('');
        return $done;
    }

    private function upsertPost(LinkedInPost $old): void
    {
        // Map the dual-publish ids:
        //   social_posts.platform_post_id           ← primary (personal if set, else page)
        //   social_posts.platform_post_id_secondary ← the other one (page when dual-publish)
        [$primary, $secondary] = [$old->li_post_id_personal, $old->li_post_id_page];
        if (!$primary) [$primary, $secondary] = [$secondary, null];

        // Dedup key: platform + original linkedin_post.id (stored in platform_metadata)
        $metadata = [
            'legacy_linkedin_post_id' => $old->id,
            'legacy_li_post_id_personal' => $old->li_post_id_personal,
            'legacy_li_post_id_page'     => $old->li_post_id_page,
        ];

        // Look up an existing SocialPost row created from this LinkedInPost
        $existing = SocialPost::forPlatform('linkedin')
            ->whereJsonContains('platform_metadata->legacy_linkedin_post_id', $old->id)
            ->first();

        $attrs = [
            'platform'                    => 'linkedin',
            'source_type'                 => $old->source_type,
            'source_id'                   => $old->source_id,
            'source_title'                => $old->source_title,
            'day_type'                    => $old->day_type,
            'lang'                        => $old->lang,
            'account_type'                => $this->mapAccount($old->account),
            'hook'                        => $old->hook,
            'body'                        => $old->body,
            'hashtags'                    => $old->hashtags ?? [],
            'first_comment'               => $old->first_comment,
            'featured_image_url'          => $old->featured_image_url,
            'first_comment_posted_at'     => $old->first_comment_posted_at,
            'first_comment_status'        => $old->first_comment_status,
            'reply_variants'              => $old->reply_variants,
            'status'                      => $old->status,
            'scheduled_at'                => $old->scheduled_at,
            'published_at'                => $old->published_at,
            'auto_scheduled'              => (bool) $old->auto_scheduled,
            'page_publish_after'          => $old->page_publish_after,
            'page_published_at'           => $old->page_published_at,
            'publish_error_page'          => $old->publish_error_page,
            'platform_post_id'            => $primary,
            'platform_post_id_secondary'  => $secondary,
            'platform_metadata'           => $metadata,
            'telegram_msg_id'             => $old->li_telegram_msg_id,
            'reach'                       => (int) $old->reach,
            'likes'                       => (int) $old->likes,
            'comments'                    => (int) $old->comments,
            'shares'                      => (int) $old->shares,
            'clicks'                      => (int) $old->clicks,
            'engagement_rate'             => (float) $old->engagement_rate,
            'phase'                       => (int) $old->phase,
            'error_message'               => $old->error_message,
            'created_at'                  => $old->created_at,
            'updated_at'                  => $old->updated_at,
        ];

        if ($existing) {
            $existing->update($attrs);
        } else {
            SocialPost::create($attrs);
        }
    }

    /** Map legacy account enum (page|personal|both) → new account_type string. */
    private function mapAccount(?string $account): ?string
    {
        return match ($account) {
            'page'     => 'page',
            'personal' => 'personal',
            'both'     => 'personal', // primary URN is personal; secondary handled separately
            default    => null,
        };
    }

    // ── Comments ──────────────────────────────────────────────────────

    private function backfillComments(bool $dryRun, int $chunk): int
    {
        $total = LinkedInPostComment::count();
        $this->line("   comments candidates: {$total}");

        if ($dryRun) return $total;

        $done = 0;
        LinkedInPostComment::chunk($chunk, function ($rows) use (&$done) {
            foreach ($rows as $old) {
                $this->upsertComment($old);
                $done++;
            }
            $this->output->write("\r   comments backfilled: {$done}");
        });
        $this->line('');
        return $done;
    }

    private function upsertComment(LinkedInPostComment $old): void
    {
        // Resolve the new SocialPost id via the legacy id we stored in platform_metadata
        $newPost = SocialPost::forPlatform('linkedin')
            ->whereJsonContains('platform_metadata->legacy_linkedin_post_id', $old->linkedin_post_id)
            ->first();

        if (!$newPost) return; // orphan — skip (was the legacy post backfilled?)

        SocialPostComment::updateOrCreate(
            [
                'platform'            => 'linkedin',
                'platform_comment_id' => $old->comment_urn,
            ],
            [
                'social_post_id'       => $newPost->id,
                'author_name'          => $old->author_name,
                'author_platform_id'   => $old->author_urn,
                'comment_text'         => $old->comment_text,
                'commented_at'         => $old->commented_at,
                'telegram_notified_at' => $old->telegram_notified_at,
                'telegram_msg_id'      => $old->telegram_msg_id,
                'reply_text'           => $old->reply_text,
                'replied_at'           => $old->replied_at,
                'reply_source'         => $old->reply_source,
                'created_at'           => $old->created_at,
                'updated_at'           => $old->updated_at,
            ]
        );
    }
}
