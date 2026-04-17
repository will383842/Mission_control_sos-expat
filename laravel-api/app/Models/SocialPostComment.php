<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment received on a published social post (any platform).
 *
 * Populated by CheckSocialCommentsCommand (polling) or by platform webhooks
 * (Facebook / Instagram Graph). Telegram notifications + reply tracking
 * work the same way on every platform.
 */
class SocialPostComment extends Model
{
    protected $table = 'social_post_comments';

    protected $fillable = [
        'social_post_id',
        'platform',
        'platform_comment_id',
        'author_name',
        'author_platform_id',
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

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }
}
