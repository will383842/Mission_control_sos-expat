<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TypeVerificationFlag extends Model
{
    protected $fillable = [
        'influenceur_id', 'current_type', 'suggested_type',
        'reason', 'details', 'status', 'resolved_by',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function influenceur(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
