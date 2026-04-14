<?php

namespace App\Jobs;

use App\Models\LinkedInPost;
use App\Services\Social\LinkedInApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Posted 3 minutes after a LinkedIn post is published.
 * Posts the first_comment text as a comment on the LinkedIn post.
 */
class PostLinkedInFirstCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries   = 3;

    public function __construct(
        public int    $postId,
        public string $liPostUrn,
        public string $accountType,
    ) {
        $this->onQueue('linkedin');
    }

    public function handle(LinkedInApiService $api): void
    {
        $post = LinkedInPost::find($this->postId);
        if (!$post || !$post->first_comment) return;

        $success = $api->postFirstComment($this->liPostUrn, $post->first_comment, $this->accountType);

        $post->update([
            'first_comment_status'    => $success ? 'posted' : 'failed',
            'first_comment_posted_at' => $success ? now() : null,
        ]);

        Log::info('PostLinkedInFirstCommentJob: ' . ($success ? 'posted' : 'failed'), [
            'post_id'      => $this->postId,
            'account_type' => $this->accountType,
        ]);
    }
}
