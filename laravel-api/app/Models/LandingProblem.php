<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LandingProblem extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'category',
        'subcategory',
        'intent',
        'urgency_score',
        'business_value',
        'product_route',
        'needs_lawyer',
        'needs_helper',
        'monetizable',
        'lp_angle',
        'faq_seed',
        'search_queries_seed',
        'user_profiles',
        'tags',
        'status',
    ];

    protected $casts = [
        'urgency_score'       => 'integer',
        'needs_lawyer'        => 'boolean',
        'needs_helper'        => 'boolean',
        'monetizable'         => 'boolean',
        'search_queries_seed' => 'array',
        'user_profiles'       => 'array',
        'tags'                => 'array',
    ];

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeHighValue(Builder $query): Builder
    {
        return $query->where('business_value', 'high');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForLawyers(Builder $query): Builder
    {
        return $query->where('needs_lawyer', true);
    }

    public function scopeForHelpers(Builder $query): Builder
    {
        return $query->where('needs_helper', true);
    }

    public function scopeMinUrgency(Builder $query, int $min): Builder
    {
        return $query->where('urgency_score', '>=', $min);
    }

    public function scopeByBusinessValue(Builder $query, array $values): Builder
    {
        return $query->whereIn('business_value', $values);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        // business_value sémantique : high > mid > low (pas alphabétique)
        return $query
            ->orderByDesc('urgency_score')
            ->orderByRaw("CASE business_value WHEN 'high' THEN 1 WHEN 'mid' THEN 2 ELSE 3 END");
    }
}
