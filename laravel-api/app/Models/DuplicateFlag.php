<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateFlag extends Model
{
    protected $fillable = [
        'influenceur_a_id', 'influenceur_b_id', 'match_type',
        'confidence', 'status', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function influenceurA(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class, 'influenceur_a_id');
    }

    public function influenceurB(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class, 'influenceur_b_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
