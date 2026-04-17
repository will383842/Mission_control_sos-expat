<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * STUB — full implementation in Phase 4.
 *
 * Generates hook/body/hashtags/first_comment/featured_image_url for a SocialPost
 * using the platform's driver capability flags (maxContentLength, supportsHashtags,
 * supportsFirstComment, requiresImage) + Claude/OpenAI + Unsplash.
 *
 * Queue name is derived from config('social.drivers.{platform}.queue').
 */
class GenerateSocialPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;
    public array $backoff = [30, 120];

    public function __construct(public int $postId, public string $platform)
    {
        // Route to the platform-dedicated queue so FB/IG/Threads/LinkedIn can be
        // scaled or paused independently (e.g. during Meta App Review freeze).
        $this->onQueue(config("social.drivers.{$platform}.queue", 'default'));
    }

    public function handle(): void
    {
        $post = \App\Models\SocialPost::find($this->postId);
        if (!$post) return;

        \Illuminate\Support\Facades\Log::warning(
            'GenerateSocialPostJob is a stub — no content generated.',
            ['post_id' => $this->postId, 'platform' => $post->platform]
        );
        // TODO Phase 4: port GenerateLinkedInPostJob logic, use driver capability flags.
    }
}
