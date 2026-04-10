<?php

namespace App\Jobs;

use App\Models\RssFeedItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunNewsGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    public function handle(): array
    {
        // ── Quota journalier ──
        $quotaInfo    = $this->getQuotaInfo();
        $quotaLimit   = $quotaInfo['quota'];
        $generatedToday = $quotaInfo['generated_today'];
        $remaining    = max(0, $quotaLimit - $generatedToday);

        if ($remaining <= 0) {
            Log::info('RunNewsGenerationJob: quota journalier atteint, aucun dispatch');
            return ['dispatched' => 0, 'remaining_quota' => 0];
        }

        // ── Récupérer les items pending pertinents ──
        $items = RssFeedItem::where('status', 'pending')
            ->whereNotNull('relevance_score')
            ->orderByDesc('relevance_score')
            ->orderByDesc('published_at')
            ->limit($remaining)
            ->get();

        $dispatched = 0;
        $seenTitles = [];

        foreach ($items as $item) {
            // Dedup: skip items with same title (same article from different RSS feeds)
            $normalizedTitle = mb_strtolower(trim($item->title));
            if (isset($seenTitles[$normalizedTitle])) {
                $item->update(['status' => 'skipped', 'error_message' => 'Duplicate title from another feed']);
                continue;
            }
            $seenTitles[$normalizedTitle] = true;

            GenerateNewsArticleJob::dispatch($item->id);
            $dispatched++;
        }

        Log::info("RunNewsGenerationJob: {$dispatched} jobs dispatchés, quota restant: " . ($remaining - $dispatched));

        return [
            'dispatched'      => $dispatched,
            'remaining_quota' => $remaining - $dispatched,
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunNewsGenerationJob failed permanently', [
            'error' => $e->getMessage(),
        ]);

        $botToken = config('services.telegram_alerts.bot_token');
        $chatId = config('services.telegram_alerts.chat_id');
        if ($botToken && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "🚨 *Job Failed*: `RunNewsGenerationJob`\n" .
                              "Error: " . mb_substr($e->getMessage(), 0, 500) . "\n" .
                              "Time: " . now()->toDateTimeString(),
                ]);
            } catch (\Throwable $tgError) {
                Log::warning('Failed to send Telegram alert', [
                    'error' => $tgError->getMessage(),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────
    // QUOTA
    // ─────────────────────────────────────────

    private function getQuotaInfo(): array
    {
        try {
            $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
            $quota = $raw ? json_decode($raw, true) : [];

            $quotaLimit     = (int) ($quota['quota'] ?? 15);
            $generatedToday = (int) ($quota['generated_today'] ?? 0);
            $lastResetDate  = $quota['last_reset_date'] ?? '';
            $today          = now()->toDateString();

            // Reset si nouveau jour
            if ($lastResetDate !== $today) {
                $generatedToday = 0;
                // Persister le reset
                $quota['generated_today'] = 0;
                $quota['last_reset_date'] = $today;
                $quota['quota']           = $quotaLimit;
                DB::table('settings')->updateOrInsert(
                    ['key' => 'news_daily_quota'],
                    ['value' => json_encode($quota), 'updated_at' => now()]
                );
            }

            return ['quota' => $quotaLimit, 'generated_today' => $generatedToday];

        } catch (\Throwable $e) {
            Log::warning('RunNewsGenerationJob: erreur lecture quota', ['error' => $e->getMessage()]);
            return ['quota' => 15, 'generated_today' => 0];
        }
    }
}
