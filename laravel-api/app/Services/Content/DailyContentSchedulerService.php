<?php

namespace App\Services\Content;

use App\Models\DailyContentLog;
use App\Models\DailyContentSchedule;
use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Models\QuestionCluster;
use App\Models\TopicCluster;
use App\Services\Quality\AutoQualityImproverService;
use App\Services\Quality\PlagiarismService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DailyContentSchedulerService
{
    public function __construct(
        private TopicClusteringService $topicClustering,
        private QuestionClusteringService $questionClustering,
        private ResearchBriefService $researchBrief,
        private ArticleGenerationService $articleGeneration,
        private ArticleFromQuestionsService $articleFromQuestions,
        private QaFromQuestionsService $qaFromQuestions,
        private ComparativeGenerationService $comparativeGeneration,
        private DeduplicationService $dedup,
        private AutoQualityImproverService $qualityImprover,
        private PlagiarismService $plagiarism,
    ) {}

    /**
     * Run the daily content generation schedule.
     * If no schedule provided, use the active default one.
     */
    public function runDaily(?DailyContentSchedule $schedule = null): DailyContentLog
    {
        $schedule ??= DailyContentSchedule::active()->where('name', 'default')->first();

        if (!$schedule) {
            // Create a default schedule if none exists
            $schedule = DailyContentSchedule::create([
                'name'      => 'default',
                'is_active' => true,
            ]);
        }

        // Check today's log — if already completed, skip
        $todayLog = DailyContentLog::where('schedule_id', $schedule->id)
            ->where('date', today()->toDateString())
            ->first();

        if ($todayLog && $todayLog->completed_at) {
            Log::info('DailyContentScheduler: already completed today', [
                'schedule' => $schedule->name,
                'log_id'   => $todayLog->id,
            ]);
            return $todayLog;
        }

        // Create or reuse today's log
        $log = $todayLog ?? DailyContentLog::create([
            'schedule_id' => $schedule->id,
            'date'        => today()->toDateString(),
            'started_at'  => now(),
        ]);

        if (!$log->started_at) {
            $log->update(['started_at' => now()]);
        }

        $errors = $log->errors ?? [];

        Log::info('DailyContentScheduler: starting', [
            'schedule' => $schedule->name,
            'log_id'   => $log->id,
        ]);

        // ═══════════════════════════════════════════════════════
        // 1. Pillar articles (long, 3000+ words, guide content)
        // ═══════════════════════════════════════════════════════
        $pillarNeeded = $schedule->pillar_articles_per_day - $log->pillar_generated;
        if ($pillarNeeded > 0) {
            Log::info('DailyContentScheduler: generating pillar articles', ['needed' => $pillarNeeded]);
            $this->generateArticles($log, $schedule, 'pillar', $pillarNeeded, $errors);
        }

        // ═══════════════════════════════════════════════════════
        // 2. Normal articles (medium, 1500-2500 words)
        // ═══════════════════════════════════════════════════════
        $normalNeeded = $schedule->normal_articles_per_day - $log->normal_generated;
        if ($normalNeeded > 0) {
            Log::info('DailyContentScheduler: generating normal articles', ['needed' => $normalNeeded]);
            $this->generateArticles($log, $schedule, 'normal', $normalNeeded, $errors);
        }

        // ═══════════════════════════════════════════════════════
        // 3. Q&A generation
        // ═══════════════════════════════════════════════════════
        $qaNeeded = $schedule->qa_per_day - $log->qa_generated;
        if ($qaNeeded > 0) {
            Log::info('DailyContentScheduler: generating Q&A entries', ['needed' => $qaNeeded]);
            $this->generateQa($log, $schedule, $qaNeeded, $errors);
        }

        // ═══════════════════════════════════════════════════════
        // 4. Comparative articles
        // ═══════════════════════════════════════════════════════
        $compNeeded = $schedule->comparatives_per_day - $log->comparatives_generated;
        if ($compNeeded > 0) {
            Log::info('DailyContentScheduler: generating comparatives', ['needed' => $compNeeded]);
            $this->generateComparatives($log, $schedule, $compNeeded, $errors);
        }

        // ═══════════════════════════════════════════════════════
        // 5. Custom titles (one-shot, removed after generation)
        // ═══════════════════════════════════════════════════════
        $customTitles = $schedule->custom_titles ?? [];
        if (!empty($customTitles)) {
            Log::info('DailyContentScheduler: generating custom titles', ['count' => count($customTitles)]);
            $this->generateCustomTitles($log, $schedule, $customTitles, $errors);
        }

        // ═══════════════════════════════════════════════════════
        // 6. Schedule publications for today
        // ═══════════════════════════════════════════════════════
        Log::info('DailyContentScheduler: scheduling publications');
        $publishedCount = $this->schedulePublicationsForToday($schedule);
        $log->update(['published' => $publishedCount]);

        // Finalize
        $log->update([
            'errors'       => !empty($errors) ? $errors : null,
            'completed_at' => now(),
        ]);

        Log::info('DailyContentScheduler: completed', [
            'schedule'   => $schedule->name,
            'log_id'     => $log->id,
            'pillar'     => $log->pillar_generated,
            'normal'     => $log->normal_generated,
            'qa'         => $log->qa_generated,
            'comparatives' => $log->comparatives_generated,
            'custom'     => $log->custom_generated,
            'published'  => $publishedCount,
            'errors'     => count($errors),
        ]);

        return $log->fresh();
    }

    /**
     * Schedule publication times for a given day.
     * Returns array of Carbon DateTime objects.
     */
    public function schedulePublications(int $count, int $startHour, int $endHour, bool $irregular): array
    {
        if ($count <= 0 || $startHour >= $endHour) {
            return [];
        }

        $totalMinutes = ($endHour - $startHour) * 60;
        $baseInterval = $totalMinutes / $count;
        $times = [];
        $currentMinutes = 0;

        for ($i = 0; $i < $count; $i++) {
            $minuteOffset = $currentMinutes;

            if ($irregular && $i > 0) {
                // Add random jitter: ±30% of base interval
                $jitter = (int) ($baseInterval * 0.3);
                $minuteOffset += random_int(-$jitter, $jitter);
                $minuteOffset = max(0, min($totalMinutes, $minuteOffset));
            }

            $time = today()
                ->setHour($startHour)
                ->addMinutes((int) round($minuteOffset));

            // Clamp to the end hour
            $maxTime = today()->setHour($endHour);
            if ($time->greaterThan($maxTime)) {
                $time = $maxTime->subMinutes(random_int(1, 10));
            }

            $times[] = $time;
            $currentMinutes += $baseInterval;
        }

        // Sort chronologically
        usort($times, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return $times;
    }

    /**
     * Get current schedule status + today's progress.
     */
    public function getStatus(): array
    {
        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();

        if (!$schedule) {
            return [
                'schedule'   => null,
                'today'      => null,
                'is_running' => false,
            ];
        }

        $todayLog = DailyContentLog::where('schedule_id', $schedule->id)
            ->where('date', today()->toDateString())
            ->first();

        return [
            'schedule' => $schedule,
            'today'    => $todayLog ? [
                'pillar'      => $todayLog->pillar_generated . '/' . $schedule->pillar_articles_per_day,
                'normal'      => $todayLog->normal_generated . '/' . $schedule->normal_articles_per_day,
                'qa'          => $todayLog->qa_generated . '/' . $schedule->qa_per_day,
                'comparatives' => $todayLog->comparatives_generated . '/' . $schedule->comparatives_per_day,
                'custom'      => $todayLog->custom_generated,
                'published'   => $todayLog->published . '/' . $schedule->publish_per_day,
                'total_cost_cents' => $todayLog->total_cost_cents,
                'started_at'  => $todayLog->started_at?->toIso8601String(),
                'completed_at' => $todayLog->completed_at?->toIso8601String(),
            ] : null,
            'is_running' => $todayLog && $todayLog->started_at && !$todayLog->completed_at,
        ];
    }

    // ============================================================
    // Private helpers
    // ============================================================

    private function generateArticles(
        DailyContentLog $log,
        DailyContentSchedule $schedule,
        string $type,
        int $needed,
        array &$errors,
    ): void {
        $isPillar = $type === 'pillar';
        $counter = $isPillar ? 'pillar_generated' : 'normal_generated';
        $generated = 0;

        // Try topic clusters first (richest sources)
        $clusters = TopicCluster::whereIn('status', ['pending', 'ready'])
            ->when($schedule->target_country, fn ($q, $c) => $q->where('country', $c))
            ->orderByDesc('source_articles_count')
            ->limit($needed)
            ->get();

        foreach ($clusters as $cluster) {
            if ($generated >= $needed) {
                break;
            }

            try {
                // Dedup check
                $existing = $this->dedup->findDuplicateArticle($cluster->name, $cluster->country ?? '', 'fr');
                if ($existing) {
                    $cluster->update(['generated_article_id' => $existing->id, 'status' => 'completed']);
                    continue;
                }

                // Generate research brief if missing
                if (!$cluster->researchBrief) {
                    $this->researchBrief->generateBrief($cluster);
                    $cluster->refresh();
                }

                $cluster->update(['status' => 'generating']);

                $brief = $cluster->researchBrief;
                $params = [
                    'topic'        => $cluster->name,
                    'language'     => 'fr',
                    'country'      => $cluster->country,
                    'content_type' => $isPillar ? 'guide' : 'article',
                    'keywords'     => (function () use ($brief) {
                        $pk = $brief?->suggested_keywords['primary'] ?? [];
                        if (is_string($pk)) $pk = [$pk];
                        return $pk;
                    })(),
                    'cluster_id'   => $cluster->id,
                    'tone'         => 'professional',
                    'length'       => $isPillar ? 'long' : 'medium',
                    'generate_faq' => true,
                    'faq_count'    => $isPillar ? 12 : 8,
                    'research_sources'     => true,
                    'image_source'         => 'unsplash',
                    'auto_internal_links'  => true,
                    'auto_affiliate_links' => true,
                    'translation_languages' => [],
                ];

                $article = $this->articleGeneration->generate($params);

                // Plagiarism check
                $plagResult = $this->plagiarism->check($article);
                if (!$plagResult['is_original'] && $plagResult['similarity_percent'] > 40) {
                    $article->update(['status' => 'draft', 'quality_score' => 0]);
                    $cluster->update(['status' => 'ready']);
                    continue;
                }

                // Quality improvement if below threshold
                if ($article->quality_score < $schedule->min_quality_score) {
                    $this->qualityImprover->improve($article, $schedule->min_quality_score);
                }

                $cluster->update(['generated_article_id' => $article->id, 'status' => 'completed']);

                // Track cost
                $log->increment('total_cost_cents', $article->generation_cost_cents ?? 0);
                $generated++;
                $log->increment($counter);
            } catch (\Throwable $e) {
                $errors[] = "{$type} cluster #{$cluster->id} ({$cluster->name}): {$e->getMessage()}";
                Log::error("DailyContentScheduler: {$type} generation failed", [
                    'cluster_id' => $cluster->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // If we still need more, try question clusters
        if ($generated < $needed) {
            $qClusters = QuestionCluster::where('status', 'pending')
                ->orderByDesc('popularity_score')
                ->limit($needed - $generated)
                ->get();

            foreach ($qClusters as $qCluster) {
                if ($generated >= $needed) {
                    break;
                }

                try {
                    $existing = $this->dedup->findDuplicateArticle($qCluster->name, $qCluster->country ?? '', 'fr');
                    if ($existing) {
                        $qCluster->update(['generated_article_id' => $existing->id, 'status' => 'completed']);
                        continue;
                    }

                    $qCluster->update(['status' => 'generating_article']);
                    $article = $this->articleFromQuestions->generateFromCluster($qCluster);

                    $plagResult = $this->plagiarism->check($article);
                    if (!$plagResult['is_original'] && $plagResult['similarity_percent'] > 40) {
                        $article->update(['status' => 'draft', 'quality_score' => 0]);
                        $qCluster->update(['status' => 'pending']);
                        continue;
                    }

                    if ($article->quality_score < $schedule->min_quality_score) {
                        $this->qualityImprover->improve($article, $schedule->min_quality_score);
                    }

                    $log->increment('total_cost_cents', $article->generation_cost_cents ?? 0);
                    $generated++;
                    $log->increment($counter);
                } catch (\Throwable $e) {
                    $errors[] = "{$type} qcluster #{$qCluster->id}: {$e->getMessage()}";
                    Log::error("DailyContentScheduler: {$type} q-generation failed", [
                        'qcluster_id' => $qCluster->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function generateQa(
        DailyContentLog $log,
        DailyContentSchedule $schedule,
        int $needed,
        array &$errors,
    ): void {
        $generated = 0;

        // From question clusters
        $completedQClusters = QuestionCluster::whereIn('status', ['completed', 'generating_article'])
            ->where('generated_qa_count', 0)
            ->orderByDesc('popularity_score')
            ->limit((int) ceil($needed / 5))
            ->get();

        foreach ($completedQClusters as $qCluster) {
            if ($generated >= $needed) {
                break;
            }

            try {
                $qaEntries = $this->qaFromQuestions->generateFromCluster($qCluster, 5);
                $count = $qaEntries->count();
                $generated += $count;
                $log->increment('qa_generated', $count);
            } catch (\Throwable $e) {
                $errors[] = "QA qcluster #{$qCluster->id}: {$e->getMessage()}";
            }
        }

        // From article FAQs if we still need more
        if ($generated < $needed) {
            $articlesWithoutQa = GeneratedArticle::where('status', '!=', 'generating')
                ->where('language', 'fr')
                ->whereNull('parent_article_id')
                ->whereDoesntHave('qaEntries')
                ->has('faqs', '>=', 3)
                ->limit((int) ceil(($needed - $generated) / 3))
                ->get();

            foreach ($articlesWithoutQa as $article) {
                if ($generated >= $needed) {
                    break;
                }

                try {
                    $qaEntries = app(QaGenerationService::class)->generateFromArticleFaqs($article);
                    $count = $qaEntries->count();
                    $generated += $count;
                    $log->increment('qa_generated', $count);
                } catch (\Throwable $e) {
                    $errors[] = "QA article #{$article->id}: {$e->getMessage()}";
                }
            }
        }
    }

    private function generateComparatives(
        DailyContentLog $log,
        DailyContentSchedule $schedule,
        int $needed,
        array &$errors,
    ): void {
        $generated = 0;

        // Find popular countries with enough data for comparison
        $countries = GeneratedArticle::where('language', 'fr')
            ->whereNull('parent_article_id')
            ->whereNotNull('country')
            ->when($schedule->target_country, fn ($q, $c) => $q->where('country', $c))
            ->selectRaw('country, COUNT(*) as cnt')
            ->groupBy('country')
            ->having('cnt', '>=', 3)
            ->orderByDesc('cnt')
            ->limit(20)
            ->pluck('country')
            ->toArray();

        if (count($countries) < 2) {
            Log::info('DailyContentScheduler: not enough countries for comparatives');
            return;
        }

        // Generate entity pairs from popular countries
        $pairs = [];
        for ($i = 0; $i < count($countries) - 1 && count($pairs) < $needed; $i++) {
            for ($j = $i + 1; $j < count($countries) && count($pairs) < $needed; $j++) {
                $pairs[] = [$countries[$i], $countries[$j]];
            }
        }

        foreach ($pairs as [$countryA, $countryB]) {
            if ($generated >= $needed) {
                break;
            }

            try {
                $category = $schedule->target_category ?? 'visa';
                $title = "Comparatif: {$category} {$countryA} vs {$countryB}";

                // Dedup check
                $existing = $this->dedup->findDuplicateArticle($title, '', 'fr');
                if ($existing) {
                    continue;
                }

                $comparative = $this->comparativeGeneration->generate([
                    'entity_a'  => "{$category} {$countryA}",
                    'entity_b'  => "{$category} {$countryB}",
                    'language'  => 'fr',
                    'category'  => $category,
                ]);

                $log->increment('total_cost_cents', $comparative->generation_cost_cents ?? 0);
                $generated++;
                $log->increment('comparatives_generated');
            } catch (\Throwable $e) {
                $errors[] = "Comparative {$countryA} vs {$countryB}: {$e->getMessage()}";
                Log::error('DailyContentScheduler: comparative failed', [
                    'countries' => [$countryA, $countryB],
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    private function generateCustomTitles(
        DailyContentLog $log,
        DailyContentSchedule $schedule,
        array $customTitles,
        array &$errors,
    ): void {
        $remaining = $customTitles;
        $generated = 0;

        foreach ($customTitles as $index => $title) {
            try {
                // Dedup check
                $existing = $this->dedup->findDuplicateArticle($title, $schedule->target_country ?? '', 'fr');
                if ($existing) {
                    unset($remaining[$index]);
                    continue;
                }

                $params = [
                    'topic'        => $title,
                    'language'     => 'fr',
                    'country'      => $schedule->target_country,
                    'content_type' => 'article',
                    'tone'         => 'professional',
                    'length'       => 'medium',
                    'generate_faq' => true,
                    'faq_count'    => 8,
                    'research_sources'     => true,
                    'image_source'         => 'unsplash',
                    'auto_internal_links'  => true,
                    'auto_affiliate_links' => true,
                    'translation_languages' => [],
                ];

                $article = $this->articleGeneration->generate($params);

                // Quality improvement if below threshold
                if ($article->quality_score < $schedule->min_quality_score) {
                    $this->qualityImprover->improve($article, $schedule->min_quality_score);
                }

                $log->increment('total_cost_cents', $article->generation_cost_cents ?? 0);
                $generated++;
                $log->increment('custom_generated');

                // Remove from the array after successful generation
                unset($remaining[$index]);
            } catch (\Throwable $e) {
                $errors[] = "Custom title \"{$title}\": {$e->getMessage()}";
                Log::error('DailyContentScheduler: custom title failed', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update custom_titles: keep only unprocessed ones
        $schedule->update([
            'custom_titles' => !empty($remaining) ? array_values($remaining) : null,
        ]);
    }

    private function schedulePublicationsForToday(DailyContentSchedule $schedule): int
    {
        $endpoint = PublishingEndpoint::where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (!$endpoint) {
            Log::warning('DailyContentScheduler: no default publishing endpoint found');
            return 0;
        }

        // Get unpublished articles with quality >= min_quality_score
        $articles = GeneratedArticle::whereIn('status', ['draft', 'review'])
            ->where('language', 'fr')
            ->whereNull('parent_article_id')
            ->where('quality_score', '>=', $schedule->min_quality_score)
            ->when($schedule->target_country, fn ($q, $c) => $q->where('country', $c))
            ->orderByDesc('quality_score')
            ->limit($schedule->publish_per_day)
            ->get();

        if ($articles->isEmpty()) {
            return 0;
        }

        $times = $this->schedulePublications(
            $articles->count(),
            $schedule->publish_start_hour,
            $schedule->publish_end_hour,
            $schedule->publish_irregular,
        );

        $count = 0;
        foreach ($articles as $i => $article) {
            $scheduledAt = $times[$i] ?? now();

            // Skip if already in queue
            $exists = PublicationQueueItem::where('publishable_type', GeneratedArticle::class)
                ->where('publishable_id', $article->id)
                ->whereIn('status', ['pending', 'scheduled'])
                ->exists();

            if ($exists) {
                continue;
            }

            PublicationQueueItem::create([
                'publishable_type' => GeneratedArticle::class,
                'publishable_id'   => $article->id,
                'endpoint_id'      => $endpoint->id,
                'status'           => 'scheduled',
                'scheduled_at'     => $scheduledAt,
                'priority'         => 5,
                'attempts'         => 0,
                'max_attempts'     => 3,
            ]);

            $count++;
        }

        return $count;
    }
}
