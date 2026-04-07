<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatisticsDataset extends Model
{
    protected $fillable = [
        'topic',
        'theme',
        'country_code',
        'country_name',
        'title',
        'summary',
        'stats',
        'sources',
        'analysis',
        'confidence_score',
        'source_count',
        'status',
        'language',
        'generated_article_id',
        'last_researched_at',
    ];

    protected $casts = [
        'stats'              => 'array',
        'sources'            => 'array',
        'analysis'           => 'array',
        'confidence_score'   => 'integer',
        'source_count'       => 'integer',
        'last_researched_at' => 'datetime',
    ];

    // ── Themes disponibles ──────────────────────────────────
    public const THEMES = [
        'expatries'      => 'Expatriates',
        'voyageurs'      => 'Travelers',
        'nomades'        => 'Digital Nomads',
        'etudiants'      => 'International Students',
        'investisseurs'  => 'Foreign Investors',
    ];

    // ── Scopes ──────────────────────────────────────────────
    public function scopeByTheme($query, string $theme)
    {
        return $query->where('theme', $theme);
    }

    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
