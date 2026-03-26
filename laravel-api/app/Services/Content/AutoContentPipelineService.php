<?php

namespace App\Services\Content;

use App\Models\ContentArticle;
use App\Models\ContentQuestion;
use App\Models\GeneratedArticle;
use App\Models\QuestionCluster;
use App\Models\TopicCluster;
use App\Services\Quality\AutoQualityImproverService;
use App\Services\Quality\PlagiarismService;
use Illuminate\Support\Facades\Log;

class AutoContentPipelineService
{
    public function __construct(
        private TopicClusteringService $topicClustering,
        private QuestionClusteringService $questionClustering,
        private ResearchBriefService $researchBrief,
        private ArticleGenerationService $articleGeneration,
        private ArticleFromQuestionsService $articleFromQuestions,
        private QaFromQuestionsService $qaFromQuestions,
        private DeduplicationService $dedup,
        private AutoQualityImproverService $qualityImprover,
        private PlagiarismService $plagiarism,
        private KeywordTrackingService $keywordTracking,
        private SeoChecklistService $seoChecklist,
    ) {}

    /**
     * Run the FULL automatic pipeline.
     * Processes all unprocessed scraped content end-to-end.
     *
     * @param array $options Configuration:
     *   - country: string|null — limit to a specific country
     *   - category: string|null — limit to a specific category
     *   - max_articles: int — max articles to generate (default 50)
     *   - min_quality_score: int — minimum quality threshold (default 85)
     *   - include_qa: bool — also generate Q&A pages (default true)
     *   - articles_from_questions: bool — also generate articles from forum questions (default true)
     * @return array Summary of what was done
     */
    public function run(array $options = []): array
    {
        $country = $options['country'] ?? null;
        $category = $options['category'] ?? null;
        $maxArticles = $options['max_articles'] ?? 50;
        $minQuality = $options['min_quality_score'] ?? 85;
        $includeQa = $options['include_qa'] ?? true;
        $fromQuestions = $options['articles_from_questions'] ?? true;

        $summary = [
            'started_at' => now()->toIso8601String(),
            'clusters_created' => 0,
            'question_clusters_created' => 0,
            'articles_generated' => 0,
            'articles_improved' => 0,
            'qa_generated' => 0,
            'plagiarism_blocked' => 0,
            'dedup_skipped' => 0,
            'errors' => [],
        ];

        Log::info('AutoContentPipeline: starting', ['options' => $options]);

        // ═══════════════════════════════════════════════════════
        // STEP 1: Cluster ALL unprocessed scraped articles
        // ═══════════════════════════════════════════════════════
        Log::info('AutoContentPipeline: Step 1 — Clustering scraped articles');

        $articleCombos = $this->getUnprocessedCombos($country, $category);

        foreach ($articleCombos as $combo) {
            try {
                $clusters = $this->topicClustering->clusterByCountryAndCategory(
                    $combo['country'],
                    $combo['category']
                );
                $summary['clusters_created'] += $clusters->count();
                Log::info('AutoContentPipeline: clustered', [
                    'country' => $combo['country'],
                    'category' => $combo['category'],
                    'clusters' => $clusters->count(),
                ]);
            } catch (\Throwable $e) {
                $summary['errors'][] = "Clustering {$combo['country']}/{$combo['category']}: {$e->getMessage()}";
                Log::error('AutoContentPipeline: clustering failed', ['error' => $e->getMessage()]);
            }
        }

        // ═══════════════════════════════════════════════════════
        // STEP 2: Cluster ALL unprocessed forum questions
        // ═══════════════════════════════════════════════════════
        if ($fromQuestions) {
            Log::info('AutoContentPipeline: Step 2 — Clustering forum questions');

            $questionCombos = $this->getUnprocessedQuestionCountries($country);

            foreach ($questionCombos as $countrySlug) {
                try {
                    $qClusters = $this->questionClustering->clusterByCountry($countrySlug, $category);
                    $summary['question_clusters_created'] += $qClusters->count();
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Q-Clustering {$countrySlug}: {$e->getMessage()}";
                }
            }
        }

        // ═══════════════════════════════════════════════════════
        // STEP 3: Generate articles from topic clusters
        //   - Sorted by source_articles_count DESC (richest clusters first)
        //   - Dedup check before each generation
        //   - Research brief → Article → Quality check → Plagiarism check
        // ═══════════════════════════════════════════════════════
        Log::info('AutoContentPipeline: Step 3 — Generating articles from topic clusters');

        $pendingClusters = TopicCluster::where('status', 'pending')
            ->orWhere('status', 'ready')
            ->orderByDesc('source_articles_count')
            ->limit($maxArticles)
            ->get();

        foreach ($pendingClusters as $cluster) {
            if ($summary['articles_generated'] >= $maxArticles) {
                break;
            }

            try {
                $article = $this->processTopicCluster($cluster, $minQuality);
                if ($article === 'dedup') {
                    $summary['dedup_skipped']++;
                } elseif ($article === 'plagiarism') {
                    $summary['plagiarism_blocked']++;
                } elseif ($article instanceof GeneratedArticle) {
                    $summary['articles_generated']++;

                    // Auto-improve if below quality threshold
                    if ($article->quality_score < $minQuality) {
                        $this->qualityImprover->improve($article, $minQuality);
                        $summary['articles_improved']++;
                    }
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "Cluster #{$cluster->id} ({$cluster->name}): {$e->getMessage()}";
                Log::error('AutoContentPipeline: cluster generation failed', [
                    'cluster_id' => $cluster->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════
        // STEP 4: Generate articles from question clusters
        //   - Sorted by popularity_score DESC (most popular first)
        //   - Same dedup + plagiarism checks
        // ═══════════════════════════════════════════════════════
        if ($fromQuestions) {
            Log::info('AutoContentPipeline: Step 4 — Generating articles from question clusters');

            $pendingQClusters = QuestionCluster::where('status', 'pending')
                ->orderByDesc('popularity_score')
                ->limit($maxArticles - $summary['articles_generated'])
                ->get();

            foreach ($pendingQClusters as $qCluster) {
                if ($summary['articles_generated'] >= $maxArticles) {
                    break;
                }

                try {
                    $article = $this->processQuestionCluster($qCluster, $minQuality);
                    if ($article === 'dedup') {
                        $summary['dedup_skipped']++;
                    } elseif ($article instanceof GeneratedArticle) {
                        $summary['articles_generated']++;
                        if ($article->quality_score < $minQuality) {
                            $this->qualityImprover->improve($article, $minQuality);
                            $summary['articles_improved']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = "QCluster #{$qCluster->id}: {$e->getMessage()}";
                }
            }
        }

        // ═══════════════════════════════════════════════════════
        // STEP 5: Generate Q&A pages from question clusters
        // ═══════════════════════════════════════════════════════
        if ($includeQa) {
            Log::info('AutoContentPipeline: Step 5 — Generating Q&A pages');

            $completedQClusters = QuestionCluster::whereIn('status', ['completed', 'generating_article'])
                ->where('generated_qa_count', 0)
                ->orderByDesc('popularity_score')
                ->limit(100)
                ->get();

            foreach ($completedQClusters as $qCluster) {
                try {
                    $qaEntries = $this->qaFromQuestions->generateFromCluster($qCluster, 5);
                    $summary['qa_generated'] += $qaEntries->count();
                } catch (\Throwable $e) {
                    $summary['errors'][] = "QA for QCluster #{$qCluster->id}: {$e->getMessage()}";
                }
            }

            // Also generate Q&A from article FAQs
            $articlesWithoutQa = GeneratedArticle::where('status', '!=', 'generating')
                ->where('language', 'fr')
                ->whereNull('parent_article_id')
                ->whereDoesntHave('qaEntries')
                ->has('faqs', '>=', 3)
                ->limit(50)
                ->get();

            foreach ($articlesWithoutQa as $article) {
                try {
                    $qaEntries = app(QaGenerationService::class)->generateFromArticleFaqs($article);
                    $summary['qa_generated'] += $qaEntries->count();
                } catch (\Throwable $e) {
                    $summary['errors'][] = "QA for article #{$article->id}: {$e->getMessage()}";
                }
            }
        }

        // ═══════════════════════════════════════════════════════
        // STEP 6: Track keywords for all new articles
        // ═══════════════════════════════════════════════════════
        Log::info('AutoContentPipeline: Step 6 — Tracking keywords');

        GeneratedArticle::where('language', 'fr')
            ->whereNull('parent_article_id')
            ->whereDoesntHave('keywords')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->chunkById(50, function ($articles) {
                foreach ($articles as $article) {
                    try {
                        $keywords = $this->keywordTracking->analyzeArticleKeywords($article);
                        if (!empty($keywords)) {
                            $this->keywordTracking->trackKeywordsForArticle($article, $keywords);
                        }
                    } catch (\Throwable $e) {
                        // Non-blocking
                    }
                }
            });

        // ═══════════════════════════════════════════════════════
        // STEP 7: Run SEO checklist on all new articles
        // ═══════════════════════════════════════════════════════
        Log::info('AutoContentPipeline: Step 7 — SEO checklists');

        GeneratedArticle::where('language', 'fr')
            ->whereNull('parent_article_id')
            ->whereDoesntHave('seoChecklist')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->chunkById(50, function ($articles) {
                foreach ($articles as $article) {
                    try {
                        $this->seoChecklist->evaluate($article);
                    } catch (\Throwable $e) {
                        // Non-blocking
                    }
                }
            });

        $summary['completed_at'] = now()->toIso8601String();
        $summary['duration_seconds'] = now()->diffInSeconds($summary['started_at']);

        Log::info('AutoContentPipeline: completed', $summary);

        return $summary;
    }

    /**
     * Process a single topic cluster end-to-end.
     */
    private function processTopicCluster(TopicCluster $cluster, int $minQuality): GeneratedArticle|string
    {
        // Dedup check
        $existing = $this->dedup->findDuplicateArticle($cluster->name, $cluster->country ?? '', 'fr');
        if ($existing) {
            $cluster->update(['generated_article_id' => $existing->id, 'status' => 'completed']);
            Log::info('AutoContentPipeline: dedup skip', ['cluster' => $cluster->name, 'existing' => $existing->id]);
            return 'dedup';
        }

        // Generate research brief
        if (!$cluster->researchBrief) {
            $this->researchBrief->generateBrief($cluster);
            $cluster->refresh();
        }

        // Generate article
        $cluster->update(['status' => 'generating']);

        $brief = $cluster->researchBrief;
        $params = [
            'topic' => $cluster->name,
            'language' => 'fr',
            'country' => $cluster->country,
            'content_type' => 'article',
            'keywords' => (function () use ($brief) {
                $primaryKeywords = $brief?->suggested_keywords['primary'] ?? [];
                if (is_string($primaryKeywords)) $primaryKeywords = [$primaryKeywords];
                return $primaryKeywords;
            })(),
            'cluster_id' => $cluster->id,
            'tone' => 'professional',
            'length' => 'long',
            'generate_faq' => true,
            'faq_count' => 10,
            'research_sources' => true,
            'image_source' => 'unsplash',
            'auto_internal_links' => true,
            'auto_affiliate_links' => true,
            'translation_languages' => [], // No auto-translation
        ];

        $article = $this->articleGeneration->generate($params);

        // Plagiarism check
        $plagResult = $this->plagiarism->check($article);
        if (!$plagResult['is_original']) {
            Log::warning('AutoContentPipeline: high plagiarism', [
                'article' => $article->id,
                'similarity' => $plagResult['similarity_percent'],
            ]);
            if ($plagResult['similarity_percent'] > 40) {
                // Too similar — regenerate with stronger reformulation instruction
                $article->update(['status' => 'draft', 'quality_score' => 0]);
                $cluster->update(['status' => 'ready']); // Reset for manual review
                return 'plagiarism';
            }
        }

        $cluster->update(['generated_article_id' => $article->id, 'status' => 'completed']);

        return $article;
    }

    /**
     * Process a single question cluster end-to-end.
     */
    private function processQuestionCluster(QuestionCluster $qCluster, int $minQuality): GeneratedArticle|string
    {
        $existing = $this->dedup->findDuplicateArticle($qCluster->name, $qCluster->country ?? '', 'fr');
        if ($existing) {
            $qCluster->update(['generated_article_id' => $existing->id, 'status' => 'completed']);
            return 'dedup';
        }

        $qCluster->update(['status' => 'generating_article']);

        $article = $this->articleFromQuestions->generateFromCluster($qCluster);

        // Plagiarism check
        $plagResult = $this->plagiarism->check($article);
        if (!$plagResult['is_original'] && $plagResult['similarity_percent'] > 40) {
            $article->update(['status' => 'draft', 'quality_score' => 0]);
            $qCluster->update(['status' => 'pending']);
            return 'plagiarism';
        }

        return $article;
    }

    /**
     * Get distinct country+category combos with unprocessed articles.
     */
    private function getUnprocessedCombos(?string $country, ?string $category): array
    {
        $query = ContentArticle::query()
            ->whereIn('processing_status', ['unprocessed', null])
            ->whereNotNull('content_text')
            ->where('word_count', '>', 100)
            ->join('content_countries', 'content_articles.country_id', '=', 'content_countries.id')
            ->selectRaw('content_countries.slug as country, content_articles.category')
            ->whereNotNull('content_articles.category')
            ->groupBy('content_countries.slug', 'content_articles.category');

        if ($country) {
            $query->where('content_countries.slug', $country);
        }
        if ($category) {
            $query->where('content_articles.category', $category);
        }

        return $query->get()->map(fn ($row) => [
            'country' => $row->country,
            'category' => $row->category,
        ])->toArray();
    }

    /**
     * Get distinct country slugs with unprocessed questions.
     */
    private function getUnprocessedQuestionCountries(?string $country): array
    {
        if ($country) {
            return [$country];
        }

        return ContentQuestion::where('article_status', 'new')
            ->whereNotNull('country_slug')
            ->distinct()
            ->pluck('country_slug')
            ->toArray();
    }
}
