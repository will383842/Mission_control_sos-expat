<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticsDataPoint extends Model
{
    protected $fillable = [
        'indicator_id', 'indicator_code', 'indicator_name',
        'country_code', 'country_name', 'year', 'value', 'unit',
        'source', 'source_dataset', 'source_url', 'fetched_at',
    ];

    protected $casts = [
        'value'      => 'decimal:4',
        'year'       => 'integer',
        'fetched_at' => 'datetime',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(StatisticsIndicator::class, 'indicator_id');
    }
}
