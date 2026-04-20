<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBusiness extends Model
{
    protected $table = 'content_businesses';

    protected $fillable = [
        'source_id', 'external_id', 'name', 'slug', 'url', 'url_hash',
        'contact_name', 'contact_email', 'contact_phone', 'website', 'website_redirect',
        'country', 'country_slug', 'continent', 'region', 'city', 'address',
        'latitude', 'longitude',
        'category', 'category_slug', 'category_id',
        'subcategory', 'subcategory_slug', 'subcategory_id',
        'description', 'logo_url', 'images', 'opening_hours',
        'recommendations', 'views', 'is_premium', 'schema_type',
        'language', 'detail_scraped', 'scraped_at', 'backlink_synced_at',
    ];

    protected $casts = [
        'external_id'        => 'integer',
        'latitude'           => 'decimal:7',
        'longitude'          => 'decimal:7',
        'category_id'        => 'integer',
        'subcategory_id'     => 'integer',
        'images'             => 'array',
        'opening_hours'      => 'array',
        'recommendations'    => 'integer',
        'views'              => 'integer',
        'is_premium'         => 'boolean',
        'detail_scraped'     => 'boolean',
        'scraped_at'         => 'datetime',
        'backlink_synced_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }
}
