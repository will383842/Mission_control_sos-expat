<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentQuestion extends Model
{
    protected $table = 'content_questions';

    protected $fillable = [
        'source_id', 'title', 'url', 'url_hash',
        'country', 'country_slug', 'continent', 'city',
        'replies', 'views', 'is_sticky', 'is_closed',
        'last_post_date', 'last_post_author', 'language',
        'article_status', 'article_notes', 'scraped_at',
    ];

    protected $casts = [
        'replies'    => 'integer',
        'views'      => 'integer',
        'is_sticky'  => 'boolean',
        'is_closed'  => 'boolean',
        'scraped_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }
}
