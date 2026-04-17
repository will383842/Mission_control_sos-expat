<?php

namespace App\Console\Commands;

use App\Jobs\PostSocialFirstCommentJob;
use App\Models\SocialPost;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Multi-platform auto-publish command — run every 5 minutes.
 *
 * Processes posts with status='scheduled' and scheduled_at <= now() for each
 * enabled platform.
 *
 * Two modes per platform (driven by services.{platform}.telegram_confirm):
 *
 *  auto mode (telegram_confirm=false):
 *    - Publish directly via the driver. status → published.
 *
 *  1-tap confirm mode (telegram_confirm=true):
 *    - Send preview + ✅/❌ buttons to platform's Telegram bot.
 *      status → pending_confirm. Tap ✅ → publish. Tap ❌ → reschedule +1 day.
 *
 * Usage:
 *   php artisan social:auto-publish
 *   php artisan social:auto-publish --platform=linkedin
 *   php artisan social:auto-publish --dry-run
 */
class AutoPublishSocialCommand extends Command
{
    protected $signature   = 'social:auto-publish
                               {--platform= : Only process this platform (default: all enabled)}
                               {--dry-run   : Show what would be processed without publishing}';
    protected $description = 'Auto-publish (or confirm via Telegram) scheduled social posts';

    public function __construct(private SocialDriverManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $platforms = $this->option('platform')
            ? [$this->option('platform')]
            : $this->manager->availablePlatforms();

        $dryRun = (bool) $this->option('dry-run');
        $totalProcessed = 0;

        foreach ($platforms as $platform) {
            if (!$this->manager->isEnabled($platform)) {
                $this->warn("Skipping {$platform} (disabled)");
                continue;
            }

            $count = $this->processPlatform($platform, $dryRun);
            $totalProcessed += $count;
        }

        if ($totalProcessed > 0) {
            $this->info("social:auto-publish: processed {$totalProcessed} posts across " . count($platforms) . " platform(s)");
        }

        return self::SUCCESS;
    }

    private function processPlatform(string $platform, bool $dryRun): int
    {
        $ready = SocialPost::forPlatform($platform)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->whereNull('platform_post_id')
            ->get();

        if ($ready->isEmpty()) return 0;

        $confirmMode = (bool) config("services.{$platform}.telegram_confirm", false);

        $this->line("  {$platform}: {$ready->count()} post(s) ready (confirm_mode=" . ($confirmMode ? 'on' : 'off') . ')');

        if ($dryRun) return $ready->count();

        foreach ($ready as $post) {
            if ($confirmMode) {
                $this->sendTelegramConfirm($platform, $post);
            } else {
                $this->publishNow($platform, $post);
            }
        }

        return $ready->count();
    }

    // ── Telegram 1-tap confirm mode ────────────────────────────────────

    private function sendTelegramConfirm(string $platform, SocialPost $post): void
    {
        $telegram = app(TelegramAlertService::class, ['bot' => $platform]);

        if (!$telegram->isConfigured()) {
            // Fallback to direct publish if Telegram not configured
            $this->publishNow($platform, $post);
            return;
        }

        $hook     = mb_substr($post->hook ?? '', 0, 120);
        $body     = mb_substr($post->body ?? '', 0, 200);
        $hashStr  = implode(' ', array_map(fn($h) => "#{$h}", array_slice($post->hashtags ?? [], 0, 3)));
        $account  = strtoupper($post->account_type ?: 'default');
        $lang     = strtoupper($post->lang ?? 'fr');
        $dayType  = ucfirst($post->day_type ?? '');
        $slot     = $post->scheduled_at?->format('H:i') ?? '';
        $platLbl  = ucfirst($platform);

        $preview = <<<TEXT
        📣 <b>{$platLbl} — Publication prête !</b>

        🕐 Programmé : <b>{$slot}</b> | {$dayType} | {$lang} | {$account}

        <b>{$hook}</b>

        {$body}...

        {$hashStr}
        TEXT;

        $buttons = [
            [
                ['text' => '✅ Publier maintenant',  'callback_data' => "social_{$platform}_confirm_{$post->id}"],
                ['text' => '❌ Repousser +1 jour',   'callback_data' => "social_{$platform}_skip_{$post->id}"],
            ],
        ];

        $msgId = $telegram->sendInlineKeyboard(trim($preview), $buttons);

        $post->update([
            'status'          => 'pending_confirm',
            'error_message'   => null,
            'telegram_msg_id' => $msgId,
        ]);

        Log::info("social:auto-publish: {$platform} Telegram confirm sent", [
            'post_id'    => $post->id,
            'message_id' => $msgId,
        ]);
    }

