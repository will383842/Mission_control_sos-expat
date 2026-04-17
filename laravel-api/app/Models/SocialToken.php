<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Multi-platform OAuth token.
 *
 * One row per (platform, account_type) pair. Examples:
 *   (linkedin, personal)   — w_member_social
 *   (linkedin, page)       — rw_organization_social
 *   (facebook, page)       — pages_manage_posts
 *   (instagram, business)  — instagram_content_publish
 *   (threads, personal)    — threads_content_publish
 *
 * access_token and refresh_token are AES-encrypted at rest.
 */
class SocialToken extends Model
{
    protected $table = 'social_tokens';

    protected $fillable = [
        'platform',
        'account_type',
        'access_token',
        'refresh_token',
        'expires_at',
        'refresh_token_expires_at',
        'platform_user_id',
        'platform_user_name',
        'scope',
        'metadata',
    ];

    protected $casts = [
        'expires_at'               => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'metadata'                 => 'array',
    ];

    // ── Encryption mutators ────────────────────────────────────────────

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        if (empty($this->access_token)) return false;
        if ($this->expires_at === null) return true; // long-lived tokens (Facebook)
        return $this->expires_at->isFuture();
    }

    public function expiresInDays(): ?int
    {
        if ($this->expires_at === null) return null;
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    public function refreshExpiresInDays(): ?int
    {
        if (!$this->refresh_token_expires_at) return null;
        return max(0, (int) now()->diffInDays($this->refresh_token_expires_at, false));
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeForAccountType($query, string $accountType)
    {
        return $query->where('account_type', $accountType);
    }

    /**
     * Fetch the token row for a (platform, account_type) pair.
     * Intentionally NOT named find() to avoid shadowing Eloquent's Model::find($id).
     */
    public static function lookup(string $platform, string $accountType): ?self
    {
        return static::query()
            ->where('platform', $platform)
            ->where('account_type', $accountType)
            ->first();
    }
}
