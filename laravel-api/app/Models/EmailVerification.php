<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    protected $fillable = [
        'influenceur_id', 'email', 'mx_valid', 'mx_domain',
        'smtp_valid', 'smtp_response', 'status', 'checked_at',
    ];

    protected $casts = [
        'mx_valid'   => 'boolean',
        'smtp_valid'  => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function influenceur(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class);
    }
}
