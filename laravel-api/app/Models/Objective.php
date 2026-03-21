<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Objective extends Model
{
    protected $fillable = [
        'user_id', 'continent', 'countries', 'language', 'niche',
        'target_count', 'deadline', 'is_active', 'created_by',
    ];

    protected $casts = [
        'deadline'     => 'date',
        'is_active'    => 'boolean',
        'target_count' => 'integer',
        'countries'    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: only active objectives.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
