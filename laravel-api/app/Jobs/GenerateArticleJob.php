<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
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

        // ── Auto-publish to default endpoint (blog sos-expat.com) ──
        // Skip if this is a translation (parent publishes all translations together)
        if (!$article->parent_article_id) {
            $this->autoPublish($article);
        }

        Log::info('GenerateArticleJob completed', [
            'id' => $article->id,
            'title' => $article->title,
            'word_count' => $article->word_count,
            'seo_score' => $article->seo_score,
            'cost_cents' => $article->generation_cost_cents,
            'auto_published' => !$article->parent_article_id,
        ]);
    }

    /**
     * Auto-publish article to the default publishing endpoint.
     * Creates a PublicationQueueItem and dispatches PublishContentJob.
     */
    private function autoPublish(GeneratedArticle $article): void
    {
        try {
            $endpoint = PublishingEndpoint::where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (!$endpoint) {
                Log::warning('GenerateArticleJob: no default publishing endpoint, skipping auto-publish', [
                    'article_id' => $article->id,
                ]);
                return;
            }

            // Avoid duplicate publication
            $alreadyQueued = PublicationQueueItem::where('publishable_type', GeneratedArticle::class)
                ->where('publishable_id', $article->id)
                ->whereIn('status', ['pending', 'published', 'scheduled'])
                ->exists();

            if ($alreadyQueued) {
                Log::info('GenerateArticleJob: article already in publication queue', ['article_id' => $article->id]);
                return;
            }

            // Delay 90 seconds to let translations finish (Phase 15 dispatches them async)
            $queueItem = PublicationQueueItem::create([
                'publishable_type' => GeneratedArticle::class,
                'publishable_id'   => $article->id,
                'endpoint_id'      => $endpoint->id,
                'status'           => 'pending',
                'priority'         => 'default',
                'max_attempts'     => 5,
            ]);

            PublishContentJob::dispatch($queueItem->id)->delay(now()->addSeconds(90));

            Log::info('GenerateArticleJob: auto-publish queued', [
                'article_id'    => $article->id,
                'endpoint'      => $endpoint->name,
                'queue_item_id' => $queueItem->id,
            ]);
        } catch (\Throwable $e) {
            // Auto-publish failure should NOT fail the entire generation
            Log::error('GenerateArticleJob: auto-publish failed (non-blocking)', [
                'article_id' => $article->id,
                'error'      => $e->getMessage(),
            ]);
        }
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
