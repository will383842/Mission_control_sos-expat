<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QaEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_article_id', 'cluster_id',
        'question', 'answer_short', 'answer_detailed_html',
        'language', 'country', 'category', 'slug',
        'meta_title', 'meta_description', 'canonical_url',
        'json_ld', 'hreflang_map',
        'keywords_primary', 'keywords_secondary',
        'seo_score', 'word_count',
        'source_type', 'status',
        'generation_cost_cents',
        'parent_qa_id', 'related_qa_ids', 'sources',
        'published_at', 'external_url', 'external_id',
        'created_by',
    ];

    protected $casts = [
        'json_ld'               => 'array',
        'hreflang_map'          => 'array',
        'keywords_secondary'    => 'array',
        'related_qa_ids'        => 'array',
        'sources'               => 'array',
        'seo_score'             => 'integer',
        'word_count'            => 'integer',
        'generation_cost_cents' => 'integer',
        'published_at'          => 'datetime',
    ];

    // ============================================================
    // Boot
    // ============================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ============================================================
    // Relationships
    // ============================================================

    public function parentArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'parent_article_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(TopicCluster::class, 'cluster_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QaEntry::class, 'parent_qa_id');
    }

    public function parentQa(): BelongsTo
    {
        return $this->belongsTo(QaEntry::class, 'parent_qa_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function seoAnalysis(): MorphOne
    {
        return $this->morphOne(SeoAnalysis::class, 'analyzable')->latestOfMany();
    }

    public function publicationQueue(): MorphMany
    {
        return $this->morphMany(PublicationQueueItem::class, 'publishable');
    }

    public function apiCosts(): MorphMany
    {
        return $this->morphMany(ApiCost::class, 'costable');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeLanguage(Builder $query, string $lang): Builder
    {
        return $query->where('language', $lang);
    }

    public function scopeCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getUrlAttribute(): string
    {
        $countrySlug = Str::slug($this->country ?? 'general');

        return "/{$this->language}/qa/{$countrySlug}/{$this->slug}";
    }
}
