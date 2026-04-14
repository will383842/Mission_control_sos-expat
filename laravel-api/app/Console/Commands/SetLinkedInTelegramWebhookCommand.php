<?php

namespace App\Console\Commands;

use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;

/**
 * One-time setup: register the Telegram webhook URL with the alert bot.
 *
 * Usage:
 *   php artisan linkedin:set-telegram-webhook
 *
 * Requires in .env:
 *   TELEGRAM_LINKEDIN_BOT_TOKEN=...   (dedicated LinkedIn bot — see @BotFather)
 *   TELEGRAM_LINKEDIN_CHAT_ID=...     (your chat ID, e.g. 7560535072)
 *   TELEGRAM_LINKEDIN_WEBHOOK_SECRET=<random string> (openssl rand -hex 32)
 *
 * The webhook URL is auto-built from APP_URL:
 *   {APP_URL}/api/telegram/linkedin
 */
class SetLinkedInTelegramWebhookCommand extends Command
{
    protected $signature   = 'linkedin:set-telegram-webhook {--delete : Remove the webhook}';
    protected $description = 'Register (or delete) the Telegram webhook for LinkedIn 1-tap confirm';

    public function __construct(private TelegramAlertService $telegram) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->telegram->isConfigured()) {
            $this->error('TELEGRAM_ALERT_BOT_TOKEN or TELEGRAM_ALERT_CHAT_ID not configured.');
            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $ok = $this->telegram->deleteWebhook();
            $this->info($ok ? '✓ Webhook deleted.' : '✗ Failed to delete webhook.');
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $appUrl = rtrim(config('app.url'), '/');
        $webhookUrl = $appUrl . '/api/telegram/linkedin';
        $secret = config('services.linkedin.telegram_webhook_secret', '');

        $this->info("Registering webhook: {$webhookUrl}");

        $ok = $this->telegram->setWebhook($webhookUrl, $secret ?: null);

        if ($ok) {
            $this->info('✓ Telegram webhook registered successfully.');
            $this->line("URL  : {$webhookUrl}");
            $this->line("Secret: " . ($secret ? '(set)' : '(none — add TELEGRAM_LINKEDIN_WEBHOOK_SECRET to .env for security)'));
        } else {
            $this->error('✗ Failed to register webhook. Check TELEGRAM_ALERT_BOT_TOKEN in .env.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
