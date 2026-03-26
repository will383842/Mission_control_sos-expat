<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LawyerDirectorySource extends Model
{
    protected $table = 'lawyer_directory_sources';

    protected $fillable = [
        'slug', 'name', 'base_url', 'country_scope', 'language_scope',
        'status', 'total_lawyers', 'total_with_email',
        'robots_txt_ok', 'notes', 'last_scraped_at',
    ];

    protected $casts = [
        'total_lawyers'    => 'integer',
        'total_with_email' => 'integer',
        'robots_txt_ok'    => 'boolean',
        'last_scraped_at'  => 'datetime',
    ];

    public function lawyers()
    {
        return $this->hasMany(Lawyer::class, 'source_slug', 'slug');
    }

    public function updateStats(): void
    {
        $this->update([
            'total_lawyers'    => Lawyer::where('source_slug', $this->slug)->count(),
            'total_with_email' => Lawyer::where('source_slug', $this->slug)
                ->whereNotNull('email')->where('email', '!=', '')->count(),
        ]);
    }
}
