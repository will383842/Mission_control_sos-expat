<?php

namespace App\Services\Content;

use App\Models\AffiliateLink;
use App\Models\ContentCampaignItem;
use App\Models\ContentGenerationCampaign;
use App\Models\ExternalLinkRegistry;
use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Models\GeneratedArticleSource;
use App\Models\GenerationLog;
use App\Models\PromptTemplate;
use App\Models\ResearchBrief;
use App\Models\TopicCluster;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\Content\SeoChecklistService;
use App\Services\PerplexitySearchService;
use App\Services\Seo\GeoMetaService;
use App\Services\Seo\HreflangService;
use App\Services\Seo\InternalLinkingService;
use App\Services\Seo\JsonLdService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Article generation orchestrator — 15-phase pipeline.
 * Coordinates AI, SEO, and content services to produce full articles.
 */
class ArticleGenerationService
{
    private string $kbPrompt = '';

    public function __construct(
        private OpenAiService $openAi,
        private ClaudeService $claude,
        private UnsplashService $unsplash,
        private PerplexitySearchService $perplexity,
        private SeoAnalysisService $seoAnalysis,
        private HreflangService $hreflang,
        private JsonLdService $jsonLd,
        private SlugService $slugService,
        private InternalLinkingService $internalLinking,
        private GeoMetaService $geoMeta,
        private KnowledgeBaseService $knowledgeBase,
        private GenerationGuardService $guard,
        private QualityGuardService $qualityGuard,
        private GenerationSchedulerService $scheduler,
    ) {}

    /**
     * Default search intent per content type.
     * Google ranks content higher when it matches search intent (since 2023).
     */
    private static function defaultIntent(string $contentType): string
    {
        return match ($contentType) {
            'guide', 'guide_city', 'pillar'              => 'informational',
            'article', 'tutorial', 'statistics'           => 'informational',
            'qa', 'qa_needs'                              => 'informational',
            'comparative', 'affiliation'                  => 'commercial_investigation',
            'testimonial'                                 => 'informational',
            'outreach'                                    => 'transactional',
            default                                       => 'informational',
        };
    }

