<?php

namespace App\Services\Social;

use App\Models\SocialToken;
use App\Services\Social\Contracts\SocialPublishingServiceInterface;
use App\Services\Social\TelegramAlertService;
use Illuminate\Support\Facades\Log;

/**
 * Base class for every SocialPublishingServiceInterface implementation.
 *
 * Provides shared logic that is identical across platforms:
 *   - token lookup by (platform, account_type)
 *   - generic getTokenStatus() that loops over supportedAccountTypes()
 *   - Telegram alerts for expired / revoked tokens
 *   - sensible default capability flags (drivers override what they need)
 */
abstract class AbstractSocialDriver implements SocialPublishingServiceInterface
{
    public function isConfigured(?string $accountType = null): bool
    {
        if ($accountType === null) {
            foreach ($this->supportedAccountTypes() as $type) {
                if ($this->isConfigured($type)) return true;
            }
            return false;
        }

        $token = $this->findToken($accountType);
        return $token && $token->isValid();
    }

    public function getTokenStatus(): array
    {
        $out = [];
        foreach ($this->supportedAccountTypes() as $type) {
            $token = $this->findToken($type);
            $out[$type] = [
                'connected'               => $token?->isValid() ?? false,
                'name'                    => $token?->platform_user_name,
                'expires_in_days'         => $token?->expiresInDays(),
                'has_refresh_token'       => !empty($token?->refresh_token),
                'refresh_expires_in_days' => $token?->refreshExpiresInDays(),
            ];
        }
        return $out;
    }

    // ── Default capability flags (override when needed) ────────────────

    public function supportsFirstComment(): bool { return true; }
    public function supportsHashtags(): bool     { return true; }
    public function requiresImage(): bool        { return false; }
    public function maxContentLength(): int      { return 3000; }
    public function supportedAccountTypes(): array { return ['personal']; }

    // ── Helpers for concrete drivers ───────────────────────────────────

    protected function findToken(string $accountType): ?SocialToken
    {
        return SocialToken::lookup($this->platform(), $accountType);
    }

    /** Default account_type when the caller did not pass one. */
    protected function defaultAccountType(): string
    {
        return $this->supportedAccountTypes()[0] ?? 'personal';
    }

    protected function resolveAccountType(?string $accountType): string
    {
        return $accountType ?: $this->defaultAccountType();
    }

    /** Send a Telegram alert when a token is dead and manual reconnection is required. */
    protected function notifyTokenExpired(string $accountType, int $httpStatus = 0): void
    {
        try {
            // Route the alert through the platform's dedicated Telegram bot
            // (falls back to the general alerts bot if the dedicated one is not configured).
            $telegram = app(TelegramAlertService::class, ['bot' => $this->platform()]);
            if (!$telegram->isConfigured()) return;

            $platform = ucfirst($this->platform());
            $errInfo  = $httpStatus ? " (HTTP {$httpStatus})" : '';

            $telegram->sendMessage(
                "🔴 <b>{$platform} déconnecté — action requise</b>\n\n"
                . "Le token <b>{$accountType}</b> a expiré ou été révoqué{$errInfo}.\n\n"
                . "Les publications sont <b>suspendues</b> jusqu'à reconnexion.\n\n"
                . "→ Reconnecte-toi depuis Mission Control :\n"
                . "<b>{$platform} → ⚙️ Gérer la connexion → 🔄 Reconnecter</b>"
            );
        } catch (\Throwable) {
            // never let notification failure break the publish pipeline
        }
    }

    /** Log-and-return-null helper used by drivers. */
    protected function logError(string $method, array $context = []): void
    {
        Log::error(static::class . ": {$method}", $context + ['platform' => $this->platform()]);
    }

    /** Default analytics = empty (drivers implement their own) */
    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array
    {
        return [];
    }
}
