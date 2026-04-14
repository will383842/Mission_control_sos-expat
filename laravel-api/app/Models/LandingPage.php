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

class LandingPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id', 'title', 'slug',
        'meta_title', 'meta_description',
        'language', 'country',
        'sections', 'json_ld', 'hreflang_map',
        'seo_score', 'generation_cost_cents',
        'status',
        'published_at',
        'created_by',
        // Landing Generator fields
        'audience_type',
        'template_id',
        'problem_id',
        'country_code',
        'generation_source',
        'generation_params',
    ];

    protected $casts = [
        'sections'              => 'array',
        'json_ld'               => 'array',
        'hreflang_map'          => 'array',
        'generation_params'     => 'array',
        'seo_score'             => 'integer',
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

    public function ctaLinks(): HasMany
    {
        return $this->hasMany(LandingCtaLink::class)->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'parent_id');
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

    public function publicationQueue(): MorphMany
    {
        return $this->morphMany(PublicationQueueItem::class, 'publishable');
    }

    public function apiCosts(): MorphMany
    {
        return $this->morphMany(ApiCost::class, 'costable');
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

    public function scopeAudience(Builder $query, string $type): Builder
    {
        return $query->where('audience_type', $type);
    }

    public function scopeForCountry(Builder $query, string $code): Builder
    {
        return $query->where('country_code', $code);
    }

    public function scopeAiGenerated(Builder $query): Builder
    {
        return $query->where('generation_source', 'ai_generated');
    }
}