    /**
     * Generate a full article through the 15-phase pipeline.
     */
    public function generate(array $params): GeneratedArticle
    {
        $startTime = microtime(true);

        // Load content-type-specific AI configuration (with search intent override)
        $contentType = $params['content_type'] ?? 'article';
        $searchIntent = $params['search_intent'] ?? $params['intent'] ?? self::defaultIntent($contentType);
        $typeConfig = ContentTypeConfig::withIntent($contentType, $searchIntent);
        $language = $params['language'] ?? 'fr';
        $country = $params['country'] ?? null;

        // Rate limiting check
        if (empty($params['force_generate'])) {
            $scheduleCheck = $this->scheduler->canGenerate($contentType);
            if (!$scheduleCheck['allowed']) {
                throw new \RuntimeException("Rate limit: {$scheduleCheck['reason']}");
            }
        }

        // Load Knowledge Base prompt with search intent (injected into all AI phases)
        $this->kbPrompt = $this->knowledgeBase->getSystemPrompt($contentType, $country, $language, $searchIntent);

        // Generation Guard: dedup + cross-source check
        if (empty($params['force_generate'])) {
            $guardResult = $this->guard->check(
                $params['topic'] ?? $params['title'] ?? '',
                $contentType,
                $language,
                $country,
            );

            if ($guardResult['status'] === 'block') {
                Log::warning('ArticleGenerationService: blocked by GenerationGuard', [
                    'topic' => $params['topic'] ?? '',
                    'reason' => $guardResult['reason'],
                ]);
                // Return existing article if available
                if ($guardResult['existing_id']) {
                    $existing = GeneratedArticle::find($guardResult['existing_id']);
                    if ($existing) {
                        return $existing;
                    }
                }
                throw new \RuntimeException("Generation blocked: {$guardResult['reason']}");
            }
        }

        // 1. Use pre-created article if article_id provided, otherwise create new
        if (!empty($params['article_id'])) {
            $article = GeneratedArticle::findOrFail($params['article_id']);
            $article->update(['status' => 'generating']);
        } else {
            $uuid = (string) Str::uuid();
            $article = GeneratedArticle::create([
                'uuid' => $uuid,
                'title' => $params['topic'] ?? 'Untitled',
                'slug' => Str::slug($params['topic'] ?? 'untitled') . '-' . substr($uuid, 0, 8),
                'language' => $params['language'] ?? 'fr',
                'country' => $params['country'] ?? null,
                'content_type' => $params['content_type'] ?? 'article',
                'keywords_primary' => $params['keywords'][0] ?? '',
                'keywords_secondary' => array_slice($params['keywords'] ?? [], 1),
                'status' => 'generating',
                'generation_preset_id' => $params['preset_id'] ?? null,
                'created_by' => $params['created_by'] ?? null,
                'source_slug'   => $params['source_slug']   ?? null,
                'input_quality' => $params['input_quality'] ?? null,
            ]);
        }

        Log::info('Article generation started', [
            'article_id' => $article->id,
            'topic' => $params['topic'] ?? '',
            'language' => $params['language'] ?? 'fr',
        ]);

        try {
            // Phase 1: Validate
            $phaseStart = microtime(true);
            $validated = $this->phase01_validate($params);
            $this->logPhase($article, 'validate', 'success', null, 0, 0, $this->elapsed($phaseStart));

            // Phase 2: Research
            // Decision tree (in priority order):
            //   1. Cluster         → extract facts from TopicCluster data
            //   2. source_content  → extract facts from scraped article (+ optional Perplexity enrich)
            //   3. research_depth = 'none' or (title_only + depth = 'light') → skip Perplexity (cost saving)
            //   4. Default         → full Perplexity research
            $phaseStart     = microtime(true);
            $researchDepth  = $typeConfig['research_depth']  ?? 'standard';
            $inputQuality   = $params['input_quality']        ?? null;
            $skipPerplexity = $researchDepth === 'none'
                || ($researchDepth === 'light' && $inputQuality === 'title_only');

            if (!empty($params['cluster_id'])) {
                $research = $this->phase02_researchFromCluster((int) $params['cluster_id']);
            } elseif (!empty($params['source_content'])) {
                $research = $this->phase02_researchFromSourceContent(
                    $params['topic'],
                    $params['source_content'],
                    $params['language'] ?? 'fr',
                    $params['country'] ?? null,
                    $researchDepth
                );
            } elseif ($skipPerplexity) {
                // Light/outreach sources with no scraped content — GPT generates from its own knowledge
                Log::info('ArticleGenerationService: skipping Perplexity (research_depth=' . $researchDepth . ', input_quality=' . $inputQuality . ')', [
                    'topic' => $params['topic'] ?? '',
                ]);
                $research = ['facts' => [], 'sources' => [], 'lsi_keywords' => []];
            } else {
                $research = $this->phase02_research(
                    $params['topic'],
                    $params['language'] ?? 'fr',
                    $params['country'] ?? null
                );
            }
            $this->logPhase($article, 'research', 'success', 'Found ' . count($research['facts']) . ' facts', 0, 0, $this->elapsed($phaseStart));

            // Store LSI keywords (merge with existing secondary keywords)
            $lsiKeywords = $research['lsi_keywords'] ?? [];
            if (!empty($lsiKeywords)) {
                $existingSecondary = $article->keywords_secondary ?? [];
                $merged = array_unique(array_merge($existingSecondary, $lsiKeywords));
                $article->update(['keywords_secondary' => $merged]);
            }

            // Phase 3: Generate title
            $phaseStart = microtime(true);
            $title = $this->phase03_generateTitle(
                $params['topic'],
                $research['facts'],
                $params['language'] ?? 'fr',
                $params['keywords'] ?? [],
                $typeConfig,
                $params['country'] ?? null,
                $contentType
            );
            $article->update(['title' => $title]);
            $this->logPhase($article, 'title', 'success', $title, 0, 0, $this->elapsed($phaseStart));

            // Phase 4: Generate excerpt
            $phaseStart = microtime(true);
            $excerpt = $this->phase04_generateExcerpt(
                $title,
                $research['facts'],
                $params['language'] ?? 'fr'
            );
            $article->update(['excerpt' => $excerpt]);
            $this->logPhase($article, 'excerpt', 'success', null, 0, 0, $this->elapsed($phaseStart));

            // Phase 5: Generate content (the big one)
            $phaseStart = microtime(true);
            // Pass LSI keywords to content generation
            $params['lsi_keywords'] = $lsiKeywords;
            $contentHtml = $this->phase05_generateContent($title, $excerpt, $research['facts'], $params, $typeConfig);
            $article->update([
                'content_html' => $contentHtml,
                'word_count' => $this->seoAnalysis->countWords($contentHtml),
                'reading_time_minutes' => max(1, (int) ceil($this->seoAnalysis->countWords($contentHtml) / 250)),
            ]);
            $this->logPhase($article, 'content', 'success', 'Generated ' . $article->word_count . ' words', 0, 0, $this->elapsed($phaseStart));

            // Phase 5b: Featured snippet definition paragraph
            $this->phase05b_featuredSnippet($article, $params['keywords'][0] ?? $params['topic'], $params['language'] ?? 'fr');

            // Phase 6: Generate FAQ
            try {
                $effectiveFaqCount = $params['faq_count'] ?? $typeConfig['faq_count'] ?? 6;
                if (($params['generate_faq'] ?? true) && $effectiveFaqCount > 0) {
                    $phaseStart = microtime(true);
                    $faqs = $this->phase06_generateFaq(
                        $title,
                        $contentHtml,
                        $params['language'] ?? 'fr',
                        $effectiveFaqCount,
                        $params['country'] ?? null
                    );
                    if (!empty($faqs)) {
                        foreach ($faqs as $index => $faq) {
                            GeneratedArticleFaq::create([
                                'article_id' => $article->id,
                                'question' => $faq['question'] ?? '',
                                'answer' => $faq['answer'] ?? '',
                                'sort_order' => $index,
                            ]);
                        }
                    }
                    $this->logPhase($article, 'faq', 'success', count($faqs) . ' FAQs generated', 0, 0, $this->elapsed($phaseStart));
                }
            } catch (\Throwable $e) {
                Log::warning('Phase 6 (FAQ) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('6 — FAQs', $e->getMessage(), $article);
            }

            // Phase 7: Generate meta tags
            $phaseStart = microtime(true);
            $meta = $this->phase07_generateMeta(
                $title,
                $excerpt,
                $params['keywords'][0] ?? '',
                $params['language'] ?? 'fr'
            );
            $article->update([
                'meta_title' => $meta['meta_title'],
                'meta_description' => $meta['meta_description'],
            ]);
            $this->logPhase($article, 'meta', 'success', null, 0, 0, $this->elapsed($phaseStart));

            // Phase 7b: Generate OG meta + AI summary (AEO)
            $phaseStart = microtime(true);
            $aeoMeta = $this->phase07b_generateAeoMeta(
                $title,
                $meta['meta_title'],
                $meta['meta_description'],
                $excerpt,
                $article->content_text ?? strip_tags($article->content_html ?? ''),
                $params['keywords'][0] ?? '',
                $params['language'] ?? 'fr'
            );
            // Build geo/OG/Twitter extended meta fields
            $geoUpdate = [
                'og_title'         => $aeoMeta['og_title'],
                'og_description'   => $aeoMeta['og_description'],
                'ai_summary'       => $aeoMeta['ai_summary'],
                'og_type'          => 'article',
                'og_locale'        => GeoMetaService::OG_LOCALE_MAP[$params['language'] ?? 'fr'] ?? 'fr_FR',
                'og_site_name'     => 'SOS Expat & Travelers',
                'twitter_card'     => 'summary_large_image',
                'content_language' => $params['language'] ?? 'fr',
                'meta_keywords'    => implode(', ', array_filter(array_slice($params['keywords'] ?? [], 0, 5))),
                'last_reviewed_at' => now(),
            ];
            if (!empty($params['country'])) {
                $geoUpdate['geo_region']    = $this->geoMeta->getGeoRegion($params['country']);
                $geoUpdate['geo_placename'] = $this->geoMeta->getGeoPlacename($params['country'], $params['language'] ?? 'fr');
                $geoUpdate['geo_position']  = $this->geoMeta->getGeoPosition($params['country']);
                $geoUpdate['icbm']          = $this->geoMeta->getIcbm($params['country']);
            }
            $article->update($geoUpdate);
            $this->logPhase($article, 'aeo_meta', 'success', null, 0, 0, $this->elapsed($phaseStart));

            // Phase 8: Generate JSON-LD
            $phaseStart = microtime(true);
            $jsonLdData = $this->phase08_generateJsonLd($article->fresh());
            $article->update(['json_ld' => $jsonLdData]);
            $this->logPhase($article, 'jsonld', 'success', null, 0, 0, $this->elapsed($phaseStart));

            // ═══ CORE CONTENT COMPLETE ═══
            // Mark as review NOW — all enhancement phases below are non-blocking
            $article->update(['status' => 'review']);
            Log::info('Article core content complete, status set to review', ['article_id' => $article->id]);

            // Phases 9-14: Enhancement phases (non-blocking — article content is already saved)
            // If any of these fail, the article still has its core content

            // Phase 9: Add internal links
            try {
                if ($params['auto_internal_links'] ?? true) {
                    $phaseStart = microtime(true);
                    $contentHtml = $this->phase09_addInternalLinks($article->fresh());
                    $article->update(['content_html' => $contentHtml]);
                    $this->logPhase($article, 'internal_links', 'success', null, 0, 0, $this->elapsed($phaseStart));
                }
            } catch (\Throwable $e) {
                Log::warning('Phase 9 (internal links) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('9 — Liens internes', $e->getMessage(), $article);
            }

            // Phase 10: Add external links
            try {
                $phaseStart = microtime(true);
                $contentHtml = $this->phase10_addExternalLinks($article->fresh(), $research['sources']);
                $article->update(['content_html' => $contentHtml]);
                $this->logPhase($article, 'external_links', 'success', null, 0, 0, $this->elapsed($phaseStart));
            } catch (\Throwable $e) {
                Log::warning('Phase 10 (external links) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('10 — Liens externes', $e->getMessage(), $article);
            }

            // Phase 11: Add affiliate links
            try {
                if ($params['auto_affiliate_links'] ?? true) {
                    $phaseStart = microtime(true);
                    $contentHtml = $this->phase11_addAffiliateLinks($article->fresh());
                    $article->update(['content_html' => $contentHtml]);
                    $this->logPhase($article, 'affiliate_links', 'success', null, 0, 0, $this->elapsed($phaseStart));
                }
            } catch (\Throwable $e) {
                Log::warning('Phase 11 (affiliate links) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('11 — Liens affiliés', $e->getMessage(), $article);
            }

            // Phase 12: Add images
            try {
                $phaseStart = microtime(true);
                $this->phase12_addImages($article->fresh(), $params['image_source'] ?? 'unsplash');
                $this->logPhase($article, 'images', 'success', null, 0, 0, $this->elapsed($phaseStart));
            } catch (\Throwable $e) {
                Log::warning('Phase 12 (images) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('12 — Images Unsplash', $e->getMessage(), $article);
            }

            // Ensure featured_image_url is set from images relation if not already
            $article->refresh();
            if (!$article->featured_image_url) {
                $firstImage = $article->images()->first();
                if ($firstImage) {
                    $article->update([
                        'featured_image_url' => $firstImage->url,
                        'featured_image_alt' => $firstImage->alt_text,
                        'featured_image_attribution' => $firstImage->attribution ?? null,
                    ]);
                    Log::info('Featured image fallback: set from first image in relation', ['article_id' => $article->id]);
                }
            }

            // Phase 13: Generate slugs + canonical
            try {
                $phaseStart = microtime(true);
                $this->phase13_generateSlugs($article->fresh());
                $this->logPhase($article, 'slugs', 'success', null, 0, 0, $this->elapsed($phaseStart));

                $article->refresh();
                $siteUrl = config('services.blog.site_url', config('services.site.url', 'https://sos-expat.com'));
                $canonical = rtrim($siteUrl, '/') . '/' . $article->language . '/articles/' . $article->slug;
                $article->update([
                    'canonical_url' => $canonical,
                    'og_url'        => $canonical,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Phase 13 (slugs) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('13 — Slugs/Canonical', $e->getMessage(), $article);
            }

            // Status already set to 'review' after phase 8

            // Phase 14: Calculate quality (non-blocking — article is already in review)
            $phaseStart = microtime(true);
            try {
                $this->phase14_calculateQuality($article->fresh());
                $this->logPhase($article, 'quality', 'success', null, 0, 0, $this->elapsed($phaseStart));
            } catch (\Throwable $e) {
                Log::warning('Phase 14: quality calculation failed (non-blocking)', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
                $this->sendTelegramAlert('14 — Scores SEO/Qualité', $e->getMessage(), $article);
                $this->logPhase($article, 'quality', 'warning', $e->getMessage(), 0, 0, $this->elapsed($phaseStart));
            }

            // Plagiarism check
            $dedup = app(DeduplicationService::class);
            $plagiarismResult = $dedup->checkContentOriginality($article);
            if (!$plagiarismResult['is_original']) {
                Log::warning('ArticleGenerationService: high similarity detected', [
                    'article_id' => $article->id,
                    'similarity' => $plagiarismResult['similarity_percent'],
                    'matches' => collect($plagiarismResult['matches'])->pluck('article_title')->toArray(),
                ]);
                // Don't block, just log + lower quality score
                $article->update([
                    'quality_score' => max(0, ($article->quality_score ?? 0) - 15),
                ]);
            }

            // Phase 15: Auto-dispatch translations to all 8 target languages
            try {
                $targetLanguages = ['en', 'es', 'de', 'pt', 'ru', 'zh', 'ar', 'hi'];
                $article->refresh();
                $this->phase15_dispatchTranslations($article, $targetLanguages);
                Log::info('Phase 15: translations dispatched for 8 languages', ['article_id' => $article->id]);
            } catch (\Throwable $e) {
                Log::warning('Phase 15 (translations) failed, continuing', ['error' => $e->getMessage(), 'article_id' => $article->id]);
                $this->sendTelegramAlert('15 — Traductions', $e->getMessage(), $article);
            }

            // Mark source item as used
            if (!empty($params['source_item_id'])) {
                \Illuminate\Support\Facades\DB::table('generation_source_items')
                    ->where('id', $params['source_item_id'])
                    ->update([
                        'processing_status' => 'used',
                        'used_count'        => \Illuminate\Support\Facades\DB::raw('used_count + 1'),
                        'updated_at'        => now(),
                    ]);
            }

            // Update cluster if generated from one
            if (!empty($params['cluster_id'])) {
                TopicCluster::where('id', $params['cluster_id'])->update([
                    'generated_article_id' => $article->id,
                    'status' => 'generated',
                ]);

                // Lock all source content_articles used by this cluster
                $clusterArticleIds = \App\Models\TopicClusterArticle::where('cluster_id', $params['cluster_id'])
                    ->pluck('source_article_id');

                if ($clusterArticleIds->isNotEmpty()) {
                    \App\Models\ContentArticle::whereIn('id', $clusterArticleIds)
                        ->update(['processing_status' => 'used', 'processed_at' => now()]);
                }
            }

            // Link back to campaign item if this was generated from a campaign
            if (!empty($params['campaign_item_id'])) {
                ContentCampaignItem::where('id', $params['campaign_item_id'])->update([
                    'itemable_type' => GeneratedArticle::class,
                    'itemable_id' => $article->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                // Update campaign counters
                ContentGenerationCampaign::where('id', $params['campaign_id'] ?? 0)->increment('completed_items');
            }

            $totalDuration = (int) ((microtime(true) - $startTime) * 1000);
            $article->refresh();
            Log::info('Article generation complete', [
                'article_id' => $article->id,
                'title' => $article->title,
                'duration_ms' => $totalDuration,
                'word_count' => $article->word_count,
                'seo_score' => $article->seo_score,
                'quality_score' => $article->quality_score,
            ]);

            // Post-generation quality check + auto-optimization
            try {
                $autoOptimizer = app(AutoOptimizeService::class);
                $optimResult = $autoOptimizer->evaluateAndOptimize($article->fresh());

                // Store optimization metadata
                $article->refresh();
                $article->update([
                    'quality_score' => $optimResult['final_score'],
                    'generation_notes' => json_encode([
                        'action' => $optimResult['action'],
                        'original_score' => $optimResult['original_score'],
                        'final_score' => $optimResult['final_score'],
                        'passes' => $optimResult['passes'],
                        'improvements' => $optimResult['improvements'],
                    ]),
                ]);

                Log::info("AutoOptimize result: {$optimResult['action']}", [
                    'article_id' => $article->id,
                    'score' => "{$optimResult['original_score']}→{$optimResult['final_score']}",
                    'passes' => $optimResult['passes'],
                ]);
            } catch (\Throwable $e) {
                Log::error('AutoOptimize failed', ['error' => $e->getMessage()]);
            }

            // Record generation for rate limiting
            $this->scheduler->recordGeneration($contentType, $article->generation_cost_cents ?? 0);

            // Auto-generate Q/R satellites (3 questions per article for topical clustering)
            // Dispatched async — doesn't block the current generation
            if (!in_array($contentType, ['qa', 'news'], true)) {
                try {
                    \App\Jobs\GenerateQrSatellitesJob::dispatch($article->id)->onQueue('content-worker')->delay(now()->addMinutes(2));
                    Log::info('Q/R satellites dispatched', ['article_id' => $article->id]);
                } catch (\Throwable $e) {
                    Log::warning('Q/R satellites dispatch failed (non-blocking)', ['error' => $e->getMessage()]);
                }
            }

            // Send Telegram success notification
            $this->sendTelegramSuccess($article);

            return $article->fresh();
        } catch (\Throwable $e) {
            Log::error('Article generation failed', [
                'article_id' => $article->id,
                'phase' => 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Save partial content as draft
            $article->update(['status' => 'draft']);

            $this->logPhase($article, 'pipeline', 'error', $e->getMessage());
            $this->sendTelegramAlert('PIPELINE COMPLET (échec critique)', $e->getMessage(), $article);

            return $article->fresh();
        }
    }

    // ============================================================
    // Pipeline Phases
    // ============================================================

    private function phase01_validate(array $params): array
    {
        $errors = [];

        if (empty($params['topic'])) {
            $errors[] = 'Topic is required';
        }

        if (empty($params['language'])) {
            $errors[] = 'Language is required';
        }

        // Validate content_type against known types
        $validTypes = [
            'guide', 'pillar', 'guide_city',
            'article', 'tutorial', 'news',
            'comparative', 'qa', 'qa_needs',
            'testimonial',
            'outreach', 'affiliation',
            'landing', 'press', 'press_release',
        ];
        if (!empty($params['content_type']) && !in_array($params['content_type'], $validTypes, true)) {
            $errors[] = 'Invalid content_type "' . $params['content_type'] . '". Must be one of: ' . implode(', ', $validTypes);
        }

        // Validate country if provided (must be non-empty string or null)
        if (array_key_exists('country', $params) && !is_null($params['country']) && empty(trim((string) $params['country']))) {
            $errors[] = 'Country must be a non-empty string or null';
        }

        // At least one keyword is recommended
        if (empty($params['keywords'])) {
            Log::warning('ArticleGeneration: no keywords provided — SEO quality will be degraded');
        }

        // Validate that at least one AI service is configured
        if (!$this->openAi->isConfigured() && !$this->claude->isConfigured()) {
            $errors[] = 'No AI API key configured (OpenAI or Anthropic required)';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        return $params;
    }

    /**
     * Phase 2 variant: extract research facts from existing scraped content.
     * Used for full_content items — faster and cheaper than Perplexity.
     * Still calls Perplexity to enrich with recent data if configured.
     */
    /**
     * Phase 2 variant: extract research facts from existing scraped content.
     * Used for full_content items — faster and cheaper than pure Perplexity.
     *
     * - Always: GPT extracts facts + LSI from the source text (cheap, fast)
     * - If research_depth = 'deep' or 'standard': also calls Perplexity for recent data enrichment
     * - If research_depth = 'light' or 'none': skip Perplexity (source content is enough)
     */
    private function phase02_researchFromSourceContent(
        string $topic,
        string $sourceContent,
        string $language,
        ?string $country,
        string $researchDepth = 'standard'
    ): array {
        $facts   = [];
        $sources = [];

        // Step 1 — GPT extracts key facts from the scraped content (always done)
        $truncated     = mb_substr(strip_tags($sourceContent), 0, 4000);
        $extractResult = $this->openAi->complete(
            $this->kbPrompt . "\n\nTu es un assistant de recherche. Extrais les faits clés, chiffres, données importantes "
            . "et points essentiels de ce contenu. "
            . "Retourne en JSON : {\"facts\": [\"fait1\", \"fait2\", ...], \"lsi_keywords\": [\"mot1\", \"mot2\", ...]}",
            "Contenu source :\n{$truncated}",
            ['temperature' => 0.3, 'max_tokens' => 1000, 'json_mode' => true]
        );

        $lsiKeywords = [];
        if ($extractResult['success']) {
            $data        = json_decode($extractResult['content'], true);
            $facts       = $data['facts']       ?? [];
            $lsiKeywords = $data['lsi_keywords'] ?? [];
        }

        // Step 2 — Perplexity enrichment (only for deep/standard research depth)
        $enrichWithPerplexity = in_array($researchDepth, ['deep', 'standard'], true)
            && $this->perplexity->isConfigured();

        if ($enrichWithPerplexity) {
            $countryCtx = $country ? " en {$country}" : '';
            $query      = "Données récentes {$countryCtx} sur : \"{$topic}\". "
                        . "Statistiques " . date('Y') . ", lois en vigueur, informations pratiques à jour.";

            $result = $this->perplexity->search($query, $language);
            if ($result['success'] && !empty($result['text'])) {
                foreach (explode("\n", $result['text']) as $line) {
                    $line = trim($line);
                    if (mb_strlen($line) > 20) {
                        $facts[] = $line;
                    }
                }
                foreach ($result['citations'] ?? [] as $citation) {
                    $sources[] = ['url' => $citation, 'domain' => parse_url($citation, PHP_URL_HOST) ?? ''];
                }
            }
        }

        return ['facts' => $facts, 'sources' => $sources, 'lsi_keywords' => $lsiKeywords];
    }

    private function phase02_research(string $topic, string $language, ?string $country): array
    {
        $facts = [];
        $sources = [];

        if (!$this->perplexity->isConfigured()) {
            Log::warning('Perplexity not configured — skipping research phase');
            return ['facts' => [], 'sources' => []];
        }

        $countryContext = $country ? " en {$country}" : '';
        $query = "Tu es un chercheur web. Recherche des informations factuelles, récentes et fiables sur le sujet suivant{$countryContext}: \"{$topic}\". "
            . "Retourne: les faits clés, les statistiques, les sources fiables, les points importants à couvrir. "
            . "Inclus aussi une liste de 10-15 mots-clés sémantiques (LSI) liés au sujet — des mots que Google s'attend à trouver dans un article complet sur ce thème.";

        $result = $this->perplexity->search($query, $language);

        $lsiKeywords = [];

        if ($result['success'] && !empty($result['text'])) {
            // Parse facts from response
            $lines = explode("\n", $result['text']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && mb_strlen($line) > 20) {
                    $facts[] = $line;
                }
            }

            // Extract citations as sources
            foreach ($result['citations'] ?? [] as $citation) {
                $sources[] = [
                    'url' => $citation,
                    'domain' => parse_url($citation, PHP_URL_HOST) ?? '',
                ];
            }

            // Extract LSI keywords from the research
            try {
                $lsiResult = $this->openAi->complete(
                    "Extrais 10-15 mots-clés sémantiques (LSI) de ce texte de recherche. Ce sont des termes que Google s'attend à trouver dans un article complet sur le sujet. Retourne en JSON: {\"lsi_keywords\": [\"mot1\", \"mot2\", ...]}",
                    mb_substr($result['text'], 0, 3000),
                    ['temperature' => 0.3, 'max_tokens' => 300, 'json_mode' => true]
                );
                if ($lsiResult['success']) {
                    $lsiData = json_decode($lsiResult['content'], true);
                    $lsiKeywords = $lsiData['lsi_keywords'] ?? [];
                }
            } catch (\Throwable $e) {
                Log::warning('ArticleGeneration: LSI extraction failed (non-blocking)', ['error' => $e->getMessage()]);
            }
        }

        // Save sources to database
        if (!empty($sources)) {
            // We'll save them after the article is created — handled in generate()
        }

        return ['facts' => $facts, 'sources' => $sources, 'lsi_keywords' => $lsiKeywords];
    }

    /**
     * Phase 2 alternative: load research data from an existing cluster + research brief.
     */
    private function phase02_researchFromCluster(int $clusterId): array
    {
        $cluster = TopicCluster::with(['researchBrief', 'clusterArticles'])->find($clusterId);

        if (!$cluster || !$cluster->researchBrief) {
            Log::warning('Cluster or brief not found, falling back to fresh research', ['cluster_id' => $clusterId]);
            return ['facts' => [], 'sources' => []];
        }

        $brief = $cluster->researchBrief;
        $facts = [];
        $sources = [];

        // Collect extracted facts from the brief
        foreach ($brief->extracted_facts ?? [] as $factSet) {
            foreach ($factSet['key_facts'] ?? [] as $fact) {
                $facts[] = $fact;
            }
            foreach ($factSet['statistics'] ?? [] as $stat) {
                $facts[] = $stat;
            }
            foreach ($factSet['procedures'] ?? [] as $proc) {
                $facts[] = $proc;
            }
        }

        // Add recent data from Perplexity research
        foreach ($brief->recent_data ?? [] as $item) {
            if (is_array($item)) {
                $facts[] = $item['fact'] ?? json_encode($item);
                if (!empty($item['source'])) {
                    $sources[] = ['url' => $item['source'], 'domain' => parse_url($item['source'], PHP_URL_HOST) ?? ''];
                }
            } else {
                $facts[] = (string) $item;
            }
        }

        // Add gap-related context
        foreach ($brief->identified_gaps ?? [] as $gap) {
            if (is_array($gap)) {
                $facts[] = 'Gap to cover: ' . ($gap['topic'] ?? $gap['description'] ?? json_encode($gap));
            }
        }

        Log::info('Research from cluster loaded', [
            'cluster_id' => $clusterId,
            'facts_count' => count($facts),
            'sources_count' => count($sources),
        ]);

        return ['facts' => $facts, 'sources' => $sources];
    }

    private function phase03_generateTitle(string $topic, array $facts, string $language, array $keywords, array $typeConfig = [], ?string $country = null, string $contentType = 'article'): string
    {
        $primaryKeyword = $keywords[0] ?? $topic;
        $year = date('Y');
        $factsContext = !empty($facts) ? "\n\nFaits de recherche:\n" . implode("\n", array_slice($facts, 0, 5)) : '';

        $template = $this->getPromptTemplate('article', 'title');

        $systemPrompt = $this->kbPrompt . "\n\n" . ($template
            ? $this->replaceVariables($template->system_message, ['language' => $language, 'year' => $year])
            : "Tu es un expert SEO senior spécialisé dans les titres à fort CTR. "
              . "Génère un titre d'article PARFAIT pour Google et les moteurs de recherche.\n\n"
              . "RÈGLES STRICTES pour le titre :\n"
              . "1. LONGUEUR : entre 50 et 60 caractères EXACTEMENT (Google tronque au-delà de 60)\n"
              . "2. MOT-CLÉ PRINCIPAL : \"{$primaryKeyword}\" doit apparaître dans les 3 PREMIERS MOTS du titre\n"
              . "3. ANNÉE : inclure \"{$year}\" dans le titre (signal de fraîcheur critique)\n"
              . "4. FORMAT PROUVÉ (choisir un de ces formats haute performance) :\n"
              . "   - \"{Mot-clé} : Complet {$year}\"\n"
              . "   - \"{Mot-clé} - {Chiffre} Étapes Essentielles ({$year})\"\n"
              . "   - \"{Mot-clé} : Tout Savoir en {$year}\"\n"
              . "   - \"{Mot-clé} {$year} : Conseils, Démarches et Astuces\"\n"
              . "   - \"Top {Chiffre} : {Mot-clé} en {$year}\"\n"
              . "5. POWER WORDS : utiliser un de ces mots qui augmentent le CTR : "
              . "Complet, Pratique, Essentiel, À Jour, Officiel, Ultime, Simple, Rapide\n"
              . "6. PAS de : clickbait, point d'exclamation, majuscules excessives, \"meilleur\" sans justification\n"
              . "7. NE COMMENCE PAS le titre par : \"Guide\", \"Article\", \"Comment\", \"Voici\"\n"
              . "7. UNIQUE : le titre ne doit pas être générique, il doit être spécifique au sujet\n\n"
              . "Langue: {$language}. Retourne UNIQUEMENT le titre, sans guillemets ni explication.");

        $userPrompt = "Sujet: {$topic}\nMot-clé principal: {$primaryKeyword}\nAnnée: {$year}{$factsContext}";

        // Force country name in title for country/city guide articles (anti-duplicate content)
        if ($country && in_array($contentType, ['guide', 'pillar', 'guide_city'], true)) {
            $countryName = $this->geoMeta->getGeoPlacename($country, $language);
            $userPrompt .= "\n\nOBLIGATOIRE : Le titre DOIT contenir explicitement le nom du pays ou de la ville '{$countryName}'. Exemple : 'Visa pour {$countryName} : Guide Complet {$year}'. Sans ce nom géographique le titre sera REJETÉ."
                . "\nGRAMMAIRE : Utiliser la BONNE préposition devant le nom de pays (en français : 'au Portugal', 'en France', 'aux États-Unis', 'à Singapour'). Le titre DOIT sonner 100% naturel et natif — JAMAIS de formulation maladroite.";
        }

        $result = $this->openAi->complete($systemPrompt, $userPrompt, [
            'model' => $typeConfig['model'] ?? null,
            'temperature' => $typeConfig['temperature'] ?? 0.6,
            'max_tokens' => $typeConfig['max_tokens_title'] ?? 100,
        ]);

        if ($result['success']) {
            $title = trim($result['content'], " \t\n\r\0\x0B\"'");

            // Validation: si le titre est trop long, on tronque intelligemment
            if (mb_strlen($title) > 60) {
                // Chercher le dernier espace avant 60 chars
                $truncated = mb_substr($title, 0, 60);
                $lastSpace = mb_strrpos($truncated, ' ');
                if ($lastSpace && $lastSpace > 40) {
                    $title = mb_substr($truncated, 0, $lastSpace);
                } else {
                    $title = $truncated;
                }
            }

            // Validation: vérifier que le mot-clé principal est présent
            if (!empty($primaryKeyword) && mb_stripos($title, mb_substr($primaryKeyword, 0, 15)) === false) {
                Log::warning('ArticleGeneration: title missing keyword, regenerating', [
                    'title' => $title, 'keyword' => $primaryKeyword,
                ]);
                // Forcer le mot-clé dans le titre
                $title = ucfirst($primaryKeyword) . ' : ' . $title;
                if (mb_strlen($title) > 60) {
                    $truncated = mb_substr($title, 0, 60);
                    $lastSpace = mb_strrpos($truncated, ' ');
                    $title = $lastSpace && $lastSpace > 40 ? mb_substr($truncated, 0, $lastSpace) : $truncated;
                }
            }

            // Validation: reject titles starting with forbidden words
            $forbiddenStarts = ['guide ', 'article ', 'comment ', 'voici '];
            $titleLower = mb_strtolower($title);
            foreach ($forbiddenStarts as $word) {
                if (str_starts_with($titleLower, $word)) {
                    // Prepend keyword to reframe the title
                    $title = ucfirst($primaryKeyword) . ' : ' . $title;
                    if (mb_strlen($title) > 60) {
                        $truncated = mb_substr($title, 0, 60);
                        $lastSpace = mb_strrpos($truncated, ' ');
                        $title = $lastSpace && $lastSpace > 40 ? mb_substr($truncated, 0, $lastSpace) : $truncated;
                    }
                    break;
                }
            }

            return $title;
        }

        // Fallback: titre structuré basique avec mot-clé + année (pas "Guide")
        $fallback = ucfirst($primaryKeyword) . ' : Tout Savoir en ' . $year;
        return mb_substr($fallback, 0, 60);
    }

    private function phase04_generateExcerpt(string $title, array $facts, string $language): string
    {
        $factsContext = !empty($facts) ? "\nFaits: " . implode('. ', array_slice($facts, 0, 3)) : '';

        $systemPrompt = $this->kbPrompt . "\n\nTu es un expert SEO. Génère un résumé de 2-3 phrases (150-200 caractères) qui servira d'EXCERPT pour l'article."
            . "\n\nRÈGLES STRICTES :"
            . "\n- LONGUEUR : entre 150 et 200 caractères EXACTEMENT (Google snippet + meta description)"
            . "\n- Commence directement par l'information clé (PAS 'Cet article traite de...')"
            . "\n- Contient le mot-clé principal et 1-2 faits importants"
            . "\n- Mentionne le pays et l'année si pertinent"
            . "\n- Ton factuel, informatif, donne envie de lire l'article complet"
            . "\nLangue: {$language}. Retourne UNIQUEMENT le texte, sans guillemets.";

        $result = $this->openAi->complete($systemPrompt, "Titre: {$title}{$factsContext}", [
            'temperature' => 0.5,
            'max_tokens' => 100,
        ]);

        if ($result['success']) {
            $excerpt = trim($result['content']);
            // Ensure it's within 150-200 chars; truncate at last word boundary if needed
            if (mb_strlen($excerpt) > 200) {
                $truncated = mb_substr($excerpt, 0, 200);
                $lastSpace = mb_strrpos($truncated, ' ');
                $excerpt = $lastSpace > 120 ? mb_substr($truncated, 0, $lastSpace) : $truncated;
            }
            return $excerpt;
        }

        // Fallback: build a minimal excerpt from the title rather than returning empty
        return mb_substr($title, 0, 150);
    }

    private function phase05_generateContent(string $title, string $excerpt, array $facts, array $params, array $typeConfig = []): string
    {
        $language = $params['language'] ?? 'fr';
        $tone = $params['tone'] ?? 'professional';
        $keywords = $params['keywords'] ?? [];
        $instructions = $params['instructions'] ?? '';
        $contentType = $params['content_type'] ?? 'article';

        // Target word counts — ambitious for world-class content
        $targetWords = $typeConfig['target_words_range'] ?? match ($params['length'] ?? $typeConfig['length'] ?? 'long') {
            'short' => '1500-2000',
            'medium' => '2000-3000',
            'long' => '3000-4500',
            'extra_long' => '4500-7000',
            default => '3000-4500',
        };

        $keywordsStr = !empty($keywords) ? implode(', ', $keywords) : '';
        $factsStr = !empty($facts) ? implode("\n- ", array_slice($facts, 0, 15)) : '';

        $template = $this->getPromptTemplate($contentType, 'content');

        $systemPrompt = $this->kbPrompt . "\n\n" . ($template
            ? $this->replaceVariables($template->system_message, [
                'language' => $language,
                'tone' => $tone,
                'target_words' => $targetWords,
                'year' => date('Y'),
            ])
            : "Tu es un journaliste expert et rédacteur SEO de classe mondiale. "
              . "Tu rédiges des articles de référence qui se classent #1 sur Google. "
              . "Langue: {$language}. Ton: {$tone}.\n\n"
              . "═══ EXIGENCE ABSOLUE DE LONGUEUR ═══\n"
              . "L'article DOIT contenir MINIMUM {$targetWords} mots. PAS MOINS.\n"
              . "Un article de moins de " . explode('-', $targetWords)[0] . " mots sera REJETÉ et tu devras le refaire.\n"
              . "Pour atteindre cette longueur :\n"
              . "- 8-12 sections <h2> avec chacune 3-5 paragraphes de 80-120 mots\n"
              . "- Des sous-sections <h3> dans les sections longues\n"
              . "- Des exemples concrets, des études de cas, des témoignages\n"
              . "- Des données chiffrées récentes (" . date('Y') . "), prix, statistiques\n"
              . "- Des tableaux comparatifs HTML (<table>) quand pertinent\n"
              . "- Des encadrés 'Bon à savoir' avec <blockquote>\n"
              . "- Des listes détaillées avec explications pour chaque point\n\n"
              . "═══ QUALITÉ WORLD-CLASS ═══\n"
              . "- Chaque affirmation doit être étayée par un fait, un chiffre ou une source\n"
              . "- Inclus des conseils pratiques que le lecteur peut appliquer immédiatement\n"
              . "- Anticipe les questions du lecteur et réponds-y dans le texte\n"
              . "- Utilise des transitions fluides entre les sections\n"
              . "- Varie le vocabulaire : évite les répétitions\n"
              . "- Écris pour un humain, pas pour un robot : sois engageant et utile\n\n"
              . "═══ STRUCTURE HTML ═══\n"
              . "- Balises: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <table>, <thead>, <tbody>, <tr>, <th>, <td>\n"
              . "- 8-12 sections <h2> (PAS de <h1>)\n"
              . "- Au moins 3 listes (<ul>/<ol>) avec 5+ items chacune\n"
              . "- Au moins 1 tableau comparatif (<table>) si le sujet s'y prête\n"
              . "- Au moins 2 <blockquote> pour les conseils importants\n"
              . "- Premier paragraphe : accroche + mot-clé principal + année " . date('Y') . "\n"
              . "- Dernier paragraphe : conclusion avec récapitulatif et appel à l'action\n"
              . "- Pas de <html>, <head>, <body> — seulement le contenu article\n\n"
              . "═══ SEO AVANCÉ ═══\n"
              . "- Mot-clé principal dans : premier paragraphe, 2+ titres H2, 1x en <strong>\n"
              . "- Densité mot-clé principal : 1-2%\n"
              . "- Mots-clés secondaires répartis naturellement (1 par section minimum)\n"
              . "- Phrases variées : 15-25 mots en moyenne, alterner courtes/longues\n"
              . "- Paragraphes de 3-5 lignes max\n"
              . "- Mentionne '" . date('Y') . "' dans les données chiffrées\n"
              . "- Dans la conclusion\n"
              . "- Densité totale : 1-2% (ni trop, ni trop peu)\n"
              . "Le mot-clé doit apparaître NATURELLEMENT — jamais forcé ou répétitif.");

        $userPrompt = "Titre: {$title}\n\n"
            . "Introduction (déjà rédigée, à intégrer):\n{$excerpt}\n\n"
            . (!empty($keywordsStr) ? "Mots-clés à intégrer: {$keywordsStr}\n\n" : '')
            . (!empty($factsStr) ? "Faits de recherche à utiliser:\n- {$factsStr}\n\n" : '')
            . (!empty($instructions) ? "Instructions supplémentaires: {$instructions}\n\n" : '')
            . "Année courante: " . date('Y') . ". Mentionne cette année dans le premier paragraphe et les données chiffrées.\n\n"
            . "Rédige l'article complet en HTML.";

        // Inject country geo context for guide/pillar/guide_city — prevents duplicate content across 197 countries
        if (!empty($params['country']) && in_array($contentType, ['guide', 'pillar', 'guide_city'], true)) {
            $geoContext = $this->geoMeta->buildCountryContextForPrompt($params['country'], $language);
            if (!empty($geoContext)) {
                $userPrompt = $geoContext . "\n\n" . $userPrompt;
            }
        }

        // Append content-type-specific instructions
        $promptSuffix = $typeConfig['prompt_suffix'] ?? '';
        if (!empty($promptSuffix)) {
            $userPrompt .= "\n\nINSTRUCTIONS SUPPLEMENTAIRES DU TYPE DE CONTENU:\n" . $promptSuffix;
        }

        // Reinforce H2 keyword rule for template-based prompts too
        $primaryKw = $keywords[0] ?? $params['topic'] ?? '';
        if (!empty($primaryKw)) {
            $userPrompt .= "\n\nRÈGLE H2 OBLIGATOIRE : Le mot-clé principal \"{$primaryKw}\" DOIT apparaître dans au moins 2 titres H2 sur les 6-8 (variantes/synonymes acceptés).";
        }

        // LSI keywords integration
        $lsiKeywords = $params['lsi_keywords'] ?? [];
        if (!empty($lsiKeywords)) {
            $lsiList = implode(', ', array_slice($lsiKeywords, 0, 15));
            $userPrompt .= "\n\nMOTS-CLÉS SÉMANTIQUES (LSI) à intégrer naturellement dans le texte :\n{$lsiList}\nCes mots doivent apparaître au moins 1 fois chacun dans l'article pour signaler à Google que l'article couvre le sujet en profondeur.";
        }

        // If source content is provided (full_content items), inject it as GPT reference
        $sourceContent = $params['source_content'] ?? null;
        if (!empty($sourceContent)) {
            $truncated = mb_substr(strip_tags($sourceContent), 0, 3000);
            $userPrompt .= "\n\n═══ CONTENU SOURCE À ENRICHIR ═══\n"
                . "Voici le contenu existant sur ce sujet. Ne le recopie PAS — utilise-le comme base "
                . "pour créer un article BIEN MEILLEUR : plus complet, mieux structuré, avec plus de données, "
                . "d'exemples concrets et de valeur ajoutée. Réécris et enrichis :\n\n"
                . $truncated;
        }

        // Token limits for world-class content (1 French word ≈ 1.8 tokens + HTML overhead ~30%)
        $maxTokens = $typeConfig['max_tokens_content'] ?? match ($params['length'] ?? $typeConfig['length'] ?? 'long') {
            'short' => 8000,       // ~2000 words + HTML
            'medium' => 12000,     // ~3000 words + HTML
            'long' => 16384,       // ~4500 words + HTML (GPT-4o max output)
            'extra_long' => 16384, // ~4500+ words (GPT-4o max)
            default => 16384,
        };

        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model' => $typeConfig['model'] ?? null,
            'temperature' => $typeConfig['temperature'] ?? 0.7,
            'max_tokens' => $maxTokens,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => null, // Will be set after creation
        ]);

        if ($result['success']) {
            $content = trim($result['content']);

            // Strip markdown code fences that GPT sometimes wraps HTML in
            $content = preg_replace('/^```(?:html)?\s*\n?/i', '', $content);
            $content = preg_replace('/\n?```\s*$/i', '', $content);

            // Strip full HTML page wrapper if GPT returned <html>/<head>/<body>/<style>
            $content = preg_replace('/<html[^>]*>|<\/html>/i', '', $content);
            $content = preg_replace('/<head>.*?<\/head>/is', '', $content);
            $content = preg_replace('/<body[^>]*>|<\/body>/i', '', $content);
            $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
            $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
            $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
            $content = preg_replace('/<title[^>]*>.*?<\/title>/is', '', $content);
            $content = preg_replace('/<meta[^>]*>/i', '', $content);
            $content = preg_replace('/<link[^>]*>/i', '', $content);

            // Remove any leftover <h1> (title is separate)
            $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content);

            $content = trim($content);

            // Check word count — if too short, retry with stronger instruction
            $wordCount = str_word_count(strip_tags($content));
            $minWords = (int) (((int) explode('-', $targetWords)[0]) * 0.7); // 70% of minimum target
            if ($wordCount < $minWords && $wordCount > 100) {
                Log::info("Phase 5: Content too short ({$wordCount} words, min {$minWords}), extending...", [
                    'article_topic' => $params['topic'] ?? '',
                ]);

                $extendPrompt = "L'article suivant fait seulement {$wordCount} mots. Il DOIT faire MINIMUM {$targetWords} mots. "
                    . "Réécris-le en le développant considérablement : ajoute des sections détaillées, des exemples concrets, "
                    . "des chiffres, des tableaux comparatifs, des conseils pratiques, des retours d'expérience. "
                    . "Chaque section <h2> doit contenir au minimum 3 paragraphes de 80+ mots chacun.\n\n"
                    . "ARTICLE À DÉVELOPPER :\n" . $content;

                $extendResult = $this->openAi->complete(
                    $this->kbPrompt . "\n\nTu es un rédacteur web expert. Développe cet article en HTML pour qu'il atteigne {$targetWords} mots minimum.",
                    $extendPrompt,
                    [
                        'model' => $typeConfig['model'] ?? null,
                        'temperature' => 0.8,
                        'max_tokens' => $maxTokens,
                    ]
                );

                if ($extendResult['success']) {
                    $extended = trim($extendResult['content']);
                    $extended = preg_replace('/^```(?:html)?\s*\n?/i', '', $extended);
                    $extended = preg_replace('/\n?```\s*$/i', '', $extended);
                    $extended = trim($extended);
                    $newWordCount = str_word_count(strip_tags($extended));
                    Log::info("Phase 5: Extended from {$wordCount} to {$newWordCount} words");
                    if ($newWordCount > $wordCount) {
                        return $extended;
                    }
                }
            }

            return $content;
        }

        throw new \RuntimeException('Content generation failed: ' . ($result['error'] ?? 'Unknown error'));
    }

    private function phase05b_featuredSnippet(GeneratedArticle $article, string $primaryKeyword, string $language): string
    {
        $startTime = microtime(true);

        $template = $this->getPromptTemplate('article', 'featured_snippet');

        $systemPrompt = $this->kbPrompt . "\n\n" . ($template
            ? $this->replaceVariables($template->system_message, ['topic' => $article->title, 'keyword' => $primaryKeyword, 'language' => $language])
            : "Tu es un expert SEO. Génère un paragraphe de définition de EXACTEMENT 40-60 mots qui répond directement à la question implicite du titre. Ce paragraphe doit commencer par une reformulation du sujet (ex: 'Le visa pour l'Allemagne est...'). Il sera utilisé comme featured snippet Google (Position 0). Langue: {$language}.");

        $userPrompt = $template
            ? $this->replaceVariables($template->user_message_template, [
                'title' => $article->title,
                'primary_keyword' => $primaryKeyword,
                'keyword' => $primaryKeyword,
                'context' => mb_substr(strip_tags($article->content_html ?? ''), 0, 500),
                'language' => $language,
                'year' => date('Y'),
            ])
            : "Titre de l'article: \"{$article->title}\"\nMot-clé principal: \"{$primaryKeyword}\"\nAnnée: " . date('Y') . "\n\nGénère UNIQUEMENT le paragraphe de définition (40-60 mots, pas de HTML, juste le texte).";

        $result = $this->openAi->complete($systemPrompt, $userPrompt, [
            'temperature' => 0.5,
            'max_tokens' => 200,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($result['success'] && !empty($result['content'])) {
            $snippet = trim($result['content']);
            // Wrap in paragraph with bold for emphasis
            $snippetHtml = '<p class="featured-snippet"><strong>' . e($snippet) . '</strong></p>';

            // Insert after first <h2>
            $html = $article->content_html;
            $pos = strpos($html, '</h2>');
            if ($pos !== false) {
                $html = substr($html, 0, $pos + 5) . "\n" . $snippetHtml . "\n" . substr($html, $pos + 5);
            } else {
                // No H2 found, insert at beginning
                $html = $snippetHtml . "\n" . $html;
            }

            $article->update(['content_html' => $html]);

            $this->logPhase($article, 'featured_snippet', 'completed', 'Definition paragraph added',
                $result['tokens_input'] + $result['tokens_output'], 0, $durationMs);
        } else {
            $this->logPhase($article, 'featured_snippet', 'failed', $result['error'] ?? 'Empty response', 0, 0, $durationMs);
        }

        return $article->content_html;
    }

    private function phase06_generateFaq(string $title, string $contentHtml, string $language, int $count = 8, ?string $country = null): array
    {
        $contentText = $this->seoAnalysis->extractTextFromHtml($contentHtml);
        $contentExcerpt = mb_substr($contentText, 0, 2000);

        // Force country name in every FAQ question — prevents generic duplicate FAQs across 197 countries
        $faqCountryConstraint = $country ? $this->geoMeta->buildFaqCountryConstraint($country, $language) : '';

        $systemPrompt = $this->kbPrompt . "\n\nTu es un expert SEO spécialisé en FAQ Schema. Génère exactement {$count} questions fréquemment posées "
            . "sur le sujet de l'article, avec des réponses détaillées (3-5 phrases chacune). "
            . "Langue: {$language}.\n\n"
            . "Retourne en JSON: [{\"question\": \"...\", \"answer\": \"...\"}]\n"
            . "Les questions doivent être celles que les utilisateurs taperaient réellement dans Google."
            . $faqCountryConstraint;

        $result = $this->openAi->complete($systemPrompt, "Titre: {$title}\n\nContenu (extrait):\n{$contentExcerpt}", [
            'temperature' => 0.6,
            'max_tokens' => 3000,
            'json_mode' => true,
        ]);

        $faqs = [];

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);

            // Handle different JSON structures
            $items = $parsed['faqs'] ?? $parsed['questions'] ?? $parsed ?? [];
            if (isset($items[0])) {
                $faqs = $items;
            }
        }

        return $faqs;
    }

    private function phase07_generateMeta(string $title, string $excerpt, string $primaryKeyword, string $language): array
    {
        $year = date('Y');
        $systemPrompt = $this->kbPrompt . "\n\nTu es un expert SEO senior. Génère un meta title ET une meta description parfaitement optimisés pour Google.\n"
            . "Langue: {$language}.\n\n"
            . "Retourne en JSON: {\"meta_title\": \"...\", \"meta_description\": \"...\"}\n\n"
            . "RÈGLES META TITLE (balise <title>) :\n"
            . "- EXACTEMENT 50-60 caractères (Google tronque au-delà)\n"
            . "- Le mot-clé principal \"{$primaryKeyword}\" DOIT apparaître dans les 3 premiers mots\n"
            . "- Inclure l'année {$year} (signal de fraîcheur)\n"
            . "- Ajouter le séparateur ' | SOS-Expat' à la fin si la place le permet\n"
            . "- Le meta_title PEUT être différent du H1 (plus concis, plus SEO)\n"
            . "- Power words qui augmentent le CTR : Guide, Complet, Pratique, Conseils, À Jour\n"
            . "- Format idéal : \"{Mot-clé} : {Bénéfice} {$year} | SOS-Expat\"\n\n"
            . "RÈGLES META DESCRIPTION :\n"
            . "- EXACTEMENT 140-155 caractères (Google tronque à ~155)\n"
            . "- Contient le mot-clé principal naturellement\n"
            . "- Commence par un verbe d'action (Découvrez, Apprenez, Trouvez, Consultez)\n"
            . "- Finit par un CTA (Guide complet, Conseils pratiques, Tout savoir)\n"
            . "- Mentionne un bénéfice concret pour l'utilisateur\n"
            . "- Pas de caractères spéciaux inutiles, pas de caps lock";

        $result = $this->openAi->complete($systemPrompt,
            "Titre: {$title}\nExcerpt: {$excerpt}\nMot-clé: {$primaryKeyword}", [
                'temperature' => 0.5,
                'max_tokens' => 300,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            $metaTitle = $parsed['meta_title'] ?? $title;
            $metaDesc = $parsed['meta_description'] ?? $excerpt;

            // Validation meta_title : tronquer intelligemment à 60 chars
            if (mb_strlen($metaTitle) > 60) {
                $truncated = mb_substr($metaTitle, 0, 60);
                $lastSpace = mb_strrpos($truncated, ' ');
                $metaTitle = ($lastSpace && $lastSpace > 40) ? mb_substr($truncated, 0, $lastSpace) : $truncated;
            }

            // Validation : mot-clé doit être présent dans meta_title
            if (!empty($primaryKeyword) && mb_stripos($metaTitle, mb_substr($primaryKeyword, 0, 15)) === false) {
                $metaTitle = ucfirst($primaryKeyword) . ' | ' . $metaTitle;
                if (mb_strlen($metaTitle) > 60) {
                    $metaTitle = mb_substr($metaTitle, 0, 57) . '...';
                }
            }

            // Validation meta_description : tronquer à 155 chars
            if (mb_strlen($metaDesc) > 155) {
                $truncated = mb_substr($metaDesc, 0, 155);
                $lastDot = mb_strrpos($truncated, '.');
                $metaDesc = ($lastDot && $lastDot > 100) ? mb_substr($truncated, 0, $lastDot + 1) : $truncated;
            }

            return [
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
            ];
        }

        // Fallback structuré
        $year = date('Y');
        return [
            'meta_title' => mb_substr(ucfirst($primaryKeyword) . ' : Guide Complet ' . $year . ' | SOS-Expat', 0, 60),
            'meta_description' => mb_substr('Découvrez notre guide complet sur ' . $primaryKeyword . '. Conseils pratiques, démarches et informations à jour en ' . $year . '.', 0, 155),
        ];
    }

    /**
     * Phase 7b: Generate OG meta + AI summary for AEO.
     *
     * og_title: More engaging than meta_title (≤95 chars), optimized for social sharing
     * og_description: Call-to-action oriented (≤200 chars), optimized for clicks
     * ai_summary: Factual 2-3 sentence summary (≤500 chars) for AI engines (ChatGPT, Perplexity)
     */
    private function phase07b_generateAeoMeta(
        string $title,
        string $metaTitle,
        string $metaDescription,
        string $excerpt,
        string $contentText,
        string $primaryKeyword,
        string $language
    ): array {
        $contentSnippet = mb_substr($contentText, 0, 800);

        $systemPrompt = $this->kbPrompt . "\n\nTu génères 3 éléments différenciés pour un article sur '{$primaryKeyword}' en {$language}.\n\n"
            . "Retourne en JSON:\n"
            . "{\n"
            . "  \"og_title\": \"Titre accrocheur pour réseaux sociaux (≤95 chars, DIFFÉRENT du meta_title)\",\n"
            . "  \"og_description\": \"Description incitant au partage social avec CTA (≤200 chars)\",\n"
            . "  \"ai_summary\": \"Résumé factuel 100-200 mots pour les moteurs IA (PAS de marketing, commence par les faits)\"\n"
            . "}\n\n"
            . "IMPORTANT:\n"
            . "- og_title : plus ÉMOTIONNEL et ENGAGEANT que le meta_title (qui est SEO)\n"
            . "- og_description : doit donner envie de CLIQUER et PARTAGER\n"
            . "- ai_summary : STRICTEMENT FACTUEL, 100-200 mots, commence par les faits, pas par 'Cet article...'";

        $userPrompt = "Meta title actuel: {$metaTitle}\nMeta desc actuelle: {$metaDescription}\nTitre H1: {$title}\nContenu: {$contentSnippet}";

        try {
            $result = $this->openAi->complete($systemPrompt, $userPrompt, [
                'temperature' => 0.5,
                'max_tokens' => 600,
                'json_mode' => true,
            ]);

            if ($result['success']) {
                $parsed = json_decode($result['content'], true);
                if ($parsed) {
                    return [
                        'og_title'       => mb_substr($parsed['og_title'] ?? $metaTitle, 0, 95),
                        'og_description' => mb_substr($parsed['og_description'] ?? $metaDescription, 0, 200),
                        'ai_summary'     => mb_substr($parsed['ai_summary'] ?? $excerpt, 0, 1400),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Phase 7b AEO meta fallback', ['error' => $e->getMessage()]);
        }

        // Fallback: derive from existing meta
        return [
            'og_title'       => mb_substr($title, 0, 95),
            'og_description' => mb_substr($metaDescription, 0, 200),
            'ai_summary'     => mb_substr($excerpt, 0, 1400),
        ];
    }

    private function phase08_generateJsonLd(GeneratedArticle $article): array
    {
        return $this->jsonLd->generateFullSchema($article);
    }

    private function phase09_addInternalLinks(GeneratedArticle $article): string
    {
        $suggestions = $this->internalLinking->suggestLinks($article);

        if (empty($suggestions)) {
            return $article->content_html ?? '';
        }

        return $this->internalLinking->injectLinks($article, $suggestions);
    }

    private function phase10_addExternalLinks(GeneratedArticle $article, array $sources): string
    {
        $html = $article->content_html ?? '';
        $linksHtml = '';
        $usedDomains = [];

        // 1. Inject directory links from country_directory (if article has a country)
        $directoryLinks = $this->getDirectoryLinksForArticle($article);
        foreach ($directoryLinks as $dirLink) {
            if (in_array($dirLink['domain'], $usedDomains, true)) {
                continue;
            }

            GeneratedArticleSource::create([
                'article_id'  => $article->id,
                'url'         => $dirLink['url'],
                'title'       => $dirLink['title'],
                'domain'      => $dirLink['domain'],
                'trust_score' => $dirLink['trust_score'],
            ]);

            ExternalLinkRegistry::create([
                'article_type' => GeneratedArticle::class,
                'article_id'   => $article->id,
                'url'          => $dirLink['url'],
                'domain'       => $dirLink['domain'],
                'anchor_text'  => $dirLink['anchor_text'] ?? $dirLink['title'],
                'trust_score'  => $dirLink['trust_score'],
                'is_nofollow'  => !($dirLink['is_official'] ?? true),
            ]);

            $rel = ($dirLink['is_official'] ?? true) ? 'noopener' : 'noopener nofollow';
            $linksHtml .= '<li><a href="' . htmlspecialchars($dirLink['url']) . '" target="_blank" rel="' . $rel . '">'
                . htmlspecialchars($dirLink['title']) . '</a></li>';
            $usedDomains[] = $dirLink['domain'];
        }

        // 2. Add Perplexity research sources (up to 4, skip already-used domains)
        $selectedSources = array_slice($sources, 0, 4);

        foreach ($selectedSources as $source) {
            $domain = $source['domain'] ?? parse_url($source['url'] ?? '', PHP_URL_HOST) ?? '';
            $url = $source['url'] ?? '';

            if (empty($url) || in_array($domain, $usedDomains, true)) {
                continue;
            }

            GeneratedArticleSource::create([
                'article_id'  => $article->id,
                'url'         => $url,
                'title'       => $source['title'] ?? $domain,
                'domain'      => $domain,
                'trust_score' => $source['trust_score'] ?? 50,
            ]);

            ExternalLinkRegistry::create([
                'article_type' => GeneratedArticle::class,
                'article_id'   => $article->id,
                'url'          => $url,
                'domain'       => $domain,
                'anchor_text'  => $domain,
                'trust_score'  => $source['trust_score'] ?? 50,
                'is_nofollow'  => false,
            ]);

            $linksHtml .= '<li><a href="' . htmlspecialchars($url) . '" target="_blank" rel="nofollow noopener">'
                . htmlspecialchars($source['title'] ?? $domain) . '</a></li>';
            $usedDomains[] = $domain;
        }

        if (!empty($linksHtml)) {
            $sourcesSection = "\n<h2>Sources</h2>\n<ul>\n{$linksHtml}\n</ul>";
            $html .= $sourcesSection;
        }

        return $html;
    }

    /**
     * Retrieve relevant directory links from country_directory for the article.
     * Matches by country code and article content_type → directory category mapping.
     */
    private function getDirectoryLinksForArticle(GeneratedArticle $article): array
    {
        $country = $article->country;
        if (empty($country)) {
            return [];
        }

        // Resolve country code (could be name or code)
        $countryCode = mb_strlen($country) === 2
            ? strtoupper($country)
            : \App\Models\CountryDirectory::where('country_name', $country)
                ->orWhere('country_slug', \Illuminate\Support\Str::slug($country))
                ->value('country_code');

        if (empty($countryCode)) {
            return [];
        }

        // Map article content_type to relevant directory categories
        $relevantCategories = match ($article->content_type) {
            'guide', 'pillar' => ['ambassade', 'immigration', 'sante', 'logement', 'emploi', 'urgences'],
            'article'         => ['immigration', 'sante', 'logement', 'emploi'],
            'comparative'     => ['banque', 'logement', 'telecom'],
            'qa'              => ['immigration', 'urgences', 'sante'],
            default           => ['ambassade', 'immigration'],
        };

        // Get country-specific links (high trust, limited count)
        $countryLinks = \App\Models\CountryDirectory::active()
            ->where('country_code', $countryCode)
            ->whereIn('category', $relevantCategories)
            ->where('trust_score', '>=', 75)
            ->orderByDesc('trust_score')
            ->limit(4)
            ->get()
            ->map(fn ($e) => [
                'url'          => $e->url,
                'title'        => $e->title,
                'domain'       => $e->domain,
                'trust_score'  => $e->trust_score,
                'anchor_text'  => $e->anchor_text,
                'is_official'  => $e->is_official,
            ])
            ->toArray();

        // Also get 1-2 global resources
        $globalLinks = \App\Models\CountryDirectory::active()
            ->where('country_code', 'XX')
            ->whereIn('category', $relevantCategories)
            ->where('trust_score', '>=', 85)
            ->orderByDesc('trust_score')
            ->limit(2)
            ->get()
            ->map(fn ($e) => [
                'url'          => $e->url,
                'title'        => $e->title,
                'domain'       => $e->domain,
                'trust_score'  => $e->trust_score,
                'anchor_text'  => $e->anchor_text,
                'is_official'  => $e->is_official,
            ])
            ->toArray();

        return array_merge($countryLinks, $globalLinks);
    }

    private function phase11_addAffiliateLinks(GeneratedArticle $article): string
    {
        $html = $article->content_html ?? '';

        // SOS-Expat service keywords to match
        $affiliateTargets = [
            [
                'keywords' => ['consultation juridique', 'legal consultation', 'avocat', 'lawyer', 'juridique'],
                'url' => 'https://www.sos-expat.com/consultation',
                'anchor' => 'consultation juridique SOS-Expat',
                'program' => 'sos-expat',
            ],
            [
                'keywords' => ['assistance expatrié', 'expat assistance', 'aide expatriation', 'expat help'],
                'url' => 'https://www.sos-expat.com',
                'anchor' => 'SOS-Expat assistance',
                'program' => 'sos-expat',
            ],
            [
                'keywords' => ['prestataire', 'provider', 'expert', 'spécialiste'],
                'url' => 'https://www.sos-expat.com/providers',
                'anchor' => 'trouver un expert SOS-Expat',
                'program' => 'sos-expat',
            ],
        ];

        $contentLower = mb_strtolower($html);
        $addedCount = 0;

        foreach ($affiliateTargets as $target) {
            if ($addedCount >= 2) {
                break; // Max 2 affiliate links per article
            }

            foreach ($target['keywords'] as $keyword) {
                if (mb_strpos($contentLower, $keyword) !== false) {
                    // Found relevant keyword — add affiliate link at end of a related paragraph
                    $escapedKeyword = preg_quote($keyword, '/');
                    $pattern = '/(<p[^>]*>[^<]*' . $escapedKeyword . '[^<]*<\/p>)/iu';

                    if (preg_match($pattern, $html, $match)) {
                        $link = ' <a href="' . htmlspecialchars($target['url']) . '" target="_blank" rel="sponsored noopener">'
                            . htmlspecialchars($target['anchor']) . '</a>';

                        // Insert before closing </p>
                        $replacement = str_replace('</p>', $link . '</p>', $match[0]);
                        $html = str_replace($match[0], $replacement, $html);

                        AffiliateLink::create([
                            'article_type' => GeneratedArticle::class,
                            'article_id' => $article->id,
                            'url' => $target['url'],
                            'anchor_text' => $target['anchor'],
                            'program' => $target['program'],
                            'position' => $addedCount + 1,
                        ]);

                        $addedCount++;
                        break; // Move to next target
                    }
                }
            }
        }

        return $html;
    }

    private function phase12_addImages(GeneratedArticle $article, string $imageSource): void
    {
        $keywords = $article->keywords_primary ?? $article->title;

        if ($imageSource === 'dalle' && $this->openAi->isConfigured()) {
            $prompt = "Professional blog article header image about: {$keywords}. "
                . "Clean, modern, editorial style. No text overlay.";

            $result = $this->openAi->generateImage($prompt, [
                'costable_type' => GeneratedArticle::class,
                'costable_id' => $article->id,
            ]);

            if ($result['success']) {
                // Keyword-optimized alt text
                $altText = ($article->keywords_primary ? ucfirst($article->keywords_primary) . ' - ' : '')
                    . $article->title
                    . ($article->country ? ' (' . $article->country . ')' : '');
                $altText = mb_substr($altText, 0, 125);

                $image = $article->images()->create([
                    'url' => $result['url'],
                    'alt_text' => $altText,
                    'source' => 'dall-e-3',
                    'attribution' => 'Generated by DALL-E 3',
                    'sort_order' => 0,
                ]);

                // Set as featured image
                $article->update([
                    'featured_image_url' => $result['url'],
                    'featured_image_alt' => $altText,
                ]);

                return;
            }

            // Fallback to Unsplash if DALL-E fails
            Log::warning('DALL-E failed, falling back to Unsplash', ['article_id' => $article->id]);
        }

        // Unsplash search
        if ($this->unsplash->isConfigured()) {
            $result = $this->unsplash->search($keywords, 3);

            if ($result['success'] && !empty($result['images'])) {
                $firstImageUrl = null;
                $firstImageAlt = null;

                foreach ($result['images'] as $index => $image) {
                    // Keyword-enriched alt text
                    $altText = $article->keywords_primary
                        ? ucfirst($article->keywords_primary) . ' - ' . ($image['alt_text'] ?? $article->title)
                        : ($image['alt_text'] ?? $article->title);
                    $altText = mb_substr($altText, 0, 125);

                    $article->images()->create([
                        'url' => $image['url'],
                        'alt_text' => $altText,
                        'source' => 'unsplash',
                        'attribution' => $image['attribution'],
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'sort_order' => $index,
                    ]);

                    if ($index === 0) {
                        $firstImageUrl = $image['url'];
                        $firstImageAlt = $altText;
                    }
                }

                // Set first image as featured with Unsplash attribution data
                if ($firstImageUrl) {
                    $firstImage = $result['images'][0];
                    $article->update([
                        'featured_image_url' => $firstImageUrl,
                        'featured_image_alt' => $firstImageAlt,
                        'featured_image_attribution' => $firstImage['attribution'] ?? null,
                        'featured_image_srcset' => $firstImage['srcset'] ?? null,
                        'photographer_name' => $firstImage['photographer_name'] ?? null,
                        'photographer_url' => $firstImage['photographer_url'] ?? null,
                    ]);
                    Log::info('Phase 12: Featured image set from Unsplash', [
                        'article_id' => $article->id,
                        'photographer' => $firstImage['photographer_name'] ?? 'unknown',
                    ]);
                    return;
                }
            }
        }

        // Fallback: if no image source worked, log warning
        Log::warning('Phase 12: No featured image set (both DALL-E and Unsplash failed or unconfigured)', [
            'article_id' => $article->id,
            'unsplash_configured' => $this->unsplash->isConfigured(),
        ]);
    }

    private function phase13_generateSlugs(GeneratedArticle $article): void
    {
        $slug = $this->slugService->generateSlug($article->title, $article->language);
        $slug = $this->slugService->ensureUnique($slug, $article->language, 'generated_articles', $article->id);

        $article->update(['slug' => $slug]);
    }

    private function phase14_calculateQuality(GeneratedArticle $article): void
    {
        // Run SEO analysis
        $seoResult = $this->seoAnalysis->analyze($article);

        // Calculate readability
        $text = $this->seoAnalysis->extractTextFromHtml($article->content_html ?? '');
        $readabilityScore = $this->seoAnalysis->calculateReadability($text);

        // Calculate quality score (weighted average)
        $seoWeight = 0.40;
        $readabilityWeight = 0.25;
        $lengthWeight = 0.20;
        $faqWeight = 0.15;

        $seoNormalized = min(100, $seoResult->overall_score);
        $readabilityNormalized = $readabilityScore;

        // Length score: 100 if 1500-3000 words, scaled otherwise
        $wordCount = $article->word_count ?? 0;
        $lengthNormalized = 0;
        if ($wordCount >= 1500 && $wordCount <= 3000) {
            $lengthNormalized = 100;
        } elseif ($wordCount >= 1000) {
            $lengthNormalized = 70;
        } elseif ($wordCount >= 500) {
            $lengthNormalized = 40;
        }

        // FAQ completeness: 100 if has 6+ FAQs
        $faqCount = $article->faqs()->count();
        $faqNormalized = min(100, ($faqCount / 6) * 100);

        $qualityScore = (int) round(
            ($seoNormalized * $seoWeight)
            + ($readabilityNormalized * $readabilityWeight)
            + ($lengthNormalized * $lengthWeight)
            + ($faqNormalized * $faqWeight)
        );

        $article->update([
            'seo_score' => (int) $seoNormalized,
            'quality_score' => $qualityScore,
            'readability_score' => $readabilityScore,
            'word_count' => $wordCount > 0 ? $wordCount : $this->seoAnalysis->countWords($article->content_html ?? ''),
        ]);

        // Run SEO Checklist evaluation
        try {
            app(SeoChecklistService::class)->evaluate($article);
        } catch (\Throwable $e) {
            Log::warning('ArticleGeneration: SEO checklist failed (non-blocking)', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function phase15_dispatchTranslations(GeneratedArticle $article, array $languages): void
    {
        foreach ($languages as $targetLang) {
            if ($targetLang === $article->language) {
                continue; // Skip same language
            }

            // Dispatch translation job (async)
            try {
                \App\Jobs\GenerateTranslationJob::dispatch($article->id, $targetLang);

                Log::info('Translation job dispatched', [
                    'article_id' => $article->id,
                    'target_language' => $targetLang,
                ]);
            } catch (\Throwable $e) {
                Log::error('Translation dispatch failed', [
                    'article_id' => $article->id,
                    'target_language' => $targetLang,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Sync hreflang after dispatching translations
        $this->hreflang->syncAllTranslations($article);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function logPhase(GeneratedArticle $article, string $phase, string $status, ?string $message = null, int $tokens = 0, int $costCents = 0, int $durationMs = 0): void
    {
        try {
            GenerationLog::create([
                'loggable_type' => GeneratedArticle::class,
                'loggable_id' => $article->id,
                'phase' => $phase,
                'status' => $status,
                'message' => $message,
                'tokens_used' => $tokens,
                'cost_cents' => $costCents,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log generation phase', [
                'phase' => $phase,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getPromptTemplate(string $contentType, string $phase): ?PromptTemplate
    {
        try {
            return PromptTemplate::forPhase($contentType, $phase)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function replaceVariables(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }

    private function elapsed(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }

    /**
     * Send a Telegram alert when a generation phase fails.
     * Non-blocking — if Telegram fails, just log it.
     */
    private function sendTelegramAlert(string $phase, string $error, ?GeneratedArticle $article = null): void
    {
        try {
            $botToken = config('services.telegram_alerts.bot_token');
            $chatId = config('services.telegram_alerts.chat_id');
            if (!$botToken || !$chatId) return;

            $articleInfo = $article
                ? "Article #{$article->id}: {$article->title}\nLangue: {$article->language} | Pays: {$article->country}"
                : 'N/A';

            $text = "⚠️ *Pipeline Content — Phase échouée*\n\n"
                . "Phase: `{$phase}`\n"
                . "Erreur: " . mb_substr($error, 0, 300) . "\n\n"
                . "{$articleInfo}\n"
                . "Heure: " . now()->format('d/m/Y H:i:s');

            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Telegram alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send a Telegram notification when an article is successfully generated.
     */
    private function sendTelegramSuccess(GeneratedArticle $article): void
    {
        try {
            $botToken = config('services.telegram_alerts.bot_token');
            $chatId = config('services.telegram_alerts.chat_id');
            if (!$botToken || !$chatId) return;

            $text = "✅ *Nouvel article généré*\n\n"
                . "Titre: {$article->title}\n"
                . "Type: {$article->content_type} | Langue: {$article->language} | Pays: {$article->country}\n"
                . "Mots: {$article->word_count} | SEO: {$article->seo_score}/100 | Qualité: {$article->quality_score}/100\n"
                . "Image: " . ($article->featured_image_url ? '✅' : '❌') . "\n"
                . "FAQs: " . $article->faqs()->count() . "\n"
                . "Status: {$article->status}\n"
                . "Heure: " . now()->format('d/m/Y H:i:s');

            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Telegram success notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Route a completion request to Claude or OpenAI depending on the model.
     * Model names starting with 'claude-' go to ClaudeService; everything else to OpenAiService.
     */
    private function aiComplete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $model = $options['model'] ?? null;

        if ($model && str_starts_with($model, 'claude-')) {
            return $this->claude->complete($systemPrompt, $userPrompt, $options);
        }

        return $this->openAi->complete($systemPrompt, $userPrompt, $options);
    }
}
