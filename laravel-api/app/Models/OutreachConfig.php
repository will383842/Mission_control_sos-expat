<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachConfig extends Model
{
    protected $fillable = [
        'contact_type', 'auto_send', 'ai_generation_enabled',
        'max_steps', 'step_delays', 'daily_limit', 'is_active',
        'calendly_url', 'calendly_step', 'custom_prompt', 'from_name',
    ];

    protected $casts = [
        'auto_send'              => 'boolean',
        'ai_generation_enabled'  => 'boolean',
        'is_active'              => 'boolean',
        'step_delays'            => 'array',
    ];

    /**
     * Get config for a contact type, or return defaults.
     */
    public static function getFor(string $contactType): self
    {
        return static::firstOrCreate(
            ['contact_type' => $contactType],
            [
                'auto_send'             => false,
                'ai_generation_enabled' => true,
                'max_steps'             => 4,
                'step_delays'           => [0, 3, 7, 14],
                'daily_limit'           => 50,
                'is_active'             => true,
            ]
        );
    }

    /**
     * Get delay in days before sending step N.
     */
    public function getStepDelay(int $step): int
    {
        $delays = $this->step_delays ?? [0, 3, 7, 14];
        return $delays[$step - 1] ?? 14;
    }
}
