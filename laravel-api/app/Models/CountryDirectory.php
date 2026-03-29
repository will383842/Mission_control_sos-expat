<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CountryDirectory extends Model
{
    protected $table = 'country_directory';

    protected $fillable = [
        'country_code', 'country_name', 'country_slug', 'continent',
        'nationality_code', 'nationality_name',
        'category', 'sub_category',
        'title', 'url', 'domain', 'description', 'language', 'translations',
        'address', 'city', 'phone', 'phone_emergency', 'email', 'opening_hours',
        'latitude', 'longitude',
        'emergency_number',
        'trust_score', 'is_official', 'is_active',
        'anchor_text', 'rel_attribute',
    ];

    protected function casts(): array
    {
        return [
            'is_official'  => 'boolean',
            'is_active'    => 'boolean',
            'trust_score'  => 'integer',
            'latitude'     => 'decimal:7',
            'longitude'    => 'decimal:7',
            'translations' => 'array',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeCountry(Builder $query, string $code): Builder
    {
        return $query->where('country_code', strtoupper($code));
    }

    public function scopeNationality(Builder $query, string $code): Builder
    {
        return $query->where('nationality_code', strtoupper($code));
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

    // ── Accesseurs ─────────────────────────────────────────────────────────────

    /**
     * Retourne le titre dans la langue demandée (fallback sur le titre principal).
     */
    public function getTitle(string $lang = 'fr'): string
    {
        if ($lang !== 'fr' && is_array($this->translations) && isset($this->translations[$lang]['title'])) {
            return $this->translations[$lang]['title'];
        }
        return $this->title;
    }

    /**
     * Retourne la description dans la langue demandée (fallback sur la description principale).
     */
    public function getDescription(string $lang = 'fr'): ?string
    {
        if ($lang !== 'fr' && is_array($this->translations) && isset($this->translations[$lang]['description'])) {
            return $this->translations[$lang]['description'];
        }
        return $this->description;
    }

    // ── Helpers statiques ──────────────────────────────────────────────────────

    /**
     * Toutes les entrées d'un pays, groupées par catégorie.
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
     * L'ambassade d'une nationalité donnée dans un pays hôte.
     * Ex : ambassade allemande en Thaïlande → nationalityCode='DE', hostCountryCode='TH'
     */
    public static function embassiesFor(string $nationalityCode, string $hostCountryCode): array
    {
        return static::active()
            ->where('nationality_code', strtoupper($nationalityCode))
            ->where('country_code', strtoupper($hostCountryCode))
            ->where('category', 'ambassade')
            ->orderByDesc('trust_score')
            ->get()
            ->toArray();
    }

    /**
     * Liens externes haute-confiance pour la génération d'articles IA.
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

        return $query->get()->map(fn ($e) => [
            'url'         => $e->url,
            'title'       => $e->title,
            'domain'      => $e->domain,
            'trust_score' => $e->trust_score,
            'anchor_text' => $e->anchor_text ?? $e->title,
            'rel'         => $e->rel_attribute,
        ])->toArray();
    }
}
