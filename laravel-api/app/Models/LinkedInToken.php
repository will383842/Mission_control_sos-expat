<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores LinkedIn OAuth tokens.
 *
 * account_type: 'personal' (w_member_social) | 'page' (rw_organization_social)
 * access_token and refresh_token are AES-encrypted at rest.
 * expires_at: 60 days from issue for access_token.
 */
class LinkedInToken extends Model
{
    protected $fillable = [
        'account_type',
        'access_token',
        'refresh_token',
        'expires_at',
        'linkedin_id',
        'linkedin_name',
        'scope',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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
        return $this->expires_at && $this->expires_at->isFuture() && !empty($this->access_token);
    }

    public function expiresInDays(): int
    {
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForPersonal($query)
    {
        return $query->where('account_type', 'personal');
    }

    public function scopeForPage($query)
    {
        return $query->where('account_type', 'page');
    }
}
