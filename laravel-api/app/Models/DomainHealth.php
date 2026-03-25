<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainHealth extends Model
{
    protected $table = 'domain_health';

    protected $fillable = [
        'domain', 'total_sent', 'total_delivered', 'total_bounced',
        'total_complained', 'bounce_rate', 'complaint_rate',
        'is_blacklisted', 'is_paused', 'last_checked_at',
    ];

    protected $casts = [
        'is_blacklisted'  => 'boolean',
        'is_paused'       => 'boolean',
        'last_checked_at' => 'datetime',
        'bounce_rate'     => 'float',
        'complaint_rate'  => 'float',
    ];
}
