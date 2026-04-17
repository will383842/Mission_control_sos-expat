<?php

namespace App\Console\Commands;

use App\Models\SocialToken;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;

/**
 * Daily token health check for every enabled platform — runs at 08:00 UTC.
 *
 * Sends ONE Telegram alert per (platform, account_type) when reconnection is
 * truly needed (expired OR expiring within 7 days with no refresh token).
 * Uses the platform's dedicated Telegram bot (falls back to alerts bot).
 */
class CheckSocialTokensCommand extends Command
{
    protected $signature   = 'social:check-tokens
                               {--platform= : Only this platform (default: all enabled)}';
    protected $description = 'Alert via Telegram when a social platform token needs manual reconnection';

    public function __construct(private SocialDriverManager $manager) {
        parent::__construct();
    }

    public function handle(): int
    {
        $platforms = $this->option('platform')
            ? [$this->option('platform')]
            : $this->manager->availablePlatforms();

        foreach ($platforms as $platform) {
            if (!$this->manager->isEnabled($platform)) continue;
            $this->checkPlatform($platform);
        }

        return self::SUCCESS;
    }

    private function checkPlatform(string $platform): void
    {
        $driver   = $this->manager->driver($platform);
        $telegram = app(TelegramAlertService::class, ['bot' => $platform]);

        foreach ($driver->supportedAccountTypes() as $accountType) {
            $token = SocialToken::lookup($platform, $accountType);
            if (!$token) continue;

            $daysLeft   = $token->expiresInDays();
            $hasRefresh = !empty($token->refresh_token);

            // Long-lived (null expires_at) tokens never trigger — they have no hard expiry
            if ($daysLeft === null && $token->isValid()) continue;

            $needsReconnect = !$token->isValid()
                || (!$hasRefresh && $daysLeft !== null && $daysLeft <= 7);

            if (!$needsReconnect) continue;
            if (!$telegram->isConfigured()) continue;

            $reason = !$token->isValid()
                ? 'Le token a expiré.'
                : "Le token expire dans <b>{$daysLeft} jour(s)</b> et ne peut pas se renouveler automatiquement.";

            $platLbl = ucfirst($platform);
            $telegram->sendMessage(
                "🔴 <b>{$platLbl} — reconnexion requise ({$accountType})</b>\n\n"
                . "{$reason}\n\n"
                . "Les publications seront suspendues sans action.\n\n"
                . "→ <b>Mission Control → {$platLbl} → ⚙️ Gérer → 🔄 Reconnecter</b>"
            );

            $this->warn("{$platform} ({$accountType}): {$reason}");
        }
    }
}
