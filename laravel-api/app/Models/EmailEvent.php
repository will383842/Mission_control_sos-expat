<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
    protected $fillable = [
        'outreach_email_id', 'event_type', 'metadata',
        'ip_address', 'user_agent', 'occurred_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    public function outreachEmail(): BelongsTo
    {
        return $this->belongsTo(OutreachEmail::class);
    }
}
