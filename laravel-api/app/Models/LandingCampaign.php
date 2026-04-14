<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LandingCampaign extends Model
{
    public const VALID_TYPES = [
        'clients', 'lawyers', 'helpers', 'matching',
        'category_pillar', 'profile', 'emergency', 'nationality',
    ];

    public const DEFAULT_TEMPLATES = [
        'clients'         => ['urgent', 'seo', 'trust'],
        'lawyers'         => ['general', 'urgent', 'freedom', 'income', 'premium'],
        'helpers'         => ['recruitment', 'opportunity', 'reassurance'],
        'matching'        => ['expert', 'lawyer', 'helper'],
        'category_pillar' => ['overview', 'guide'],
        'profile'         => ['profile_general', 'profile_guide'],
        'emergency'       => ['emergency'],
        'nationality'     => ['nationality_general'],
    ];

    protected $fillable = [
        'audience_type',
        'country_queue',
        'current_country',
        'pages_per_country',
        'daily_limit',
        'selected_templates',
        'problem_filters',
        'status',
        'total_generated',
        'total_cost_cents',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'country_queue'      => 'array',
        'selected_templates' => 'array',
        'problem_filters'    => 'array',
        'total_generated'    => 'integer',
        'total_cost_cents'   => 'integer',
        'daily_limit'        => 'integer',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
    ];

    // ============================================================
    // Static helpers
    // ============================================================

    /**
     * Récupère ou crée la campagne pour un audience_type donné.
     * Initialise avec les templates par défaut si nouvelle.
     */
    public static function findOrCreateForType(string $type): self
    {
        return static::firstOrCreate(
            ['audience_type' => $type],
            [
                'country_queue'      => [],
                'selected_templates' => self::DEFAULT_TEMPLATES[$type] ?? [],
                'pages_per_country'  => 10,
                'daily_limit'        => 0,
                'status'             => 'idle',
                'total_generated'    => 0,
                'total_cost_cents'   => 0,
            ]
        );
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getProgressPercentAttribute(): int
    {
        $queue = $this->country_queue ?? [];
        $total = count($queue);
        if ($total === 0) {
            return 0;
        }

        $completed = LandingPage::where('audience_type', $this->audience_type)
            ->where('generation_source', 'ai_generated')
            ->whereIn('country_code', $queue)
            ->groupBy('country_code')
            ->havingRaw('COUNT(*) >= ?', [$this->pages_per_country])
            ->count();

        return (int) round(($completed / $total) * 100);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }
}