    // ── Direct publish mode ────────────────────────────────────────────

    private function publishNow(string $platform, SocialPost $post): void
    {
        $driver      = $this->manager->driver($platform);
        $accountType = $post->account_type ?: $driver->supportedAccountTypes()[0];

        if ($driver->requiresImage() && !$post->featured_image_url) {
            $this->failPost($post, "{$platform} requires a featured_image_url — skipping publish");
            return;
        }

        if (!$driver->isConfigured($accountType)) {
            $this->failPost($post, "Token {$platform} ({$accountType}) expired or not configured. Reconnect OAuth.");
            return;
        }

        try {
            $platformPostId = $driver->publish($post, $accountType);
            if (!$platformPostId) {
                $this->failPost($post, "{$platform} API returned no post id");
                return;
            }

            $post->update([
                'platform_post_id'     => $platformPostId,
                'status'               => 'published',
                'published_at'         => now(),
                'first_comment_status' => ($post->first_comment && $driver->supportsFirstComment())
                                            ? 'pending'
                                            : ($post->first_comment ? 'skipped' : null),
            ]);

            // Schedule first comment N seconds after publication
            if ($post->first_comment && $driver->supportsFirstComment()) {
                $delay = (int) config('social.auto_publish.first_comment_delay_seconds', 180);
                PostSocialFirstCommentJob::dispatch($post->id, $platform)
                    ->delay(now()->addSeconds($delay));
            }

            $this->notifySuccess($platform, $post, $platformPostId);

        } catch (\Throwable $e) {
            $this->failPost($post, $e->getMessage());
        }
    }

    // ── Notifications ──────────────────────────────────────────────────

    private function notifySuccess(string $platform, SocialPost $post, string $platformPostId): void
    {
        $telegram = app(TelegramAlertService::class, ['bot' => $platform]);
        if (!$telegram->isConfigured()) return;

        $hook     = mb_substr($post->hook ?? '', 0, 120);
        $day      = ucfirst($post->day_type ?? '');
        $lang     = strtoupper($post->lang ?? 'fr');
        $dateStr  = $post->scheduled_at?->format('D d M Y') ?? now()->format('D d M Y');
        $platLbl  = ucfirst($platform);
        $delayMin = (int) config('social.auto_publish.first_comment_delay_seconds', 180) / 60;

        $suffix = ($post->first_comment && $this->manager->driver($platform)->supportsFirstComment())
            ? "\n💬 1er commentaire dans {$delayMin} min"
            : '';

        $telegram->sendMessage(
            "✅ <b>{$platLbl} publié !</b>\n\n"
            . "<b>{$hook}</b>\n\n"
            . "🗓 {$dateStr} | {$day} | {$lang}\n"
            . "🔗 ID : <code>{$platformPostId}</code>"
            . $suffix
        );
    }

    private function failPost(SocialPost $post, string $reason): void
    {
        $post->update(['status' => 'failed', 'error_message' => $reason]);
        Log::error("social:auto-publish: {$post->platform} failed", ['post_id' => $post->id, 'reason' => $reason]);

        $telegram = app(TelegramAlertService::class, ['bot' => $post->platform]);
        if (!$telegram->isConfigured()) return;

        $hook    = mb_substr($post->hook ?? '(sans hook)', 0, 100);
        $day     = ucfirst($post->day_type ?? '');
        $dateStr = $post->scheduled_at?->format('D d M Y') ?? '';
        $platLbl = ucfirst($post->platform);

        $telegram->sendMessage(
            "❌ <b>{$platLbl} publication échouée</b>\n\n"
            . "Post #{$post->id} | {$day} | {$dateStr}\n"
            . "<i>{$hook}</i>\n\n"
            . "Erreur : <code>" . mb_substr($reason, 0, 300) . "</code>\n\n"
            . "→ Vérifier dans Mission Control : {$platLbl} > File d'attente"
        );
    }
}
