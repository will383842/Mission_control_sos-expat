<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiPrompt extends Model
{
    protected $fillable = ['contact_type', 'prompt_template', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Get prompt for a contact type (cached 10 min).
     * Falls back to hardcoded default if not in DB.
     */
    public static function getFor(string $contactType): ?string
    {
        return Cache::remember("ai_prompt_{$contactType}", 600, function () use ($contactType) {
            return self::where('contact_type', $contactType)
                ->where('is_active', true)
                ->value('prompt_template');
        });
    }

    public static function flushCache(?string $contactType = null): void
    {
        if ($contactType) {
            Cache::forget("ai_prompt_{$contactType}");
        } else {
            // Flush all — get all types from DB
            self::pluck('contact_type')->each(fn ($t) => Cache::forget("ai_prompt_{$t}"));
        }
    }
}
