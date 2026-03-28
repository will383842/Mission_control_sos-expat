<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PressPublication extends Model
{
    protected $fillable = [
        'name', 'slug', 'base_url', 'team_url', 'contact_url',
        'media_type', 'topics', 'language', 'country',
        'contacts_count', 'status', 'last_error', 'last_scraped_at',
    ];

    protected $casts = [
        'topics' => 'array',
        'last_scraped_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(PressContact::class, 'publication_id');
    }
}
