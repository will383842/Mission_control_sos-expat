<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CountryDirectory extends Model
{
    protected $table = 'country_directory';

    protected $fillable = [
        'country_code', 'country_name', 'country_slug', 'continent',
        'category', 'sub_category',
        'title', 'url', 'domain', 'description', 'language',
        'address', 'city', 'phone', 'phone_emergency', 'email', 'opening_hours',
        'latitude', 'longitude',
        'emergency_number',
        'trust_score', 'is_official', 'is_active',
        'anchor_text', 'rel_attribute',
    ];

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_active' => 'boolean',
            'trust_score' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeCountry(Builder $query, string $code): Builder
    {
        return $query->where('country_code', strtoupper($code));
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    public function scopeTrusted(Builder $query, int $minScore = 80): Builder
    {
        return $query->where('trust_score', '>=', $minScore);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all directory entries for a country, grouped by category.
     */
    public static function forCountry(string $countryCode): array
    {
        return static::active()
            ->country($countryCode)
            ->orderBy('category')
            ->orderByDesc('trust_score')
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get external links suitable for article generation (high trust, official).
     */
    public static function linksForArticle(string $countryCode, ?string $category = null, int $limit = 5): array
    {
        $query = static::active()
            ->country($countryCode)
            ->trusted(75)
            ->orderByDesc('trust_score')
            ->limit($limit);

        if ($category) {
            $query->category($category);
        }

        return $query->get()->map(fn ($entry) => [
            'url' => $entry->url,
            'title' => $entry->title,
            'domain' => $entry->domain,
            'trust_score' => $entry->trust_score,
            'anchor_text' => $entry->anchor_text ?? $entry->title,
            'rel' => $entry->rel_attribute,
        ])->toArray();
    }
}
