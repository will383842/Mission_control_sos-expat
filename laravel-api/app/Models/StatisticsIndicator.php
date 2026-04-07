<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatisticsIndicator extends Model
{
    protected $fillable = [
        'code', 'name', 'source', 'theme', 'unit',
        'description', 'api_endpoint', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dataPoints(): HasMany
    {
        return $this->hasMany(StatisticsDataPoint::class, 'indicator_id');
    }
}
