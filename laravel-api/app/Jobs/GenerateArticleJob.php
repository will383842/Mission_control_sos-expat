<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Services\Content\ArticleGenerationService;
use App\Services\Content\QualityGuardService;
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

        // ── Sanitize all internal links in generated HTML ──
        if (!empty($article->content_html)) {
            $sanitized = \App\Services\Content\LinkSanitizer::sanitize(
                $article->content_html,
                $article->language ?? 'fr',
                $article->country,
            );
            if ($sanitized !== $article->content_html) {
                $article->update(['content_html' => $sanitized]);
                Log::info('GenerateArticleJob: links sanitized', ['article_id' => $article->id]);
            }
        }

        // Also sanitize translations
        foreach ($article->translations ?? [] as $translation) {
            if (!empty($translation->content_html)) {
                $sanitized = \App\Services\Content\LinkSanitizer::sanitize(
                    $translation->content_html,
                    $translation->language ?? 'fr',
                    $article->country,
                );
                if ($sanitized !== $translation->content_html) {
                    $translation->update(['content_html' => $sanitized]);
                }
            }
        }

        // Calculate and persist total generation cost
        $totalCost = \App\Models\ApiCost::where('costable_type', \App\Models\GeneratedArticle::class)
            ->where('costable_id', $article->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $article->update(['generation_cost_cents' => $totalCost]);
        }

        // ── Quality Guard ──
        $qualityGuard = app(QualityGuardService::class);
        $qualityResult = $qualityGuard->check($article);
        $qualityScore = $qualityResult['score'] ?? 0;
        $qualityIssues = $qualityResult['issues'] ?? [];

        // Persist quality result on the article
        $article->update([
            'quality_score' => $qualityScore,
            'generation_notes' => !empty($qualityIssues) ? json_encode($qualityIssues) : null,
        ]);

        // Auto-publish if: score >= 60, no brand compliance issues, has content, is original (not translation)
        $canPublish = $qualityScore >= 60
            && empty($qualityIssues)
            && !$article->parent_article_id
            && !empty($article->content_html)
            && $article->word_count > 0;

        if (!$canPublish && !$article->parent_article_id && $article->word_count > 0) {
            // Has content but quality issues → review (not lost, can be published manually)
            $article->update(['status' => 'review']);
            Log::info('GenerateArticleJob: article set to review', [
                'article_id' => $article->id,
                'quality_score' => $qualityScore,
                'issues' => $qualityIssues,
            ]);
        }

        // ── Auto-publish to default endpoint (blog sos-expat.com) ──
        if ($canPublish) {
            $this->autoPublish($article);
        }

        Log::info('GenerateArticleJob completed', [
            'id' => $article->id,
            'title' => $article->title,
            'word_count' => $article->word_count,
            'seo_score' => $article->seo_score,
            'cost_cents' => $article->generation_cost_cents,
            'quality_passed' => $canPublish,
            'auto_published' => $canPublish,
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
                Log::error('GenerateArticleJob: no default publishing endpoint, skipping auto-publish', [
                    'article_id' => $article->id,
                ]);
                // Alert via Telegram — this is a critical config issue
                $botToken = config('services.telegram_alerts.bot_token');
                $chatId = config('services.telegram_alerts.chat_id');
                if ($botToken && $chatId) {
                    try {
                        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $chatId,
                            'parse_mode' => 'Markdown',
                            'text' => "🚨 *No Publishing Endpoint!*\nArticle `{$article->id}` generated but cannot be published.\nNo default active endpoint configured.\nRun: `php artisan db:seed --class=PublishingEndpointSeeder`",
                        ]);
                    } catch (\Throwable) {}
                }
                $article->update(['status' => 'review']);
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

            // Create queue item — the publication-engine cron (every 2 min) will
            // pick this up after 6 min (waits for translations to finish).
            // NO Redis delay — DB is the source of truth, not Redis.
            $queueItem = PublicationQueueItem::create([
                'publishable_type' => GeneratedArticle::class,
                'publishable_id'   => $article->id,
                'endpoint_id'      => $endpoint->id,
                'status'           => 'pending',
                'priority'         => 'default',
                'max_attempts'     => 5,
            ]);

            // Also dispatch immediately as a best-effort (if Redis survives, faster publish)
            PublishContentJob::dispatch($queueItem->id);

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
