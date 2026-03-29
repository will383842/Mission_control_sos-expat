<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProgram extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description',
        'website_url', 'affiliate_dashboard_url', 'affiliate_signup_url',
        'my_affiliate_link', 'commission_type', 'commission_info',
        'cookie_duration_days', 'payout_threshold', 'payout_method',
        'payout_frequency', 'current_balance', 'total_earned',
        'last_payout_amount', 'last_payout_at', 'status', 'network',
        'logo_url', 'notes', 'sort_order',
    ];

    protected $casts = [
        'payout_threshold'   => 'decimal:2',
        'current_balance'    => 'decimal:2',
        'total_earned'       => 'decimal:2',
        'last_payout_amount' => 'decimal:2',
        'last_payout_at'     => 'date',
        'cookie_duration_days' => 'integer',
        'sort_order'         => 'integer',
    ];

    public function earnings(): HasMany
    {
        return $this->hasMany(AffiliateEarning::class);
    }

    public function needsPayout(): bool
    {
        if ($this->payout_threshold === null) return false;
        return (float) $this->current_balance >= (float) $this->payout_threshold;
    }
}
