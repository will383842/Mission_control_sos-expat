<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API wrapper — multi-bot support.
 *
 * Two configs available:
 *   'alerts'  → TELEGRAM_ALERT_BOT_TOKEN / TELEGRAM_ALERT_CHAT_ID   (general alerts)
 *   'linkedin'→ TELEGRAM_LINKEDIN_BOT_TOKEN / TELEGRAM_LINKEDIN_CHAT_ID (LinkedIn interactions)
 *
 * Usage:
 *   app(TelegramAlertService::class)                  → alerts bot (default)
 *   app(TelegramAlertService::class, ['bot' => 'linkedin']) → LinkedIn bot
 *
 * Features:
 *  - sendMessage()         → plain text message
 *  - sendInlineKeyboard()  → message with inline keyboard buttons
 *  - answerCallback()      → acknowledge a callback_query (dismiss loading spinner)
 *  - editMessageText()     → update a previously sent message (after button tap)
 *  - setWebhook()          → register webhook URL with Telegram
 *  - deleteWebhook()       → remove webhook
 */
class TelegramAlertService
{
    private string $token;
    private string $chatId;
    private string $apiBase;

    public function __construct(string $bot = 'alerts')
    {
        if ($bot === 'linkedin') {
            $this->token  = config('services.telegram_linkedin.bot_token',
                            config('services.telegram_alerts.bot_token', ''));
            $this->chatId = (string) config('services.telegram_linkedin.chat_id',
                            config('services.telegram_alerts.chat_id', ''));
        } else {
            $this->token  = config('services.telegram_alerts.bot_token', '');
            $this->chatId = (string) config('services.telegram_alerts.chat_id', '');
        }

        $this->apiBase = 'https://api.telegram.org/bot' . $this->token;
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->chatId !== '';
    }

    // ── Send plain text ────────────────────────────────────────────────

    public function sendMessage(string $text, ?string $chatId = null): bool
    {
        return $this->call('sendMessage', [
            'chat_id'    => $chatId ?? $this->chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    // ── Send with inline keyboard ──────────────────────────────────────

    /**
     * Send a message with inline keyboard buttons.
     *
     * $buttons = [
     *   [['text' => 'Btn 1', 'callback_data' => 'data_1'], ['text' => 'Btn 2', 'callback_data' => 'data_2']],
     *   [['text' => 'Single row btn', 'callback_data' => 'data_3']],
     * ]
     *
     * Returns the Telegram message_id (for later editing), or null on failure.
     */
    public function sendInlineKeyboard(string $text, array $buttons, ?string $chatId = null): ?int
    {
        try {
            $response = Http::timeout(15)->post($this->apiBase . '/sendMessage', [
                'chat_id'      => $chatId ?? $this->chatId,
                'text'         => $text,
                'parse_mode'   => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons,
                ]),
            ]);

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                return $response->json()['result']['message_id'] ?? null;
            }

            Log::warning('TelegramAlertService: sendInlineKeyboard failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('TelegramAlertService: sendInlineKeyboard exception', ['error' => $e->getMessage()]);
        }
        return null;
    }

    // ── Answer callback query (dismiss spinner) ────────────────────────

    public function answerCallback(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ]);
    }

    // ── Edit previously sent message ───────────────────────────────────

    public function editMessageText(int $messageId, string $text, ?string $chatId = null): bool
    {
        return $this->call('editMessageText', [
            'chat_id'    => $chatId ?? $this->chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    // ── Set webhook ────────────────────────────────────────────────────

    /**
     * Register webhook URL with Telegram.
     * $secretToken: optional X-Telegram-Bot-Api-Secret-Token header value.
     */
    public function setWebhook(string $url, ?string $secretToken = null): bool
    {
        $params = ['url' => $url, 'allowed_updates' => ['callback_query', 'message']];
        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }
        return $this->call('setWebhook', $params);
    }

    public function deleteWebhook(): bool
    {
        return $this->call('deleteWebhook', []);
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function call(string $method, array $params): bool
    {
        try {
            $response = Http::timeout(15)->post($this->apiBase . '/' . $method, $params);
            if (!$response->successful() || !($response->json()['ok'] ?? false)) {
                Log::warning("TelegramAlertService: {$method} failed", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error("TelegramAlertService: {$method} exception", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
