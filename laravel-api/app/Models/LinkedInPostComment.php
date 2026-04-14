<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment received on a published LinkedIn post.
 *
 * Populated by CheckLinkedInCommentsCommand (runs every 15 min).
 * Telegram notification sent for each new comment.
 * Reply tracked via reply_text + replied_at + reply_source.
 */
class LinkedInPostComment extends Model
{
    protected $fillable = [
        'linkedin_post_id',
        'comment_urn',
        'author_name',
        'author_urn',
        'comment_text',
        'commented_at',
        'telegram_notified_at',
        'telegram_msg_id',
        'reply_text',
        'replied_at',
        'reply_source',
    ];

    protected $casts = [
        'commented_at'         => 'datetime',
        'telegram_notified_at' => 'datetime',
        'replied_at'           => 'datetime',
    ];

    public function linkedinPost(): BelongsTo
    {
        return $this->belongsTo(LinkedInPost::class, 'linkedin_post_id');
    }
}
