<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Multi-platform social post record.
 *
 * platform:    linkedin | facebook | threads | instagram
 * status:      generating | draft | scheduled | pending_confirm | published | failed
 * phase:       1 = FR-dominant rollout, 2 = global (FR + EN)
 * source_type: article | faq | sondage | hot_take | myth | poll | serie |
 *              reactive | milestone | partner_story | counter_intuition |
 *              tip | news | case_study
 */
class SocialPost extends Model
{
    protected $table = 'social_posts';

    public const PLATFORMS = ['linkedin', 'facebook', 'threads', 'instagram'];

    protected $fillable = [
        'platform',
        'source_type', 'source_id', 'source_title',
        'day_type', 'lang', 'account_type',
        'hook', 'body', 'hashtags',
        'first_comment', 'featured_image_url',
        'first_comment_posted_at', 'first_comment_status', 'reply_variants',
        'status', 'scheduled_at', 'published_at', 'auto_scheduled',
        'page_publish_after', 'page_published_at', 'publish_error_page',
        'platform_post_id', 'platform_post_id_secondary', 'platform_metadata',
        'telegram_msg_id',
        'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement_rate',
        'phase', 'error_message',
    ];

    protected $casts = [
        'hashtags'                 => 'array',
        'reply_variants'           => 'array',
        'platform_metadata'        => 'array',
        'scheduled_at'             => 'datetime',
        'published_at'             => 'datetime',
        'first_comment_posted_at'  => 'datetime',
        'auto_scheduled'           => 'boolean',
        'page_publish_after'       => 'datetime',
        'page_published_at'        => 'datetime',
        'phase'                    => 'integer',
        'reach'                    => 'integer',
        'likes'                    => 'integer',
        'comments'                 => 'integer',
        'shares'                   => 'integer',
        'clicks'                   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'source_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(QaEntry::class, 'source_id');
    }

    public function sondage(): BelongsTo
    {
        return $this->belongsTo(Sondage::class, 'source_id');
    }

    public function platformComments(): HasMany
    {
        return $this->hasMany(SocialPostComment::class, 'social_post_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** Types that require no DB source (free AI generation). */
    public function isFreeGeneration(): bool
    {
        return in_array($this->source_type, [
            'hot_take', 'myth', 'poll', 'serie', 'reactive',
            'milestone', 'partner_story', 'counter_intuition', 'tip', 'news', 'case_study',
        ], true);
    }

    public function fullText(): string
    {
        return $this->hook . "\n\n" . $this->body;
    }

    public function charCount(): int
    {
        return mb_strlen($this->fullText());
    }
}
