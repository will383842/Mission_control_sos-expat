<?php

namespace App\Console\Commands;

use App\Models\LinkedInToken;
use App\Services\Social\LinkedInApiService;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily token health check — runs at 08:00 UTC.
 *
 * Alerts via Telegram when:
 *  - Token expires within 14 days AND no refresh_token (manual reconnect needed)
 *  - Token expires within 3 days even with a refresh_token (refresh may have failed)
 *  - Refresh token itself expires within 30 days
 *  - Token already expired (should never happen normally)
 */
class CheckLinkedInTokenCommand extends Command
{
    protected $signature   = 'linkedin:check-token';
    protected $description = 'Check LinkedIn token health and alert via Telegram if action needed';

    public function __construct(
        private LinkedInApiService  $api,
        private TelegramAlertService $telegram,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach (['personal', 'page'] as $accountType) {
            $token = LinkedInToken::where('account_type', $accountType)->first();
            if (!$token) continue;

            $label          = $accountType === 'personal' ? 'Profil personnel' : 'Page SOS-Expat';
            $daysLeft       = $token->expiresInDays();
            $hasRefresh     = !empty($token->refresh_token);
            $refreshDaysLeft = $token->refreshExpiresInDays();

            // ── Already expired ────────────────────────────────────────
            if (!$token->isValid()) {
                $this->warn("{$label}: token EXPIRÉ");
                $this->api->notifyTokenExpired($accountType);
                continue;
            }

            // ── Expires within 3 days — even with refresh token, something's wrong
            if ($daysLeft <= 3) {
                $this->warn("{$label}: expire dans {$daysLeft}j — refresh non déclenché ?");
                if ($this->telegram->isConfigured()) {
                    $this->telegram->sendMessage(
                        "⚠️ <b>LinkedIn token critique — {$label}</b>\n\n"
                        . "Expire dans <b>{$daysLeft} jour(s)</b>.\n"
                        . ($hasRefresh
                            ? "Un refresh token existe mais le renouvellement automatique n'a pas fonctionné.\n→ Vérifie les logs Laravel ou reconnecte manuellement."
                            : "Aucun refresh token — reconnexion manuelle requise.\n→ Mission Control → LinkedIn → 🔄 Reconnecter")
                    );
                }
                continue;
            }

            // ── No refresh token + expires within 14 days — manual reconnect needed soon
            if (!$hasRefresh && $daysLeft <= 14) {
                $this->warn("{$label}: expire dans {$daysLeft}j, pas de refresh token");
                if ($this->telegram->isConfigured()) {
                    $this->telegram->sendMessage(
                        "⚠️ <b>LinkedIn — reconnexion requise dans {$daysLeft}j</b>\n\n"
                        . "Le token <b>{$label}</b> expire dans <b>{$daysLeft} jours</b> "
                        . "et il n'y a pas de refresh token pour le renouveler automatiquement.\n\n"
                        . "→ Reconnecte-toi dès que possible :\n"
                        . "<b>Mission Control → LinkedIn → ⚙️ Gérer → 🔄 Reconnecter</b>"
                    );
                }
                continue;
            }

            // ── Refresh token itself expires within 30 days ────────────
            if ($hasRefresh && $refreshDaysLeft !== null && $refreshDaysLeft <= 30) {
                $this->warn("{$label}: refresh token expire dans {$refreshDaysLeft}j");
                if ($this->telegram->isConfigured()) {
                    $this->telegram->sendMessage(
                        "⚠️ <b>LinkedIn — refresh token expire dans {$refreshDaysLeft}j</b>\n\n"
                        . "Le refresh token <b>{$label}</b> expire dans <b>{$refreshDaysLeft} jours</b>.\n"
                        . "Une reconnexion manuelle sera nécessaire sous peu.\n\n"
                        . "→ <b>Mission Control → LinkedIn → ⚙️ Gérer → 🔄 Reconnecter</b>"
                    );
                }
                continue;
            }

            // ── All good ───────────────────────────────────────────────
            $statusLine = $hasRefresh
                ? "✅ Auto-renouvelable (refresh dans ~{$refreshDaysLeft}j)"
                : "✅ Valide {$daysLeft}j (sans auto-renouvellement)";
            $this->info("{$label}: {$statusLine}");

            Log::info('linkedin:check-token: OK', [
                'account_type'    => $accountType,
                'expires_in_days' => $daysLeft,
                'has_refresh'     => $hasRefresh,
            ]);
        }

        return self::SUCCESS;
    }
}
