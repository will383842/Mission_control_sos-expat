<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GeneratedArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'source_article_id', 'generation_preset_id', 'parent_article_id', 'pillar_article_id',
        'title', 'slug', 'content_html', 'content_text', 'excerpt',
        'meta_title', 'meta_description', 'og_title', 'og_description', 'ai_summary',
        'keywords_primary', 'keywords_secondary', 'keyword_density',
        'featured_image_url', 'featured_image_alt', 'featured_image_attribution',
        'featured_image_srcset', 'photographer_name', 'photographer_url',
        'language', 'country', 'content_type', 'source_slug', 'input_quality',
        'json_ld', 'hreflang_map',
        'seo_score', 'quality_score', 'fact_check_score', 'readability_score',
        'word_count', 'reading_time_minutes',
        'generation_cost_cents', 'generation_tokens_input', 'generation_tokens_output',
        'generation_duration_seconds', 'generation_model',
        'status',
        'published_at', 'scheduled_at',
        'canonical_url', 'external_url', 'external_id',
        'og_type', 'og_locale', 'og_url', 'og_site_name', 'twitter_card',
        'geo_region', 'geo_placename', 'geo_position', 'icbm',
        'meta_keywords', 'content_language', 'last_reviewed_at',
        'created_by',
    ];

    protected $casts = [
        'json_ld'                   => 'array',
        'hreflang_map'              => 'array',
        'keywords_secondary'        => 'array',
        'keyword_density'           => 'array',
        'seo_score'                 => 'integer',
        'quality_score'             => 'integer',
        'fact_check_score'          => 'integer',
        'readability_score'         => 'decimal:2',
        'generation_cost_cents'     => 'integer',
        'generation_tokens_input'   => 'integer',
        'generation_tokens_output'  => 'integer',
        'published_at'              => 'datetime',
        'scheduled_at'              => 'datetime',
        'word_count'                    => 'integer',
        'reading_time_minutes'          => 'integer',
        'generation_duration_seconds'   => 'integer',
        'last_reviewed_at'  => 'datetime',
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

    public function faqs(): HasMany
    {
        return $this->hasMany(GeneratedArticleFaq::class, 'article_id')->orderBy('sort_order');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(GeneratedArticleSource::class, 'article_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GeneratedArticleVersion::class, 'article_id')->orderByDesc('version_number');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GeneratedArticleImage::class, 'article_id')->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(GeneratedArticle::class, 'parent_article_id');
    }

    public function parentArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'parent_article_id');
    }

    public function pillarArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'pillar_article_id');
    }

    public function clusterArticles(): HasMany
    {
        return $this->hasMany(GeneratedArticle::class, 'pillar_article_id');
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(ContentArticle::class, 'source_article_id');
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(GenerationPreset::class, 'generation_preset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generationLogs(): MorphMany
    {
        return $this->morphMany(GenerationLog::class, 'loggable');
    }

    public function seoAnalysis(): MorphOne
    {
        return $this->morphOne(SeoAnalysis::class, 'analyzable')->latestOfMany();
    }

    public function internalLinksOut(): MorphMany
    {
        return $this->morphMany(InternalLink::class, 'source');
    }

    public function internalLinksIn(): MorphMany
    {
        return $this->morphMany(InternalLink::class, 'target');
    }

    public function externalLinks(): MorphMany
    {
        return $this->morphMany(ExternalLinkRegistry::class, 'article');
    }

    public function affiliateLinks(): MorphMany
    {
        return $this->morphMany(AffiliateLink::class, 'article');
    }

    public function publicationQueue(): MorphMany
    {
        return $this->morphMany(PublicationQueueItem::class, 'publishable');
    }

    public function goldenExample(): HasOne
    {
        return $this->hasOne(GoldenExample::class, 'article_id');
    }

    public function apiCosts(): MorphMany
    {
        return $this->morphMany(ApiCost::class, 'costable');
    }

    public function topicCluster(): HasOne
    {
        return $this->hasOne(TopicCluster::class, 'generated_article_id');
    }

    public function qaEntries(): HasMany
    {
        return $this->hasMany(QaEntry::class, 'parent_article_id');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(KeywordTracking::class, 'article_keywords', 'article_id', 'keyword_id')
            ->withPivot('usage_type', 'density_percent', 'occurrences', 'position_context')
            ->withTimestamps();
    }

    public function seoChecklist(): HasOne
    {
        return $this->hasOne(SeoChecklist::class, 'article_id');
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

    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('parent_article_id');
    }

    public function scopePillars(Builder $query): Builder
    {
        return $query->whereNull('pillar_article_id')->whereHas('clusterArticles');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getEstimatedCostAttribute(): float
    {
        return $this->generation_cost_cents / 100;
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function getUrlAttribute(): string
    {
        return "/{$this->language}/blog/{$this->slug}";
    }
}
