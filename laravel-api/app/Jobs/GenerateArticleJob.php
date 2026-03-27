<?php

namespace App\Jobs;

use App\Services\Content\ArticleGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public int $maxExceptions = 2;

    /**
     * Exponential backoff in seconds: 1min, then 5min.
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public array $params,
    ) {
        $this->onQueue('content');
    }

    public function handle(ArticleGenerationService $service): void
    {
        Log::info('GenerateArticleJob started', [
            'topic' => $this->params['topic'] ?? null,
            'language' => $this->params['language'] ?? null,
            'content_type' => $this->params['content_type'] ?? 'article',
        ]);

        $article = $service->generate($this->params);

        // Calculate and persist total generation cost
        $totalCost = \App\Models\ApiCost::where('costable_type', \App\Models\GeneratedArticle::class)
            ->where('costable_id', $article->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $article->update(['generation_cost_cents' => $totalCost]);
        }

        Log::info('GenerateArticleJob completed', [
            'id' => $article->id,
            'title' => $article->title,
            'word_count' => $article->word_count,
            'seo_score' => $article->seo_score,
            'cost_cents' => $article->generation_cost_cents,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateArticleJob failed', [
            'params' => $this->params,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Send Telegram alert if configured
        $botToken = config('services.telegram_alerts.bot_token');
        $chatId = config('services.telegram_alerts.chat_id');
        if ($botToken && $chatId) {
            try {
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "🚨 *Job Failed*: `" . class_basename(static::class) . "`\n" .
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
}
