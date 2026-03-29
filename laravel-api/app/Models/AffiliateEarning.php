<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateEarning extends Model
{
    protected $fillable = [
        'affiliate_program_id', 'amount', 'currency',
        'type', 'description', 'reference', 'earned_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'earned_at'  => 'date',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class);
    }
}
