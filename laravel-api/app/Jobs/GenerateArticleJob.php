<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Services\Content\ArticleGenerationService;
use App\Services\Content\ArticleImprovementService;
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

        // ── Auto-improvement loop (max 2 passes) ──
        //
        // If the initial quality score is below the publication threshold,
        // ArticleImprovementService applies targeted gpt-4o-mini fixes
        // (expand content, rewrite AI phrases, generate FAQs, inject internal
        // links, regenerate meta tags, etc.) and re-runs the quality check.
        //
        // Each pass costs ~$0.002-0.005 and can boost score by 15-30 points.
        // The loop is capped at 2 attempts to avoid runaway cost on truly
        // irredeemable content. Anti-cannibalization issues abort the loop
        // immediately (they cannot be auto-fixed).
        //
        // If after 2 passes the score is still < 60, the article falls
        // through to the existing 'review' fallback below — same behavior
        // as before this enhancement, just with a higher chance of success.
        $isOriginal = !$article->parent_article_id;
        $hasContent = !empty($article->content_html) && $article->word_count > 0;
        $maxImprovementAttempts = 2;
        $attempt = 0;
        $allImprovementsApplied = [];

        while (
            $qualityScore < 60
            && $attempt < $maxImprovementAttempts
            && $isOriginal
            && $hasContent
        ) {
            $attempt++;
            try {
                $improvementService = app(ArticleImprovementService::class);
                $newResult = $improvementService->improve($article, $qualityResult);

                // Abort the loop if the improvement service refused to act
                if (!empty($newResult['aborted'])) {
                    Log::info('GenerateArticleJob: improvement loop aborted', [
                        'article_id' => $article->id,
                        'reason' => $newResult['aborted'],
                    ]);
                    break;
                }

                $previousScore = $qualityScore;
                $qualityResult = $newResult;
                $qualityScore = $newResult['score'] ?? $qualityScore;
                $qualityIssues = $newResult['issues'] ?? [];
                $allImprovementsApplied = array_merge($allImprovementsApplied, $newResult['improvements_applied'] ?? []);
                $article->refresh();
                $hasContent = !empty($article->content_html) && $article->word_count > 0;

                Log::info('GenerateArticleJob: improvement pass complete', [
                    'article_id' => $article->id,
                    'attempt' => $attempt,
                    'score_before' => $previousScore,
                    'score_after' => $qualityScore,
                    'delta' => $qualityScore - $previousScore,
                    'applied' => $newResult['improvements_applied'] ?? [],
                ]);

                // Stop early if no progress was made (avoid wasting another pass)
                if ($qualityScore <= $previousScore) {
                    Log::info('GenerateArticleJob: improvement made no progress, stopping loop', [
                        'article_id' => $article->id,
                        'score' => $qualityScore,
                    ]);
                    break;
                }
            } catch (\Throwable $e) {
                // Improvement is best-effort. Failing here must NOT crash the
                // main generation pipeline — fall through to the existing
                // review-fallback path below.
                Log::error('GenerateArticleJob: improvement loop exception (non-blocking)', [
                    'article_id' => $article->id,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        // Persist final quality result on the article
        $article->update([
            'quality_score' => $qualityScore,
            'generation_notes' => !empty($qualityIssues) ? json_encode($qualityIssues) : null,
        ]);

        if (!empty($allImprovementsApplied)) {
            Log::info('GenerateArticleJob: improvement summary', [
                'article_id' => $article->id,
                'final_score' => $qualityScore,
                'attempts' => $attempt,
                'all_improvements' => array_unique($allImprovementsApplied),
            ]);
        }

        // Auto-publish gate — "never block" philosophy.
        //
        // An article is ALWAYS published if it has usable content. The scores
        // (quality_score, editorial_score) are informational — they trigger
        // auto-improvement passes but do NOT block publication. The only hard
        // blockers are:
        //   - empty content (nothing to publish)
        //   - word_count == 0 (same)
        //   - is a translation (the parent handles publication)
        //   - compliance issues flagged by the brand-safety check
        //
        // Low scores trigger phase14c_improveFromJudgeRecommendations which
        // rewrites title/meta using the judge's suggestions. The improved
        // article is then published — it's better to ship an imperfect piece
        // than to leave it rotting in a review queue forever.
        $article->refresh();
        $editorialScore = $article->editorial_score;
        $editorialReport = $article->editorial_review ?? [];

        $hasContent = !empty($article->content_html)
            && $article->word_count > 0
            && !$article->parent_article_id;

        // Hard compliance blockers — the only legitimate reason to withhold
        // publication. These come from phase08/brand checks and typically
        // indicate legal/brand risk (inappropriate content, banned claims).
        $hasCriticalCompliance = !empty($qualityIssues);

        $canPublish = $hasContent && !$hasCriticalCompliance;

        // Informational breakdown for the Telegram alert + logs
        $scoreSummary = [
            'quality_score'   => $qualityScore,
            'editorial_score' => $editorialScore,
            'improved'        => false, // set to true below if phase14c fires
        ];
        if (!empty($editorialReport['issues']) && is_array($editorialReport['issues'])) {
            $scoreSummary['issues'] = array_slice($editorialReport['issues'], 0, 3);
        }

        // Auto-improvement pass: if the judge flagged a low editorial_score
        // (< 70) AND returned concrete recommendations, apply them in-place.
        // This is a best-effort improvement — if it fails, we still publish
        // the original article.
        if ($hasContent && $editorialScore !== null && $editorialScore < 70 && !empty($editorialReport)) {
            try {
                $improved = app(\App\Services\Content\ArticleGenerationService::class)
                    ->phase14c_improveFromJudgeRecommendations($article->fresh(), $editorialReport);
                if ($improved) {
                    $scoreSummary['improved'] = true;
                    Log::info('GenerateArticleJob: article auto-improved from judge recommendations', [
                        'article_id' => $article->id,
                        'editorial_score_before' => $editorialScore,
                    ]);
                    $article->refresh();
                }
            } catch (\Throwable $e) {
                // Improvement failed — log but do NOT block publication.
                Log::warning('GenerateArticleJob: auto-improvement failed (non-blocking)', [
                    'article_id' => $article->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if (!$canPublish && !$article->parent_article_id && $article->word_count > 0) {
            // Only reaches here if there are HARD compliance issues. The
            // article is kept in review for manual intervention — content
            // remains intact and can be published manually after fix.
            $article->update(['status' => 'review']);
            Log::warning('GenerateArticleJob: article held in review due to compliance issues', [
                'article_id' => $article->id,
                'compliance_issues' => $qualityIssues,
                'quality_score' => $qualityScore,
                'editorial_score' => $editorialScore,
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
