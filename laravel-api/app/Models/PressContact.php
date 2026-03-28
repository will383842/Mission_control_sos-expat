<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PressContact extends Model
{
    protected $fillable = [
        'publication_id', 'first_name', 'last_name', 'full_name',
        'email', 'email_verified', 'phone',
        'publication', 'role', 'beat', 'media_type',
        'source_url', 'profile_url', 'linkedin', 'twitter',
        'country', 'city', 'language', 'topics',
        'contact_status', 'last_contacted_at', 'notes',
        'scraped_from', 'scraped_at',
    ];

    protected $casts = [
        'topics' => 'array',
        'email_verified' => 'boolean',
        'scraped_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    public function pressPublication(): BelongsTo
    {
        return $this->belongsTo(PressPublication::class, 'publication_id');
    }
}
