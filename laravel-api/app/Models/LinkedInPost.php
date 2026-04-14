<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArray;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LinkedIn post record.
 *
 * status:      generating | draft | scheduled | published | failed
 * phase:       1 = Francophone clients (FR dominant, now → Aug 2026)
 *              2 = Global expansion (FR + EN, Sep 2026+)
 * source_type: article | faq | sondage | hot_take | myth | poll | serie |
 *              reactive | milestone | partner_story | counter_intuition |
 *              tip | news | case_study
 *
 * first_comment: auto-posted 3 min after publication (LinkedIn API v2)
 * featured_image_url: optional image from source article
 */
class LinkedInPost extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'source_title',
        'day_type', 'lang', 'account',
        'hook', 'body', 'hashtags',
        'first_comment', 'featured_image_url',
        'first_comment_posted_at', 'first_comment_status', 'reply_variants',
        'status', 'scheduled_at', 'published_at', 'auto_scheduled',
        'li_post_id_page', 'li_post_id_personal',
        'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement_rate',
        'phase', 'error_message',
    ];

    protected $casts = [
        'hashtags'                 => AsArray::class,
        'reply_variants'           => AsArray::class,
        'scheduled_at'             => 'datetime',
        'published_at'             => 'datetime',
        'first_comment_posted_at'  => 'datetime',
        'auto_scheduled'           => 'boolean',
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

    // ── Helpers ────────────────────────────────────────────────────────

    /** Types that require no DB source (free AI generation) */
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
