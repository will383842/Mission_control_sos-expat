<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentContact extends Model
{
    protected $table = 'content_contacts';

    protected $fillable = [
        'source_id', 'name', 'role', 'email', 'phone',
        'company', 'company_url', 'linkedin',
        'country', 'city', 'address', 'sector',
        'notes', 'page_url', 'language', 'scraped_at', 'backlink_synced_at',
    ];

    protected $casts = [
        'scraped_at'         => 'datetime',
        'backlink_synced_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }
}
