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
use App\Services\Content\StatisticsInjectionService;
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
        private StatisticsInjectionService $statsInjection,
    ) {}

    /**
     * Default search intent per content type.
     * Google ranks content higher when it matches search intent (since 2023).
     */
    private static function defaultIntent(string $contentType): string
    {
        return match ($contentType) {
            'guide', 'guide_city', 'pillar'              => 'informational',
            'article', 'tutorial', 'statistics', 'news'    => 'informational',
            'qa', 'qa_needs'                              => 'informational',
            'comparative', 'affiliation'                  => 'commercial_investigation',
            'testimonial'                                 => 'informational',
            'outreach'                                    => 'transactional',
            'pain_point'                                  => 'urgency',
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
        $typeConfig['_search_intent'] = $searchIntent; // Pass intent to all phases via typeConfig
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

        // Inject verified statistics from DB (World Bank / OECD / Eurostat + country_facts)
        $resolvedCountryCode = $this->statsInjection->extractCountryCode(
            $params['topic'] ?? $params['title'] ?? null,
            $country
        );
        if ($resolvedCountryCode) {
            $statsBlock = $this->statsInjection->getCountryDataBlock($resolvedCountryCode);
            if ($statsBlock) {
                $this->kbPrompt .= "\n" . $statsBlock;
                Log::info('ArticleGenerationService: injected verified stats', [
                    'country' => $resolvedCountryCode,
                    'block_length' => strlen($statsBlock),
                ]);
            }
        }

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

            // Phase 5: Generate content (multi-prompt pipeline)
            $phaseStart = microtime(true);
            $params['lsi_keywords'] = $lsiKeywords;

            // 5a: Editorial outline (structure + angle + arc narratif)
            $phaseStartSub = microtime(true);
            $outline = $this->phase05a_generateOutline($title, $excerpt, $research['facts'], $params, $typeConfig);
            $this->logPhase($article, 'content_outline', 'success', count($outline['sections'] ?? []) . ' sections planned', 0, 0, $this->elapsed($phaseStartSub));

            // Store tone guidance for the polish pass
            $params['_outline_tone'] = $outline['tone_guidance'] ?? '';
            $params['excerpt'] = $excerpt;

            // 5b: Captivating introduction (storyteller)
            $phaseStartSub = microtime(true);
            $introHtml = $this->phase05_generateIntroduction($title, $outline, $research['facts'], $params, $typeConfig);
            $this->logPhase($article, 'content_intro', 'success', str_word_count(strip_tags($introHtml)) . ' words', 0, 0, $this->elapsed($phaseStartSub));

            // 5c: Body sections (expert, by groups of 2-3)
            $phaseStartSub = microtime(true);
            $bodyHtml = $this->phase05c_generateSections($title, $outline, $introHtml, $research['facts'], $params, $typeConfig);
            $this->logPhase($article, 'content_sections', 'success', str_word_count(strip_tags($bodyHtml)) . ' words', 0, 0, $this->elapsed($phaseStartSub));

            // 5d: Conclusion (conversion copywriter)
            $phaseStartSub = microtime(true);
            $conclusionHtml = $this->phase05d_generateConclusion($title, $introHtml, $bodyHtml, $params, $typeConfig);
            $this->logPhase($article, 'content_conclusion', 'success', str_word_count(strip_tags($conclusionHtml)) . ' words', 0, 0, $this->elapsed($phaseStartSub));

            // Assemble full article
            $contentHtml = $introHtml . "\n\n" . $bodyHtml . "\n\n" . $conclusionHtml;

            // 5e: Polish pass (editor-in-chief — transitions, anti-AI patterns, coherence)
            $phaseStartSub = microtime(true);
            $contentHtml = $this->phase05e_polishAndUnify($contentHtml, $title, $params, $typeConfig);
            $this->logPhase($article, 'content_polish', 'success', null, 0, 0, $this->elapsed($phaseStartSub));

            // Word count check — if too short after assembly, extend
            $wordCount = str_word_count(strip_tags($contentHtml));
            $targetWords = $typeConfig['target_words_range'] ?? '3000-4500';
            $minWords = (int) (((int) explode('-', $targetWords)[0]) * 0.7);
            if ($wordCount < $minWords && $wordCount > 100) {
                Log::info("Phase 5: Content too short ({$wordCount} words, min {$minWords}), extending...", [
                    'article_topic' => $params['topic'] ?? '',
                ]);
                $maxTokens = $typeConfig['max_tokens_content'] ?? 16384;
                $extendPrompt = "L'article suivant fait seulement {$wordCount} mots. Il DOIT faire MINIMUM {$targetWords} mots. "
                    . "Developpe-le considerablement : ajoute des sections detaillees, des exemples concrets, "
                    . "des chiffres, des tableaux comparatifs, des conseils pratiques, des retours d'experience. "
                    . "Chaque section <h2> doit contenir au minimum 3 paragraphes de 80+ mots chacun.\n\n"
                    . "ARTICLE A DEVELOPPER :\n" . $contentHtml;

                $extendResult = $this->aiComplete(
                    $this->kbPrompt . "\n\nTu es un redacteur web expert. Developpe cet article en HTML pour qu'il atteigne {$targetWords} mots minimum.",
                    $extendPrompt,
                    [
                        'model'       => $typeConfig['model'] ?? null,
                        'temperature' => 0.8,
                        'max_tokens'  => $maxTokens,
                    ]
                );

                if ($extendResult['success']) {
                    $extended = $this->cleanAiHtml($extendResult['content']);
                    $newWordCount = str_word_count(strip_tags($extended));
                    Log::info("Phase 5: Extended from {$wordCount} to {$newWordCount} words");
                    if ($newWordCount > $wordCount) {
                        $contentHtml = $extended;
                    }
                }
            }

            $article->update([
                'content_html' => $contentHtml,
                'word_count' => $this->seoAnalysis->countWords($contentHtml),
                'reading_time_minutes' => max(1, (int) ceil($this->seoAnalysis->countWords($contentHtml) / 250)),
            ]);
            $this->logPhase($article, 'content', 'success', 'Generated ' . $article->word_count . ' words via multi-prompt pipeline', 0, 0, $this->elapsed($phaseStart));

            // Phase 5 featured snippet: SKIPPED — already generated by phase05_generateIntroduction
            // (phase05b_featuredSnippet was producing a duplicate; the intro now includes
            //  a <div class="featured-snippet"> with intent-specific rules)

            // Phase 5.5: Fact-check against verified DB data
            if ($resolvedCountryCode) {
                try {
                    $phaseStart = microtime(true);
                    $factCheck = app(FactCheckGuardService::class)->check($article, $resolvedCountryCode);
                    $article->update([
                        'fact_check_score' => $factCheck['score'],
                    ]);
                    $this->logPhase($article, 'fact_check', $factCheck['passed'] ? 'success' : 'warning',
                        'Score: ' . $factCheck['score'] . '/100, issues: ' . count($factCheck['issues']) . ', warnings: ' . count($factCheck['warnings']),
                        0, 0, $this->elapsed($phaseStart));

                    if (!empty($factCheck['issues'])) {
                        Log::warning('FactCheck: blocking issues found', [
                            'article_id' => $article->id,
                            'issues' => array_map(fn ($i) => $i['message'], $factCheck['issues']),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Phase 5.5 (fact-check) failed, continuing', ['error' => $e->getMessage()]);
                }
            }

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
                        // Store in DB — the blog frontend renders FAQs via blade partial
                        // (faq-section.blade.php) + JSON-LD FAQPage schema, so we do NOT
                        // inject them into content_html (that would create duplicates).
                        foreach ($faqs as $index => $faq) {
                            GeneratedArticleFaq::create([
                                'article_id' => $article->id,
                                'question' => mb_substr(strip_tags($faq['question'] ?? ''), 0, 255),
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
                'og_site_name'     => 'SOS-Expat & Travelers',
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

            // Ensure featured_image_url is set from images relation if not already.
            // Build a clean alt text from the article title instead of reusing
            // $firstImage->alt_text — historically that field could contain a
            // concatenation with the Unsplash photographer's English caption,
            // which pollutes French/Spanish/Arabic articles. The page-context
            // title is always in the correct language.
            $article->refresh();
            if (!$article->featured_image_url) {
                $firstImage = $article->images()->first();
                if ($firstImage) {
                    $fallbackAlt = mb_substr(trim(
                        $article->title . ($article->country ? ' (' . $article->country . ')' : '')
                    ), 0, 125);
                    $article->update([
                        'featured_image_url' => $firstImage->url,
                        'featured_image_alt' => $fallbackAlt,
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

            // Phase 14b: LLM editorial judge (title/meta/content/facts/intent)
            // Non-blocking — if judge fails, article keeps its mechanical
            // quality_score and proceeds. The publication gate in
            // GenerateArticleJob::autoPublish() reads editorial_score to
            // decide whether to push to the blog or keep for manual review.
            $phaseStart = microtime(true);
            try {
                $judgeReport = $this->phase14b_editorialJudge($article->fresh());
                $overall = $judgeReport['overall'] ?? 0;
                $this->logPhase(
                    $article,
                    'editorial_judge',
                    'success',
                    "editorial_score={$overall} title={$judgeReport['title_score']} meta={$judgeReport['meta_score']} content={$judgeReport['content_score']} facts={$judgeReport['fact_score']} intent={$judgeReport['intent_score']}",
                    0, 0,
                    $this->elapsed($phaseStart)
                );
            } catch (\Throwable $e) {
                Log::warning('Phase 14b: editorial judge failed (non-blocking)', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
                // Only alert Telegram if the judge crashed with an unexpected
                // error (not a rate limit or API timeout — those are noise).
                if (!str_contains(strtolower($e->getMessage()), 'rate') && !str_contains(strtolower($e->getMessage()), 'timeout')) {
                    $this->sendTelegramAlert('14b — Editorial Judge', $e->getMessage(), $article);
                }
                $this->logPhase($article, 'editorial_judge', 'warning', $e->getMessage(), 0, 0, $this->elapsed($phaseStart));
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
                $targetLanguages = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'ar', 'hi'];
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
                    \App\Jobs\GenerateQrSatellitesJob::dispatch($article->id)->onQueue('content')->delay(now()->addMinutes(2));
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
                'article_id' => $article->id ?? null,
                'phase' => 'unknown',
                'message' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ]);

            // Decision: empty skeleton vs partial content.
            //
            // The original behavior was to silently set status='draft' and return
            // the (often empty) article — this caused two production bugs:
            //   1. The orchestrator daily quota counter was incremented for an
            //      article that was never produced, eating up tomorrow's budget.
            //   2. The publication-engine cron only catches articles in 'review'
            //      with word_count > 0, so empty drafts stay forever in the DB,
            //      polluting the queue and never publishing.
            //
            // New behavior:
            //   - Empty skeleton (no html / wc=0) → DELETE the row entirely.
            //     The exception is rethrown so the calling job (GenerateArticleJob)
            //     properly fails, triggers Laravel queue retry/backoff, and the
            //     orchestrator counter (now self-healing in getConfig()) reflects
            //     the real DB count.
            //   - Partial content (has html or word_count > 0) → keep as 'draft'
            //     so a human can recover it manually. Still rethrow so the queue
            //     records the failure.
            $isEmpty = empty($article->content_html) || (int) ($article->word_count ?? 0) === 0;

            if ($isEmpty) {
                $articleId = $article->id;
                try {
                    $article->delete();
                } catch (\Throwable $deleteErr) {
                    Log::warning('ArticleGenerationService: failed to delete empty skeleton', [
                        'article_id' => $articleId,
                        'error' => $deleteErr->getMessage(),
                    ]);
                }
                Log::warning('ArticleGenerationService: deleted empty skeleton on pipeline failure', [
                    'article_id' => $articleId,
                    'error' => $e->getMessage(),
                ]);
                $this->sendTelegramAlert('PIPELINE COMPLET (échec critique — squelette supprimé)', $e->getMessage(), null);
            } else {
                $article->update(['status' => 'draft']);
                $this->logPhase($article, 'pipeline', 'error', $e->getMessage());
                $this->sendTelegramAlert('PIPELINE COMPLET (échec critique — contenu partiel conservé)', $e->getMessage(), $article);
            }

            // Rethrow so GenerateArticleJob's failed() handler is invoked,
            // Laravel queue retry kicks in, and the orchestrator does not
            // wrongly count this dispatch as a success.
            throw $e;
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
            'testimonial', 'statistics', 'pain_point',
            'outreach', 'affiliation', 'brand_content',
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
        $extractResult = $this->aiComplete(
            $this->kbPrompt . "\n\nTu es un assistant de recherche. Extrais les faits clés, chiffres, données importantes "
            . "et points essentiels de ce contenu. "
            . "Retourne en JSON : {\"facts\": [\"fait1\", \"fait2\", ...], \"lsi_keywords\": [\"mot1\", \"mot2\", ...]}",
            "Contenu source :\n{$truncated}",
            ['model' => 'gpt-4o-mini', 'temperature' => 0.3, 'max_tokens' => 1000, 'json_mode' => true]
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
        $query = "Recherche des informations factuelles, récentes et fiables sur le sujet suivant{$countryContext}: \"{$topic}\". "
            . "Retourne: les faits clés avec sources et années, les statistiques officielles (World Bank, OECD, gouvernement), "
            . "les points importants à couvrir. "
            . "Inclus aussi une liste de 10-15 mots-clés sémantiques (LSI) liés au sujet — des mots que Google s'attend à trouver dans un article complet sur ce thème.";

        $result = $this->perplexity->searchFactual($query, $language);

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
                $lsiResult = $this->aiComplete(
                    "Extrais 10-15 mots-clés sémantiques (LSI) de ce texte de recherche. Ce sont des termes que Google s'attend à trouver dans un article complet sur le sujet. Retourne en JSON: {\"lsi_keywords\": [\"mot1\", \"mot2\", ...]}",
                    mb_substr($result['text'], 0, 3000),
                    ['model' => 'gpt-4o-mini', 'temperature' => 0.3, 'max_tokens' => 300, 'json_mode' => true]
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

        // Determine search intent for title format
        $searchIntent = $typeConfig['_search_intent'] ?? self::defaultIntent($contentType);

        // Intent-specific title format rules (2026 best practice: title = exact search query)
        $intentTitleRule = match ($searchIntent) {
            'informational' => "FORMAT INFORMATIONNEL — le titre est une QUESTION GOOGLE ou une REQUETE NATURELLE :\n"
                . "  Exemples parfaits :\n"
                . "  - 'Visa Thailande refuse : que faire en {$year} ?'\n"
                . "  - 'Combien coute un visa de travail en Allemagne ({$year})'\n"
                . "  - 'Ouvrir un compte bancaire au Portugal : demarches {$year}'\n"
                . "  Le titre DOIT correspondre a ce que l'utilisateur tape LITTERALEMENT dans Google.\n",
            'commercial_investigation' => "FORMAT COMPARATIF — verdict direct dans le titre :\n"
                . "  Exemples parfaits :\n"
                . "  - 'Wise vs Revolut pour expatries : comparatif {$year}'\n"
                . "  - 'Meilleure assurance sante en Thailande : comparatif et prix ({$year})'\n"
                . "  Structure : 'X vs Y : comparatif {$year}' ou 'Meilleur(e) X : comparatif {$year}'\n",
            'transactional' => "FORMAT TRANSACTIONNEL — action directe dans le titre :\n"
                . "  Exemples parfaits :\n"
                . "  - 'Consulter un avocat en Thailande en ligne ({$year})'\n"
                . "  - 'Obtenir un visa Thailande : prix et delais {$year}'\n"
                . "  Le titre promet une ACTION CONCRETE et rapide.\n",
            'urgency' => "FORMAT URGENCE — probleme + action immediate dans le titre :\n"
                . "  Exemples parfaits :\n"
                . "  - 'Passeport vole en Thailande : que faire en urgence ({$year})'\n"
                . "  - 'Arrete en Thailande : premiers reflexes et droits ({$year})'\n"
                . "  Structure : '[Probleme] en [Pays] : que faire ({$year})'\n",
            'local' => "FORMAT LOCAL — service + lieu precis dans le titre :\n"
                . "  Exemples parfaits :\n"
                . "  - 'Avocat a Bangkok : ou trouver un juriste en {$year}'\n"
                . "  - 'Medecin international a Chiang Mai ({$year})'\n",
            default => '',
        };

        $systemPrompt = $this->kbPrompt . "\n\n" . ($template
            ? $this->replaceVariables($template->system_message, ['language' => $language, 'year' => $year])
            : "Tu generes des titres SEO qui correspondent a des VRAIES INTENTIONS DE RECHERCHE Google.\n\n"
              . "PRINCIPE 2026 : le titre DOIT etre la REQUETE EXACTE que l'utilisateur tape dans Google.\n"
              . "Google fait du title matching — si le titre ne correspond pas a la requete, tu perds le clic.\n\n"
              . "REGLE PRIMORDIALE : si le sujet fourni est DEJA formule comme une requete Google naturelle,\n"
              . "GARDE-LE TEL QUEL et ajoute juste l'annee ({$year}) si elle manque.\n"
              . "Ne reecris PAS un bon titre en quelque chose de generique.\n\n"
              . $intentTitleRule . "\n"
              . "REGLES GENERALES :\n"
              . "1. LONGUEUR : 50-90 caracteres. Le titre est COMPLET, jamais coupe.\n"
              . "2. MOT-CLE PRINCIPAL : \"{$primaryKeyword}\" apparait dans les 5 premiers mots.\n"
              . "3. ANNEE : inclure \"{$year}\" (signal fraicheur Google).\n"
              . "4. FORMATS INTERDITS — NE JAMAIS UTILISER :\n"
              . "   - 'Guide Complet', 'Guide Pratique', 'Guide Essentiel', 'Guide Ultime'\n"
              . "   - 'Tout Savoir sur', 'Tout ce qu'il faut savoir'\n"
              . "   - 'Conseils, Demarches et Astuces', 'Conseils Pratiques'\n"
              . "   - 'Decouvrez', 'Voici'\n"
              . "   - Tout titre qui commence par 'Guide'\n"
              . "   - Toute reformulation GENERIQUE d'un sujet SPECIFIQUE\n"
              . "5. Le titre doit donner ENVIE de cliquer — il promet une REPONSE CONCRETE.\n"
              . "6. Il doit etre SPECIFIQUE au pays/ville mentionne — pas generique.\n"
              . "7. MULTI-NATIONALITE : pas de mention de nationalite specifique dans le titre.\n\n"
              . "Langue: {$language}. Retourne UNIQUEMENT le titre, sans guillemets.");

        $userPrompt = "Sujet: {$topic}\nMot-clé principal: {$primaryKeyword}\nAnnée: {$year}{$factsContext}";

        // Force country name in title for country/city guide articles (anti-duplicate content)
        if ($country && in_array($contentType, ['guide', 'pillar', 'guide_city'], true)) {
            $countryName = $this->geoMeta->getGeoPlacename($country, $language);
            $userPrompt .= "\n\nOBLIGATOIRE : Le titre DOIT contenir explicitement le nom du pays ou de la ville '{$countryName}'. Exemple : 'Visa pour {$countryName} : demarches et couts ({$year})'. Sans ce nom géographique le titre sera REJETÉ."
                . "\nGRAMMAIRE : Utiliser la BONNE préposition devant le nom de pays (en français : 'au Portugal', 'en France', 'aux États-Unis', 'à Singapour'). Le titre DOIT sonner 100% naturel et natif — JAMAIS de formulation maladroite.";
        }

        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model' => $typeConfig['model'] ?? null,
            'temperature' => $typeConfig['temperature'] ?? 0.6,
            'max_tokens' => $typeConfig['max_tokens_title'] ?? 100,
        ]);

        if ($result['success']) {
            $title = $this->cleanTitle($result['content'], $primaryKeyword, $country, $year);
            return $title;
        }

        // Fallback: structured title with keyword + country + year
        $countryName = $country ? ($this->geoMeta->getGeoPlacename($country, $language) ?? $country) : '';
        $fallback = ucfirst($primaryKeyword) . ($countryName ? " — {$countryName}" : '') . " ({$year})";
        return mb_substr($fallback, 0, 90);
    }

    /**
     * Clean and validate a generated title. Fixes ALL known issues:
     * - Unicode escapes (\u00e9 → é)
     * - Duplicate country names ("Géorgie Géorgie")
     * - Generic patterns ("Guide Complet/Pratique/Essentiel")
     * - Missing capitalization ("vivre a luanda")
     * - Truncated titles ("federer et")
     * - Unresolved template vars ({pays})
     * - Ensure year is present
     */
    private function cleanTitle(string $raw, string $keyword, ?string $country, string $year): string
    {
        $title = trim($raw, " \t\n\r\0\x0B\"'");

        // 1. Strip HTML/markdown
        $title = strip_tags($title);
        $title = preg_replace('/^```\w*\s*|\s*```$/m', '', $title);

        // 2. Fix unicode escapes: \u00e9 → é, \u00e8 → è, etc.
        $title = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $title);

        // 3. Resolve any remaining template variables
        if ($country) {
            $countryName = $this->geoMeta->getGeoPlacename($country, 'fr') ?? $country;
            $title = str_replace(['{pays}', '{country}', '{Land}', '{país}'], $countryName, $title);
        }
        $title = str_replace(['{annee}', '{year}'], $year, $title);

        // 4. Remove duplicate country names ("Géorgie Géorgie", "Pérou : Pérou")
        if ($country) {
            $countryName = $this->geoMeta->getGeoPlacename($country, 'fr') ?? $country;
            // Match country name appearing 2+ times (case insensitive, with possible separator)
            $escaped = preg_quote($countryName, '/');
            $title = preg_replace("/({$escaped})\s*[:—–\-]?\s*{$escaped}/iu", '$1', $title);
            // Also remove raw country code if it appears alongside country name
            $title = preg_replace('/\s+' . preg_quote($country, '/') . '(?=\s|:|$)/i', '', $title);
        }

        // 5. Strip generic IA patterns
        $title = preg_replace('/\s*:?\s*guide\s+(complet|pratique|essentiel|ultime|efficace)\b/iu', '', $title);
        $title = preg_replace('/\s*:?\s*tout\s+savoir\s*(sur\s*)?/iu', '', $title);
        $title = preg_replace('/\s*:?\s*conseils\s+pratiques\b/iu', '', $title);
        $title = preg_replace('/\s*:?\s*conseils\s+et\s+astuces\b/iu', '', $title);
        $title = preg_replace('/\s*:?\s*demarches\s+et\s+astuces\b/iu', '', $title);

        // 6. Fix capitalization — Title Case for French
        // Capitalize first letter + after : — –
        $title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);
        $title = preg_replace_callback('/([:\—\–]\s*)([a-zàâäéèêëïîôùûüÿæœç])/u', function ($m) {
            return $m[1] . mb_strtoupper($m[2]);
        }, $title);

        // 7. Capitalize city/country names after locative prepositions.
        //    "à Paris", "en France", "au Portugal", "aux États-Unis" are
        //    always followed by a place, so capitalising the next letter is
        //    safe. "de"/"du" are trickier — they appear both before places
        //    ("du Portugal", "de Bangkok") AND before articles that are NOT
        //    places ("de la vie", "de le marché", "du côté de"). A blind
        //    regex capitalises "L" of "la" and produces monstrosities like
        //    "Coût de La Vie en Thaïlande". Fix: negative lookahead that
        //    skips "de/du" when followed by a French article.
        $title = preg_replace_callback(
            '/\b(à|en|au|aux)\s+([a-zàâäéèêëïîôùûüÿæœç])/u',
            fn ($m) => $m[1] . ' ' . mb_strtoupper($m[2]),
            $title
        );
        $title = preg_replace_callback(
            '/\b(de|du)\s+(?!(?:la|le|les|l[\'’]|à|en|au|aux|ce|cet|cette|ces|ma|mon|mes|ta|ton|tes|sa|son|ses|nos|vos|leurs?)\b)([a-zàâäéèêëïîôùûüÿæœç])/u',
            fn ($m) => $m[1] . ' ' . mb_strtoupper($m[2]),
            $title
        );

        // 7b. Undo wrong title-casing on French articles that the LLM or a
        //     previous cleanup step may have applied ("de La Vie" → "de la
        //     vie"). We lowercase any capitalised article that appears
        //     after "de/du/à/en/au/aux".
        $title = preg_replace_callback(
            '/\b(de|du|à|en|au|aux)\s+(La|Le|Les|L[\'’])\b/u',
            fn ($m) => $m[1] . ' ' . mb_strtolower($m[2]),
            $title
        );

        // 8. Ensure year is present
        if (!preg_match('/\d{4}/', $title)) {
            $title .= " ({$year})";
        }

        // 9. Clean up spacing/punctuation artifacts
        $title = preg_replace('/\s+/', ' ', $title);
        $title = preg_replace('/\s*:\s*:\s*/', ' : ', $title);
        $title = preg_replace('/\(\s*\)/', '', $title); // empty parentheses
        $title = trim($title, " \t:—–-");

        // 10. Reject if too short (< 20 chars = probably broken/truncated)
        if (mb_strlen($title) < 20) {
            $countryName = $country ? ($this->geoMeta->getGeoPlacename($country, 'fr') ?? $country) : '';
            $title = ucfirst($keyword) . ($countryName ? " — {$countryName}" : '') . " ({$year})";
        }

        // 11. Truncate if too long — adaptive limit
        // Brand-listing titles (Wise, Airbnb, SOS-Expat.com...) can be up to 140 chars
        $brandPattern = '/\b(SOS-Expat\.com|Wise|Revolut|Airbnb|Uber|Booking|Skyscanner|Duolingo|N26|PayPal|Nomad List)\b/iu';
        $hasBrands = preg_match_all($brandPattern, $title) >= 2;
        $maxLen = $hasBrands ? 140 : 90;

        if (mb_strlen($title) > $maxLen) {
            $truncated = mb_substr($title, 0, $maxLen);
            $lastSpace = mb_strrpos($truncated, ' ');
            $title = ($lastSpace && $lastSpace > 60) ? mb_substr($truncated, 0, $lastSpace) : $truncated;
        }

        return trim($title);
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

        $result = $this->aiComplete($systemPrompt, "Titre: {$title}{$factsContext}", [
            'model' => 'gpt-4o-mini',
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

        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.5,
            'max_tokens' => 200,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($result['success'] && !empty($result['content'])) {
            $snippet = trim($result['content']);
            // Wrap in paragraph with bold for emphasis
            $snippetHtml = '<p class="featured-snippet"><strong>' . e($snippet) . '</strong></p>';

            // Insert BEFORE first <h2> (after introduction, before first section)
            $html = $article->content_html;
            $pos = strpos($html, '<h2');
            if ($pos !== false) {
                $html = substr($html, 0, $pos) . $snippetHtml . "\n" . substr($html, $pos);
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

        $result = $this->aiComplete($systemPrompt, "Titre: {$title}\n\nContenu (extrait):\n{$contentExcerpt}", [
            'model' => 'gpt-4o-mini',
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

        $result = $this->aiComplete($systemPrompt,
            "Titre: {$title}\nExcerpt: {$excerpt}\nMot-clé: {$primaryKeyword}", [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 300,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            $metaTitle = strip_tags($parsed['meta_title'] ?? $title);
            $metaDesc = strip_tags($parsed['meta_description'] ?? $excerpt);

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

            // Validation meta_description : smart-truncate (word-aware)
            if (mb_strlen($metaDesc) > 155) {
                $metaDesc = \App\Support\SmartTruncate::run($metaDesc, 155);
            }

            return [
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
            ];
        }

        // Fallback structuré — used when the LLM call fails. Be defensive
        // about the primaryKeyword: older callsites may pass a value still
        // polluted by template artifacts ("guide complet", "budget detaille",
        // etc.) from extractKeywords() edge cases. Strip them here so we
        // never ship a broken meta_title downstream.
        $year = date('Y');
        $cleanKw = $this->sanitizeFallbackKeyword($primaryKeyword);

        // Prefer the article H1 title over a polluted keyword when the
        // keyword looks broken (empty after sanitisation or still contains
        // artifacts). The H1 is LLM-generated and usually clean.
        $baseLabel = $cleanKw !== '' ? $cleanKw : strip_tags($title);
        $baseLabel = trim($baseLabel);

        // Proper title-case: capitalise every word, not just the first.
        $baseLabelCased = mb_convert_case($baseLabel, MB_CASE_TITLE, 'UTF-8');

        return [
            'meta_title' => mb_substr($baseLabelCased . ' : Guide Pratique ' . $year . ' | SOS-Expat', 0, 60),
            'meta_description' => mb_substr('Découvrez notre guide pratique sur ' . mb_strtolower($baseLabel) . '. Conseils, démarches et informations à jour en ' . $year . '.', 0, 155),
        ];
    }

    /**
     * Strip template artifacts that leaked from extractKeywords() legacy runs.
     * These are descriptor phrases that were meant as topic classifiers
     * ("guide complet", "budget detaille", "toutes les options") but got
     * concatenated into the primary keyword by the old pipeline.
     */
    private function sanitizeFallbackKeyword(string $kw): string
    {
        $kw = mb_strtolower(trim($kw));
        $artifacts = [
            'guide complet',
            'guide pratique',
            'budget detaille',
            'budget détaillé',
            'toutes les options',
            'marche de l\'emploi et opportunites',
            'marché de l\'emploi et opportunités',
            'en tant qu\'expatrie',
            'en tant qu\'expatrié',
            'tout savoir',
        ];
        foreach ($artifacts as $a) {
            $kw = str_ireplace($a, '', $kw);
        }
        // Collapse "en en", "de de", etc.
        $kw = preg_replace('/\b(\w+)\s+\1\b/u', '$1', $kw);
        // Strip leading/trailing connector words
        $kw = preg_replace('/^(en|de|du|la|le|les|des|et|sur|pour)\s+/', '', $kw);
        $kw = preg_replace('/\s+(en|de|du|la|le|les|des|et|sur|pour)$/', '', $kw);
        // Normalize whitespace + dangling punctuation
        $kw = preg_replace('/[\s:;,]+/', ' ', $kw);
        return trim($kw);
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
            . "  \"ai_summary\": \"Résumé factuel max 160 caractères pour les moteurs IA (PAS de marketing, commence par les faits)\"\n"
            . "}\n\n"
            . "IMPORTANT:\n"
            . "- og_title : plus ÉMOTIONNEL et ENGAGEANT que le meta_title (qui est SEO)\n"
            . "- og_description : doit donner envie de CLIQUER et PARTAGER\n"
            . "- ai_summary : STRICTEMENT FACTUEL, max 160 caractères, commence par les faits, pas par 'Cet article...'";

        $userPrompt = "Meta title actuel: {$metaTitle}\nMeta desc actuelle: {$metaDescription}\nTitre H1: {$title}\nContenu: {$contentSnippet}";

        try {
            $result = $this->aiComplete($systemPrompt, $userPrompt, [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 600,
                'json_mode' => true,
            ]);

            if ($result['success']) {
                $parsed = json_decode($result['content'], true);
                if ($parsed) {
                    return [
                        'og_title'       => \App\Support\SmartTruncate::run($parsed['og_title'] ?? $metaTitle, 95),
                        'og_description' => \App\Support\SmartTruncate::run($parsed['og_description'] ?? $metaDescription, 200),
                        'ai_summary'     => \App\Support\SmartTruncate::run($parsed['ai_summary'] ?? $excerpt, 160),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Phase 7b AEO meta fallback', ['error' => $e->getMessage()]);
        }

        // Fallback: derive from existing meta
        return [
            'og_title'       => \App\Support\SmartTruncate::run($title, 95),
            'og_description' => \App\Support\SmartTruncate::run($metaDescription, 200),
            'ai_summary'     => \App\Support\SmartTruncate::run($excerpt, 160),
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

        // Pre-validate ALL candidate URLs in parallel to filter out 404s before
        // we inject them. Without this, ~25% of injected links were dead, which
        // is a quality signal Google penalises and looks unprofessional.
        $validator = app(\App\Services\Content\UrlValidatorService::class);
        $directoryLinks = $this->getDirectoryLinksForArticle($article);
        $sourcesSlice   = array_slice($sources, 0, 4);

        $candidateUrls = array_filter(array_merge(
            array_column($directoryLinks, 'url'),
            array_map(fn ($s) => $s['url'] ?? '', $sourcesSlice),
        ));
        $validity = $validator->isValidBatch($candidateUrls);
        $isAlive = fn (string $url) => $validity[$url] ?? true;

        // 1. Inject directory links from country_directory (if article has a country)
        foreach ($directoryLinks as $dirLink) {
            if (in_array($dirLink['domain'], $usedDomains, true)) {
                continue;
            }
            if (!$isAlive($dirLink['url'])) {
                Log::info('phase10: dropped dead directory link', [
                    'article_id' => $article->id,
                    'url'        => $dirLink['url'],
                ]);
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

        // 2. Add Perplexity research sources (already pre-validated above)
        foreach ($sourcesSlice as $source) {
            $domain = $source['domain'] ?? parse_url($source['url'] ?? '', PHP_URL_HOST) ?? '';
            $url = $source['url'] ?? '';

            if (empty($url) || in_array($domain, $usedDomains, true)) {
                continue;
            }
            if (!$isAlive($url)) {
                Log::info('phase10: dropped dead research source', [
                    'article_id' => $article->id,
                    'url'        => $url,
                ]);
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
            'brand_content'   => ['ambassade', 'immigration', 'sante', 'urgences', 'juridique'],
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
                // Alt text = article title in the article's language, plus the
                // country in parentheses. Intentionally drops the historical
                // "keyword prefix" because keywords_primary may contain legacy
                // template artifacts ("cout de la vie en en budget detaille")
                // that would end up in user-visible alt text.
                $altText = $article->title
                    . ($article->country ? ' (' . $article->country . ')' : '');
                $altText = mb_substr(trim($altText), 0, 125);

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

        // Unsplash search with fallback keywords strategy
        if ($this->unsplash->isConfigured()) {
            // Try multiple keyword strategies until we get images
            $keywordStrategies = array_filter([
                $keywords,                                                              // Original: "Visa digital nomad Thailande"
                $article->country ? ($keywords . ' ' . $article->country) : null,       // Add country code
                $article->country ? ('expatriate ' . $article->country) : null,         // Generic: "expatriate TH"
                $article->country ? ('travel ' . $article->country . ' city') : null,   // Scenic: "travel TH city"
                'expatriate international',                                              // Ultimate fallback
            ]);

            // Use searchUnique() instead of search() so we get photos that
            // have NEVER been published on the blog. Tries each keyword
            // strategy until we find at least one fresh photo.
            $result = ['success' => false, 'images' => []];
            $usedQuery = '';
            foreach ($keywordStrategies as $searchTerms) {
                $result = $this->unsplash->searchUnique($searchTerms, 3, 'landscape', 5);
                if ($result['success'] && !empty($result['images'])) {
                    $usedQuery = $searchTerms;
                    break;
                }
            }

            if ($result['success'] && !empty($result['images'])) {
                $tracker = app(\App\Services\AI\UnsplashUsageTracker::class);
                $firstImageUrl = null;
                $firstImageAlt = null;

                foreach ($result['images'] as $index => $image) {
                    $altText = $article->title
                        . ($article->country ? ' (' . $article->country . ')' : '');
                    $altText = mb_substr(trim($altText), 0, 125);

                    $article->images()->create([
                        'url' => $image['url'],
                        'alt_text' => $altText,
                        'source' => 'unsplash',
                        'attribution' => $image['attribution'],
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'sort_order' => $index,
                    ]);

                    // Mark this photo as used so no other article picks it up
                    $tracker->markUsed(
                        $image['url'],
                        $article->id,
                        $article->language,
                        $article->country,
                        $usedQuery,
                        $image['photographer_name'] ?? null,
                        $image['photographer_url'] ?? null,
                    );

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
                    Log::info('Phase 12: Featured image set from Unsplash (unique)', [
                        'article_id' => $article->id,
                        'photographer' => $firstImage['photographer_name'] ?? 'unknown',
                        'query' => $usedQuery,
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
        $readabilityWeight = 0.20;
        $lengthWeight = 0.20;
        $faqWeight = 0.20;

        $seoNormalized = min(100, $seoResult->overall_score);

        // Readability: French text scores ~15-20 pts lower than English on Flesch-Kincaid.
        // Compensate with a +15 bonus so well-written French content (raw 40-55) reaches 55-70.
        $readabilityNormalized = min(100, $readabilityScore + 15);

        // Length score: calibrated per content type (not hardcoded 1500-3000)
        $wordCount = $article->word_count ?? 0;
        $contentType = $article->content_type ?? 'article';
        $typeTargets = ContentTypeConfig::get($contentType);
        $minWords = $typeTargets['min_words'] ?? 1200;
        $maxWords = $typeTargets['max_words'] ?? 5000;

        if ($wordCount >= $minWords && $wordCount <= $maxWords) {
            $lengthNormalized = 100;
        } elseif ($wordCount >= $minWords * 0.7) {
            // 70-99% of minimum → scaled 60-99
            $lengthNormalized = (int) (60 + 40 * (($wordCount - $minWords * 0.7) / ($minWords * 0.3)));
        } elseif ($wordCount >= $minWords * 0.4) {
            $lengthNormalized = 40;
        } elseif ($wordCount > 0) {
            $lengthNormalized = 20;
        } else {
            $lengthNormalized = 0;
        }
        // Slight penalty for excessively long articles (>150% of max)
        if ($wordCount > $maxWords * 1.5) {
            $lengthNormalized = max(70, $lengthNormalized - 10);
        }

        // FAQ completeness: calibrated per content type
        $faqCount = $article->faqs()->count();
        $expectedFaqs = $typeTargets['faq_count'] ?? 6;
        $faqNormalized = $expectedFaqs > 0
            ? min(100, (int) (($faqCount / $expectedFaqs) * 100))
            : 100; // types with faq_count=0 get full marks

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

    /**
     * Phase 14b — LLM Editorial Judge.
     *
     * Grades the finalised article against strict editorial criteria that
     * mechanical scoring (phase14) cannot catch: does the title match a real
     * Google search query? Is the meta_description compelling? Is the prose
     * free of AI-pattern filler? Are the numbers plausible?
     *
     * Returns an associative array with per-dimension scores /100, an overall
     * score /100, and a list of concrete issues. Results are persisted on the
     * article (editorial_score + editorial_review JSON) so the publication
     * gate in GenerateArticleJob::autoPublish() can block low-scoring pieces.
     */
    private function phase14b_editorialJudge(GeneratedArticle $article): array
    {
        $title = $article->title ?? '';
        $metaTitle = $article->meta_title ?? '';
        $metaDesc = $article->meta_description ?? '';
        $contentText = mb_substr(strip_tags($article->content_html ?? ''), 0, 6000);
        $country = $article->country ?? '';
        $contentType = $article->content_type ?? 'article';
        $language = $article->language ?? 'fr';
        $year = date('Y');

        $systemPrompt = "Tu es un RÉDACTEUR EN CHEF d'un magazine international d'expatriation. "
            . "Tu évalues un article déjà rédigé selon 5 critères stricts. "
            . "Tu retournes UNIQUEMENT un JSON valide, sans commentaire.\n\n"
            . "Format JSON exigé :\n"
            . "{\n"
            . "  \"title_score\": 0-100,\n"
            . "  \"title_feedback\": \"une phrase précise\",\n"
            . "  \"meta_score\": 0-100,\n"
            . "  \"meta_feedback\": \"une phrase précise\",\n"
            . "  \"content_score\": 0-100,\n"
            . "  \"content_feedback\": \"une phrase précise\",\n"
            . "  \"fact_score\": 0-100,\n"
            . "  \"fact_feedback\": \"une phrase précise\",\n"
            . "  \"intent_score\": 0-100,\n"
            . "  \"intent_feedback\": \"une phrase précise\",\n"
            . "  \"overall\": 0-100,\n"
            . "  \"issues\": [\"probleme1\", \"probleme2\", ...],\n"
            . "  \"recommended_title\": \"suggestion si title_score<70, sinon null\",\n"
            . "  \"recommended_meta_title\": \"suggestion si meta_score<70, sinon null\",\n"
            . "  \"recommended_meta_description\": \"suggestion si meta_score<70, sinon null\"\n"
            . "}\n\n"
            . "RÈGLES DE NOTATION STRICTES :\n\n"
            . "1. TITLE (title_score) — est-ce une VRAIE requête Google ?\n"
            . "   - 90-100 : le titre EST exactement ce qu'un utilisateur tape dans Google (ex: 'Comment obtenir un visa pour le Japon ?').\n"
            . "   - 70-89 : proche d'une requête naturelle, contient le mot-clé + pays + année.\n"
            . "   - 50-69 : descriptif plat, fonctionnel mais pas engageant (ex: 'Visa Japon 2026').\n"
            . "   - 30-49 : mal formulé, grammaire bancale, ou générique type 'Guide complet'.\n"
            . "   - 0-29  : cassé, tronqué, keyword-stuffing évident.\n\n"
            . "2. META (meta_score) — meta_title + meta_description compelling ?\n"
            . "   - 90-100 : meta_title 50-60 chars avec hook + année + mot-clé ; meta_description 140-155 chars avec verbe d'action + bénéfice concret + CTA.\n"
            . "   - 70-89 : acceptable mais manque un élément (pas de CTA, longueur sous-optimale, etc.).\n"
            . "   - 50-69 : fonctionnel mais générique (commence par 'Découvrez...' générique).\n"
            . "   - 0-49  : vide, cassé, duplication du titre, patterns IA évidents.\n\n"
            . "3. CONTENT (content_score) — prose libre de patterns IA ?\n"
            . "   - Pénalise : 'Il est important de noter', 'Il convient de souligner', 'Dans cet article nous allons', 'N'hésitez pas à', 'Plongeons dans'.\n"
            . "   - Pénalise : répétition de sections, phrases passe-partout applicables à n'importe quel pays.\n"
            . "   - 90-100 : phrases variées, anecdotes concrètes, chiffres précis, zéro pattern IA.\n"
            . "   - 50-69 : lisible mais présence de 2-3 patterns IA.\n"
            . "   - 0-49  : patterns IA massifs, contenu générique, remplissage.\n\n"
            . "4. FACTS (fact_score) — les chiffres et affirmations sont-ils plausibles ?\n"
            . "   - 90-100 : tous les chiffres sont dans l'ordre de grandeur attendu pour {$country} ({$year}).\n"
            . "   - 70-89 : la plupart plausibles, 1 ou 2 doutes mineurs.\n"
            . "   - 50-69 : 1-2 chiffres suspects (ex: 'loyer à Bangkok = 10 000 USD' — hors-sol).\n"
            . "   - 0-49  : chiffres visiblement hallucinés ou contradictoires.\n\n"
            . "5. INTENT (intent_score) — le titre et le contenu matchent-ils l'intention de recherche ?\n"
            . "   - Un titre 'Comment obtenir X' doit répondre au processus.\n"
            . "   - Un titre 'Meilleur X' doit comparer et recommander.\n"
            . "   - Un titre 'Combien coûte X' doit donner un prix précis.\n"
            . "   - 90-100 : content répond EXACTEMENT à la promesse du titre.\n"
            . "   - 50-69 : content tangente le sujet mais ne répond pas directement.\n"
            . "   - 0-49  : content hors-sujet par rapport au titre.\n\n"
            . "overall = moyenne pondérée : title×25% + meta×15% + content×25% + facts×20% + intent×15%";

        $userPrompt = "Pays : {$country} | Langue : {$language} | Type : {$contentType} | Année : {$year}\n\n"
            . "TITRE : {$title}\n"
            . "META_TITLE : {$metaTitle}\n"
            . "META_DESCRIPTION : {$metaDesc}\n\n"
            . "EXTRAIT DU CONTENU (6000 chars max) :\n{$contentText}";

        try {
            $result = $this->aiComplete($systemPrompt, $userPrompt, [
                'model' => 'gpt-4o-mini',   // fast + cheap, sufficient for scoring
                'temperature' => 0.3,       // low temp for stable scoring
                'max_tokens' => 900,
                'json_mode' => true,
            ]);

            if (empty($result['success']) || empty($result['content'])) {
                Log::warning('phase14b: editorial judge call failed, defaulting to pass', [
                    'article_id' => $article->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return ['overall' => 75, 'skipped' => true, 'reason' => 'judge_unavailable'];
            }

            $report = json_decode($result['content'], true);
            if (!is_array($report) || !isset($report['overall'])) {
                Log::warning('phase14b: judge returned invalid JSON', [
                    'article_id' => $article->id,
                    'raw' => mb_substr((string) $result['content'], 0, 200),
                ]);
                return ['overall' => 75, 'skipped' => true, 'reason' => 'judge_invalid_json'];
            }

            // Clamp all scores to 0-100
            foreach (['title_score', 'meta_score', 'content_score', 'fact_score', 'intent_score', 'overall'] as $key) {
                if (isset($report[$key])) {
                    $report[$key] = max(0, min(100, (int) $report[$key]));
                }
            }

            $report['judged_at'] = now()->toIso8601String();
            $report['judge_model'] = 'gpt-4o-mini';

            // Persist on the article for the gate in GenerateArticleJob
            $article->update([
                'editorial_score'  => (int) $report['overall'],
                'editorial_review' => $report,
            ]);

            Log::info('phase14b: editorial judge completed', [
                'article_id' => $article->id,
                'overall'    => $report['overall'],
                'title'      => $report['title_score'] ?? null,
                'meta'       => $report['meta_score'] ?? null,
                'content'    => $report['content_score'] ?? null,
                'facts'      => $report['fact_score'] ?? null,
                'intent'     => $report['intent_score'] ?? null,
                'issues'     => $report['issues'] ?? [],
            ]);

            return $report;
        } catch (\Throwable $e) {
            Log::error('phase14b: editorial judge exception', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
            return ['overall' => 75, 'skipped' => true, 'reason' => 'judge_exception'];
        }
    }

    /**
     * Phase 14c — Auto-improve from judge recommendations.
     *
     * When the editorial judge (phase14b) returns an overall score below the
     * quality bar AND provides concrete recommended rewrites, this method
     * applies them in-place on the article. This is a best-effort pass —
     * if the recommendations are invalid (empty strings, too long, etc.)
     * we silently skip them and let the original content stand.
     *
     * Public on purpose: GenerateArticleJob::autoPublish() calls this right
     * before the publication queue push so the final version sent to the
     * blog is the improved one.
     *
     * Returns true if at least one field was actually changed.
     */
    public function phase14c_improveFromJudgeRecommendations(GeneratedArticle $article, array $judgeReport): bool
    {
        $updates = [];
        $changedFields = [];

        // Title improvement — only if judge scored it < 70 AND gave a recommendation
        $titleScore = (int) ($judgeReport['title_score'] ?? 100);
        $recommendedTitle = trim((string) ($judgeReport['recommended_title'] ?? ''));
        if ($titleScore < 70 && $recommendedTitle !== '' && $recommendedTitle !== 'null') {
            // Run the recommended title through cleanTitle to enforce year,
            // capitalisation rules, and length limits just like fresh output.
            $primaryKeyword = $article->keywords_primary
                ?? ($article->title ?? $recommendedTitle);
            $cleaned = $this->cleanTitle(
                $recommendedTitle,
                $primaryKeyword,
                $article->country,
                (string) date('Y')
            );
            if (mb_strlen($cleaned) >= 20 && $cleaned !== $article->title) {
                $updates['title'] = $cleaned;
                $changedFields[] = 'title';
            }
        }

        // Meta title improvement
        $metaScore = (int) ($judgeReport['meta_score'] ?? 100);
        $recommendedMetaTitle = trim((string) ($judgeReport['recommended_meta_title'] ?? ''));
        if ($metaScore < 70 && $recommendedMetaTitle !== '' && $recommendedMetaTitle !== 'null') {
            // Strip trailing pipe/whitespace and clamp to 60 chars (Google cap).
            $clean = preg_replace('/[\s|]+$/u', '', $recommendedMetaTitle);
            if (mb_strlen($clean) > 60) {
                $truncated = mb_substr($clean, 0, 60);
                $lastSpace = mb_strrpos($truncated, ' ');
                $clean = ($lastSpace && $lastSpace > 40) ? mb_substr($truncated, 0, $lastSpace) : $truncated;
            }
            if (mb_strlen($clean) >= 20 && $clean !== $article->meta_title) {
                $updates['meta_title'] = $clean;
                $changedFields[] = 'meta_title';
            }
        }

        // Meta description improvement — 140-155 chars target
        $recommendedMetaDesc = trim((string) ($judgeReport['recommended_meta_description'] ?? ''));
        if ($metaScore < 70 && $recommendedMetaDesc !== '' && $recommendedMetaDesc !== 'null') {
            $clean = strip_tags($recommendedMetaDesc);
            if (mb_strlen($clean) > 155) {
                $truncated = mb_substr($clean, 0, 155);
                $lastDot = mb_strrpos($truncated, '.');
                $clean = ($lastDot && $lastDot > 100) ? mb_substr($truncated, 0, $lastDot + 1) : $truncated;
            }
            if (mb_strlen($clean) >= 80 && $clean !== $article->meta_description) {
                $updates['meta_description'] = $clean;
                $changedFields[] = 'meta_description';
            }
        }

        if (empty($updates)) {
            return false;
        }

        $article->update($updates);

        // Mark the editorial_review JSON so we can audit what was changed
        // and never re-apply the same recommendation twice.
        $report = $article->editorial_review ?? [];
        $report['auto_improvements'] = [
            'applied_at' => now()->toIso8601String(),
            'fields'     => $changedFields,
        ];
        $article->update(['editorial_review' => $report]);

        $this->logPhase(
            $article,
            'editorial_improve',
            'success',
            'fields=' . implode(',', $changedFields),
            0, 0, 0
        );

        Log::info('phase14c: auto-improvement applied', [
            'article_id'    => $article->id,
            'changed_fields' => $changedFields,
            'title_score_before' => $titleScore,
            'meta_score_before'  => $metaScore,
        ]);

        return true;
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
    // Phase 5 — Multi-Prompt Content Pipeline
    // ============================================================

    /**
     * Strip markdown fences, HTML page wrappers, and leftover H1 from AI output.
     */
    private function cleanAiHtml(string $raw): string
    {
        $content = trim($raw);

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

        // Strip <img>, <figure>, <picture> tags — images are added by
        // phase12 separately as featured images. The LLM should not embed
        // them inline in sections. Remove both the tag itself AND any
        // "Photo by ... on Unsplash" attribution text that follows.
        $content = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $content);
        $content = preg_replace('/<picture[^>]*>.*?<\/picture>/is', '', $content);
        $content = preg_replace('/<img[^>]*>/i', '', $content);

        // Strip hallucinated image captions that the LLM sometimes injects
        // inline in the prose (text form OR HTML-wrapped with <a> links):
        //   "Photo by John Doe on Unsplash"
        //   "Photo by <a href="...">John Doe</a> on <a href="...">Unsplash</a>"
        //   "Crédit photo: ...", "Source: Unsplash", "[Image: ...]", etc.
        $imageCaptionPatterns = [
            // HTML-wrapped "Photo by <a>Name</a> on <a>Unsplash</a>"
            '/\s*Photo by\s*(<a[^>]*>[^<]*<\/a>|[^.\n<]{1,80}?)\s*on\s*(<a[^>]*>Unsplash<\/a>|Unsplash)\s*\.?/iu',
            '/\s*Crédit(s)? photo\s*:\s*[^.\n<]{1,80}\.?/iu',
            '/\s*Photo credit\s*:\s*[^.\n<]{1,80}\.?/iu',
            '/\s*Source\s*:\s*Unsplash\.?/iu',
            '/\s*\(via Unsplash\)/iu',
            '/\s*\[Image\s*:[^\]]*\]/iu',
            '/\s*Image\s*:\s*[^.\n<]{1,80}\.?/iu',
        ];
        foreach ($imageCaptionPatterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Convert leaked markdown headers to HTML. The LLM sometimes mixes
        // markdown syntax ("### Section title") in its HTML output when it
        // gets lazy. Convert to <h3>/<h2> instead of leaving raw pounds.
        // Only convert lines that start with # (not mid-sentence hashtags).
        $content = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $content);
        // Standalone # title at line start = h1 which we don't allow in body
        $content = preg_replace('/^#\s+(.+)$/m', '<h2>$1</h2>', $content);

        // Clean up any now-empty paragraphs left by tag stripping
        $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
        $content = preg_replace('/<div>\s*<\/div>/i', '', $content);

        return trim($content);
    }

    /**
     * Phase 5a — Editorial Strategist: generate a structured outline (JSON).
     *
     * Returns an associative array with: angle, tone_guidance, narrative_arc, sections[].
     */
    private function phase05a_generateOutline(string $title, string $excerpt, array $facts, array $params, array $typeConfig): array
    {
        $language    = $params['language'] ?? 'fr';
        $contentType = $params['content_type'] ?? 'article';
        $country     = $params['country'] ?? null;
        $keywords    = $params['keywords'] ?? [];
        $lsiKeywords = $params['lsi_keywords'] ?? [];
        $primaryKw   = $keywords[0] ?? $params['topic'] ?? '';

        $h2Range     = $typeConfig['h2_count'] ?? [8, 12];
        $h2Min       = is_array($h2Range) ? $h2Range[0] : 8;
        $h2Max       = is_array($h2Range) ? $h2Range[1] : 12;
        $targetWords = $typeConfig['target_words_range'] ?? '3000-4500';

        $factsStr = !empty($facts) ? implode("\n- ", array_slice($facts, 0, 15)) : '';

        // Summarise prompt_suffix for the strategist (avoid dumping full writing instructions)
        $promptSuffix = $typeConfig['prompt_suffix'] ?? '';
        $suffixSummary = $promptSuffix ? mb_substr(strip_tags($promptSuffix), 0, 400) : '';

        // Intent-specific outline guidance
        $searchIntent = $params['search_intent'] ?? $params['intent'] ?? self::defaultIntent($contentType);
        $intentOutlineGuide = match ($searchIntent) {
            'informational' => "INTENTION : INFORMATIONNELLE — l'utilisateur veut APPRENDRE.\n"
                . "- Structure pedagogique : du simple au complexe.\n"
                . "- Chaque H2 = une sous-question que l'utilisateur se pose.\n"
                . "- Prevois des sections : definition, processus, couts, delais, erreurs a eviter, FAQ.\n",
            'commercial_investigation' => "INTENTION : INVESTIGATION COMMERCIALE — l'utilisateur veut COMPARER.\n"
                . "- La PREMIERE section DOIT etre un tableau comparatif.\n"
                . "- Puis 1 section par option comparee (pros/cons).\n"
                . "- Terminer par un verdict argumente + section 'Comment choisir'.\n",
            'transactional' => "INTENTION : TRANSACTIONNELLE — l'utilisateur veut AGIR MAINTENANT.\n"
                . "- Article COURT ({$h2Min}-{$h2Max} sections max).\n"
                . "- 1ere section = prix/tarifs. 2e section = etapes d'action en <ol>.\n"
                . "- Chaque section = reponse a 'comment faire' ou 'combien ca coute'.\n",
            'urgency' => "INTENTION : URGENCE — l'utilisateur a un probleme MAINTENANT.\n"
                . "- Article COURT ({$h2Min}-{$h2Max} sections max).\n"
                . "- 1ere section = numeros d'urgence. 2e section = etapes immediates en <ol>.\n"
                . "- Chaque section = une action concrete et immediate.\n",
            'local' => "INTENTION : LOCALE — l'utilisateur cherche un service dans un lieu precis.\n"
                . "- 1ere section = tableau des ressources locales.\n"
                . "- Sections par type de ressource (ambassade, pros, associations).\n"
                . "- Focus sur les informations pratiques (adresses, horaires, contacts).\n",
            default => '',
        };

        $systemPrompt = $this->kbPrompt . "\n\n"
            . "Tu es un STRATEGE EDITORIAL pour un magazine international d'expatriation de premier plan.\n"
            . "Ta mission : concevoir le PLAN STRUCTUREL d'un article de reference.\n\n"
            . "REGLE CRITIQUE : le contenu s'adresse a TOUTE nationalite d'expatrie (pas uniquement les Francais). "
            . "Les sections doivent etre formulees de maniere universelle ('votre ambassade', pas 'l'ambassade de France').\n\n"
            . "Tu ne rediges PAS l'article. Tu crees son architecture editoriale.\n\n"
            . (!empty($intentOutlineGuide) ? $intentOutlineGuide . "\n" : '')
            . "REGLES :\n"
            . "- L'article doit viser {$targetWords} mots au total.\n"
            . "- Le plan comporte entre {$h2Min} et {$h2Max} sections H2.\n"
            . "- Le mot-cle principal \"{$primaryKw}\" doit apparaitre dans au moins 2 titres H2 (variantes/synonymes acceptes).\n"
            . "- Au moins 3 H2 sur {$h2Min} doivent etre formules comme des QUESTIONS GOOGLE reelles (People Also Ask) :\n"
            . "  'Comment...?', 'Combien coute...?', 'Faut-il...?', 'Quand...?', 'Pourquoi...?'\n"
            . "- Chaque section doit avoir un OBJECTIF CLAIR : informer, comparer, guider, rassurer, convaincre.\n"
            . "- Prevois un ARC NARRATIF : l'article doit progresser emotionnellement (curiosite → comprehension → confiance → action).\n"
            . "- Distribue les mots-cles secondaires et LSI de facon naturelle entre les sections.\n\n"
            . "Retourne UNIQUEMENT du JSON valide, sans aucun texte avant ou apres.\n"
            . "Langue: {$language}.\n\n"
            . "Structure JSON attendue :\n"
            . "{\n"
            . "  \"angle\": \"L'angle editorial unique de cet article (1 phrase)\",\n"
            . "  \"tone_guidance\": \"Directives de ton pour les redacteurs (ami expert, chaleureux, precis)\",\n"
            . "  \"narrative_arc\": \"Comment l'article progresse emotionnellement du debut a la fin\",\n"
            . "  \"sections\": [\n"
            . "    {\n"
            . "      \"heading\": \"Titre H2 de la section\",\n"
            . "      \"subheadings\": [\"H3 optionnel\", \"H3 optionnel\"],\n"
            . "      \"key_points\": [\"point cle 1\", \"point cle 2\", \"point cle 3\"],\n"
            . "      \"target_words\": 350,\n"
            . "      \"keywords_to_use\": [\"mot-cle 1\", \"mot-cle 2\"]\n"
            . "    }\n"
            . "  ]\n"
            . "}";

        $userPrompt = "Titre de l'article : {$title}\n"
            . "Resume : {$excerpt}\n"
            . "Type de contenu : {$contentType}\n"
            . "Mot-cle principal : {$primaryKw}\n"
            . (!empty($keywords) ? "Mots-cles secondaires : " . implode(', ', array_slice($keywords, 1, 5)) . "\n" : '')
            . (!empty($lsiKeywords) ? "Mots-cles LSI : " . implode(', ', array_slice($lsiKeywords, 0, 15)) . "\n" : '')
            . (!empty($factsStr)
                ? "Faits de recherche :\n- {$factsStr}\n"
                : "AUCUN fait de recherche disponible. Utilise tes PROPRES connaissances pour creer un plan COMPLET et DETAILLE. "
                  . "Chaque section doit avoir 3-5 key_points concrets. Vise le MAXIMUM de sections ({$h2Max}).\n")
            . (!empty($suffixSummary) ? "\nDirectives specifiques au type de contenu :\n{$suffixSummary}\n" : '');

        // Geo context for country-specific guides
        if ($country && in_array($contentType, ['guide', 'pillar', 'guide_city'], true)) {
            $geoContext = $this->geoMeta->buildCountryContextForPrompt($country, $language);
            if (!empty($geoContext)) {
                $userPrompt = $geoContext . "\n\n" . $userPrompt;
            }
        }

        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model'      => $typeConfig['model'] ?? null,
            'temperature' => $typeConfig['temperature'] ?? 0.7,
            'max_tokens' => 2500,
            'json_mode'  => true,
        ]);

        if ($result['success'] && !empty($result['content'])) {
            $parsed = json_decode($result['content'], true);
            if (is_array($parsed) && !empty($parsed['sections'])) {
                return $parsed;
            }

            // Try extracting JSON block if wrapped in text
            if (preg_match('/\{[\s\S]+\}/u', $result['content'], $m)) {
                $parsed = json_decode($m[0], true);
                if (is_array($parsed) && !empty($parsed['sections'])) {
                    return $parsed;
                }
            }
        }

        // Fallback: generic 8-section skeleton
        Log::warning('phase05a: outline generation failed or invalid JSON, using fallback skeleton', [
            'topic' => $params['topic'] ?? '',
        ]);

        $wordBudget = (int) (((int) explode('-', $targetWords)[0]) / 8);
        return [
            'angle' => "Article informatif sur {$title}",
            'tone_guidance' => 'Ami expert, chaleureux mais precis, avec anecdotes concretes',
            'narrative_arc' => 'Curiosite → Comprehension → Confiance → Action',
            'sections' => array_map(fn ($i) => [
                'heading' => "Section {$i}",
                'subheadings' => [],
                'key_points' => [],
                'target_words' => $wordBudget,
                'keywords_to_use' => $i <= 2 ? [$primaryKw] : [],
            ], range(1, 8)),
        ];
    }

    /**
     * Phase 5b — Journalist / Storyteller: generate a captivating introduction.
     *
     * Returns HTML string (paragraphs only, no H2).
     */
    private function phase05_generateIntroduction(string $title, array $outline, array $facts, array $params, array $typeConfig = []): string
    {
        $language    = $params['language'] ?? 'fr';
        $country     = $params['country'] ?? null;
        $contentType = $params['content_type'] ?? 'article';
        $topicText   = $params['topic'] ?? '';
        $year        = date('Y');

        $angle       = $outline['angle'] ?? '';
        $toneGuide   = $outline['tone_guidance'] ?? '';
        $narrativeArc = $outline['narrative_arc'] ?? '';

        // Intent-specific featured snippet rules for the opening paragraph
        $searchIntent = $params['search_intent'] ?? $params['intent'] ?? self::defaultIntent($contentType);
        $featuredSnippetRule = match ($searchIntent) {
            'informational' => "FEATURED SNIPPET (CRITIQUE POSITION 0) :\n"
                . "Le TOUT PREMIER paragraphe de l'intro DOIT etre une reponse directe de 50-80 mots.\n"
                . "Commence par reformuler le sujet avec reponse complete + chiffre cle.\n"
                . "Ex: 'Le visa digital nomad en France coute 99EUR en {$year}, s'obtient en 2-4 semaines et ouvre droit a une residence de 1 an renouvelable.'\n"
                . "Encadre-le dans : <div class=\"featured-snippet\"><p>...</p></div>\n"
                . "PUIS enchaine avec l'accroche narrative (anecdote, fait surprenant).\n",
            'commercial_investigation' => "FEATURED SNIPPET (CRITIQUE POSITION 0) :\n"
                . "Le TOUT PREMIER paragraphe DOIT etre un verdict direct de 50-80 mots.\n"
                . "Ex: 'En {$year}, le meilleur X est Y pour Z raison. Voici notre comparatif complet.'\n"
                . "Encadre-le dans : <div class=\"featured-snippet\"><p>...</p></div>\n"
                . "PUIS enchaine avec le contexte du comparatif.\n",
            'transactional' => "FEATURED SNIPPET (CRITIQUE POSITION 0) :\n"
                . "Le TOUT PREMIER paragraphe repond a 'combien ca coute' ou 'comment faire' en 1-2 phrases (50-80 mots).\n"
                . "Encadre-le dans : <div class=\"featured-snippet\"><p>...</p></div>\n"
                . "JUSTE APRES, ajoute le pricing box :\n"
                . "<div class=\"pricing-box\"><p><strong>Tarif</strong></p><p>Avocat : 49EUR/55USD (20 min) | Expert local : 19EUR/25USD (30 min)</p></div>\n",
            'urgency' => "FEATURED SNIPPET (CRITIQUE POSITION 0) :\n"
                . "Le TOUT PREMIER paragraphe = action immediate (50-80 mots).\n"
                . "Ex: 'En cas de passeport perdu au Maroc, appelez immediatement le +212-537-XXX. Voici les 5 etapes a suivre.'\n"
                . "Encadre-le dans : <div class=\"featured-snippet\"><p>...</p></div>\n"
                . "JUSTE APRES, ajoute l'encadre urgence :\n"
                . "<div class=\"emergency-box\"><p><strong>Numeros d'urgence</strong></p><ul><li><strong>Police :</strong> ...</li><li><strong>Ambulance :</strong> ...</li><li><strong>Ambassade :</strong> ...</li></ul></div>\n",
            default => "Le TOUT PREMIER paragraphe doit etre une reponse directe de 50-80 mots encadree dans <div class=\"featured-snippet\"><p>...</p></div>\n",
        };

        $systemPrompt = $this->kbPrompt . "\n\n"
            . "Tu es un JOURNALISTE DU NEW YORK TIMES specialise en mobilite internationale.\n"
            . "Tu ecris des introductions qui CAPTIVENT le lecteur des la premiere phrase.\n\n"
            . "TON OBJECTIF : que le lecteur ne puisse PAS s'arreter de lire apres l'intro.\n\n"
            . $featuredSnippetRule . "\n"
            . "APRES LE FEATURED SNIPPET, enchaine avec l'accroche narrative :\n"
            . "- UNE de ces approches (choisis celle qui colle LE MIEUX au sujet, varies d'un article a l'autre) :\n"
            . "  a) Un FAIT SURPRENANT ou STATISTIQUE : 'En {$year}, 73% des expatries decouvrent que...', 'Moins de 10% des gens savent que...'\n"
            . "  b) Une SITUATION VECUE universelle : 'Vous venez de recevoir votre contrat de travail a l'etranger...', 'Votre valise est bouclee, mais une question reste sans reponse...'\n"
            . "  c) Une QUESTION PROVOCANTE ou CONTRE-INTUITIVE : 'Et si tout ce que vous pensiez savoir sur [sujet] etait faux ?', 'Pourquoi tant d\'expatries font-ils cette erreur ?'\n"
            . "  d) Une ANECDOTE avec un prenom international (en DERNIER RECOURS si les autres n\'ont pas de sens) : 'Quand Aisha a debarque a Dubai...', 'Lorsque Carlos a signe son contrat a Berlin...'\n"
            . "IMPORTANT : N'utilise PAS systematiquement l'option (d). Prefere les options (a), (b) ou (c) qui donnent une accroche plus directe et percutante.\n\n"
            . "PUIS ajoute un RESUME EN BREF apres l'accroche :\n"
            . "<div class=\"summary-box\"><p><strong>En bref</strong></p><ul><li>Point cle 1 avec chiffre</li><li>Point cle 2</li><li>Point cle 3</li></ul></div>\n\n"
            . "REGLES :\n"
            . "- L'intro COMPLETE fait 250-400 mots (featured snippet + accroche + summary box + promesse).\n"
            . "- Termine par une PROMESSE CLAIRE de ce que l'article va apporter au lecteur.\n"
            . "- Mentionne l'annee {$year} naturellement.\n"
            . "- HTML : <div class=\"featured-snippet\">, <div class=\"summary-box\">, <p>. PAS de <h2>.\n"
            . "- Phrases courtes (max 20 mots). Paragraphes courts (3-4 lignes max).\n\n"
            . "STRICTEMENT INTERDIT (ces formules trahissent un texte IA) :\n"
            . "- 'Dans cet article, nous allons...'\n"
            . "- 'Bienvenue dans notre guide...'\n"
            . "- 'Que vous soyez... ou...'\n"
            . "- 'Il est important de noter que...'\n"
            . "- 'Decouvrez dans ce guide...'\n"
            . "- 'Vous vous demandez peut-etre...'\n"
            . "- 'Plongeons dans...'\n"
            . "- 'Dans un monde de plus en plus globalise...'\n"
            . "- Toute phrase generique applicable a n'importe quel pays/sujet\n\n"
            . "Langue: {$language}. Retourne UNIQUEMENT le HTML de l'introduction.";

        $factsSnippet = !empty($facts) ? implode('. ', array_slice($facts, 0, 5)) : '';

        $userPrompt = "Titre de l'article : {$title}\n"
            . "Sujet original : {$topicText}\n"
            . "Angle editorial : {$angle}\n"
            . "Ton souhaite : {$toneGuide}\n"
            . "Arc narratif : {$narrativeArc}\n"
            . (!empty($factsSnippet) ? "Faits de recherche : {$factsSnippet}\n" : '')
            . "Annee : {$year}\n";

        // Brand mention in intro (just SOS-Expat if present in topic)
        if (stripos($topicText, 'SOS-Expat') !== false || stripos($topicText, 'sos-expat') !== false) {
            $userPrompt .= "\nMentionne SOS-Expat.com naturellement dans la promesse de l'intro (service de mise en relation telephonique avec un avocat ou expert local en moins de 5 minutes, 24h/24, 197 pays).\n";
        }

        // Geo context
        if ($country && in_array($contentType, ['guide', 'pillar', 'guide_city'], true)) {
            $geoContext = $this->geoMeta->buildCountryContextForPrompt($country, $language);
            if (!empty($geoContext)) {
                $userPrompt = $geoContext . "\n\n" . $userPrompt;
            }
        }

        // Intro needs slightly higher creativity than the base type temperature
        $introTemp = min(1.0, ($typeConfig['temperature'] ?? 0.7) + 0.1);
        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model'       => $typeConfig['model'] ?? null,
            'temperature' => $introTemp,
            'max_tokens'  => 1500,
        ]);

        if ($result['success'] && !empty($result['content'])) {
            return $this->cleanAiHtml($result['content']);
        }

        // Fallback: use the excerpt wrapped in a paragraph
        Log::warning('phase05_generateIntroduction: failed, falling back to excerpt', [
            'topic' => $params['topic'] ?? '',
        ]);
        return '<p>' . e($params['excerpt'] ?? $title) . '</p>';
    }

    /**
     * Phase 5c — Subject Matter Expert: generate body sections in groups of 2-3.
     *
     * Returns concatenated HTML of all body sections.
     */
    private function phase05c_generateSections(string $title, array $outline, string $introHtml, array $facts, array $params, array $typeConfig): string
    {
        $language    = $params['language'] ?? 'fr';
        $contentType = $params['content_type'] ?? 'article';
        $country     = $params['country'] ?? null;
        $keywords    = $params['keywords'] ?? [];
        $primaryKw   = $keywords[0] ?? $params['topic'] ?? '';
        $lsiKeywords = $params['lsi_keywords'] ?? [];
        $instructions = $params['instructions'] ?? '';
        $topicText   = $params['topic'] ?? '';
        $year        = date('Y');
        $toneGuide   = $outline['tone_guidance'] ?? '';
        $promptSuffix = $typeConfig['prompt_suffix'] ?? '';

        $sections = $outline['sections'] ?? [];
        if (empty($sections)) {
            throw new \RuntimeException('No sections in outline — cannot generate body content');
        }

        // Build the base system prompt (shared by all groups)
        $baseSystemPrompt = $this->kbPrompt . "\n\n"
            . "Tu es un EXPERT THEMATIQUE en expatriation et mobilite internationale.\n"
            . "Tu rediges des sections d'article de REFERENCE — le contenu le plus complet et utile du web sur ce sujet.\n\n"
            . "TON : {$toneGuide}\n"
            . "ANNEE : {$year}\n\n"
            . "REGLE CRITIQUE — MULTI-NATIONALITE :\n"
            . "- SOS-Expat.com est une plateforme MONDIALE. Le contenu s'adresse a TOUTE personne de TOUTE nationalite.\n"
            . "- JAMAIS ecrire uniquement pour les Francais. Un article en francais s'adresse a TOUS les francophones (France, Belgique, Suisse, Canada, Afrique).\n"
            . "- Dire 'votre ambassade' ou 'l'ambassade de votre pays', JAMAIS 'l'ambassade de France'.\n"
            . "- Utiliser des prenoms internationaux varies dans les exemples (Aisha, Carlos, Priya, Kenji, Fatima...), PAS uniquement des prenoms francais.\n"
            . "- Mentionner les demarches/conventions de PLUSIEURS pays d'origine, pas uniquement la France.\n"
            . "- Formuler les conseils de maniere universelle : 'verifiez aupres de votre consulat' (pas 'le consulat de France').\n\n"
            . "QUALITE REDACTIONNELLE ABSOLUE :\n"
            . "- Chaque phrase apporte une INFORMATION NOUVELLE. Zero remplissage. Zero platitude.\n"
            . "- Utilise des ANECDOTES CONCRETES et des SITUATIONS VECUES pour illustrer (prenoms internationaux).\n"
            . "- Donne des CHIFFRES PRECIS, DATES, SOURCES pour chaque affirmation factuelle.\n"
            . "- Phrases courtes (max 25 mots). Paragraphes courts (max 4 lignes).\n"
            . "- Au moins 1 info SURPRENANTE ou PEU CONNUE par section.\n"
            . "- Des TRANSITIONS NARRATIVES entre les sections (pas juste des H2 qui se succedent).\n\n"
            . "STRUCTURE HTML (utilise ces CLASSES CSS EXACTES) :\n"
            . "- Chaque section H2 : 3-5 paragraphes de 80-120 mots\n"
            . "- Au moins 1 liste (<ul>/<ol>) par groupe de sections avec 5+ items\n"
            . "- <strong> pour les termes cles et chiffres importants\n"
            . "- PAS de <h1>. PAS de <html>/<body>/<head>. PAS de classes Tailwind.\n\n"
            . "ENCADRES OBLIGATOIRES (utilise ces classes EXACTES) :\n"
            . "- Conseil : <blockquote class=\"callout-info\"><p><strong>Bon a savoir</strong></p><p>texte</p></blockquote>\n"
            . "- Avertissement : <blockquote class=\"callout-warning\"><p><strong>Attention</strong></p><p>texte</p></blockquote>\n"
            . "- Conseil pratique : <blockquote class=\"callout-tip\"><p><strong>Conseil pratique</strong></p><p>texte</p></blockquote>\n"
            . "- Au moins 2 encadres callout par groupe de sections (info, warning, ou tip).\n"
            . "- 1 tableau comparatif <table> avec <thead>/<tbody> par article si le sujet s'y prete.\n\n"
            . "SEO AVANCE :\n"
            . "- Mot-cle principal \"{$primaryKw}\" : 1-2% densite naturelle\n"
            . "- Mots-cles secondaires repartis naturellement (fournis par section)\n"
            . "- Chaque question H2 trouve sa reponse dans les 60 premiers mots de sa section (featured snippet interne)\n\n"
            . "STRICTEMENT INTERDIT :\n"
            . "- 'Il est important de noter', 'Il convient de souligner', 'Force est de constater'\n"
            . "- 'Il est important de comprendre', 'il est essentiel de', 'il est crucial de'\n"
            . "- 'N'hesitez pas a', 'Dans un monde de plus en plus'\n"
            . "- 'Dans cet article, nous allons', 'Dans cet article nous explorons'\n"
            . "- 'Plongeons dans', 'Sans plus attendre', 'Vous vous demandez peut-etre'\n"
            . "- Toute enumeration exactement en 3 items quand le sujet en merite 5+\n"
            . "- Phrases passe-partout applicables a n'importe quel pays\n"
            . "- JAMAIS de <div class=\"featured-snippet\"> dans les sections (c'est reserve a l'introduction)\n"
            . "- JAMAIS de <div class=\"summary-box\"> dans les sections (c'est reserve a l'introduction)\n"
            . "- JAMAIS de <div class=\"cta-box\"> ni de <div class=\"disclaimer-box\"> dans les sections (c'est reserve a la conclusion)\n"
            . "- JAMAIS de <div class=\"pricing-box\"> dans les sections (c'est reserve a l'introduction)\n"
            . "- NE JAMAIS repeter une section deja redigee dans un groupe precedent\n"
            . "- JAMAIS inclure de balises <img>, <figure>, <picture>, <source>. Les images\n"
            . "  sont ajoutees par une phase separee — tu NE RETOURNES QUE du texte HTML.\n"
            . "- JAMAIS mentionner d'images, de photos, d'Unsplash, ou de credits photographe.\n"
            . "  NE PAS ecrire 'Photo by X on Unsplash', 'Credit photo :', 'Vue sur [ville]',\n"
            . "  'Image : ...', ni aucune legende ou attribution.\n"
            . "- JAMAIS utiliser la syntaxe markdown (# titre, ## titre, ### titre, **gras**,\n"
            . "  *italique*, [lien](url)). TOUT doit etre en HTML : <h2>, <h3>, <strong>,\n"
            . "  <em>, <a href=\"...\">. Le markdown est INTERDIT dans la sortie.\n\n"
            . "Langue: {$language}. Retourne UNIQUEMENT le HTML des sections demandees.";

        // Add type-specific suffix
        if (!empty($promptSuffix)) {
            $baseSystemPrompt .= "\n\nINSTRUCTIONS SPECIFIQUES AU TYPE DE CONTENU :\n" . $promptSuffix;
        }

        // Intent-specific HTML element enforcement
        $searchIntent = $params['search_intent'] ?? $params['intent'] ?? self::defaultIntent($contentType);
        if ($searchIntent === 'commercial_investigation' || !empty($typeConfig['comparison_table'])) {
            $baseSystemPrompt .= "\n\nOBLIGATOIRE — INTENTION COMPARAISON :\n"
                . "- Un tableau comparatif <table> avec <thead>/<tbody> DOIT apparaitre dans le PREMIER groupe de sections.\n"
                . "- Colonnes = options comparees. Lignes = criteres (prix, couverture, avantages, inconvenients, note).\n"
                . "- Ajoute un encadre verdict pour chaque option : pros et cons.\n";
        }
        if ($searchIntent === 'transactional') {
            $baseSystemPrompt .= "\n\nOBLIGATOIRE — INTENTION TRANSACTIONNELLE :\n"
                . "- Etapes concretes en <ol> (max 5-7 etapes). Chaque etape = 1 phrase d'action.\n"
                . "- Encadre confiance : <blockquote class=\"callout-info\"><p><strong>Bon a savoir</strong></p><p>197 pays, 24h/24, 9 langues, avis verifies.</p></blockquote>\n"
                . "- COURT et DIRECT — zero jargon, chaque phrase mene a l'action.\n";
        }
        if ($searchIntent === 'urgency') {
            $baseSystemPrompt .= "\n\nOBLIGATOIRE — INTENTION URGENCE :\n"
                . "- Etapes numerotees en <ol> — chaque etape = 1 phrase d'action immediate.\n"
                . "- Encadre erreurs a eviter : <blockquote class=\"callout-warning\"><p><strong>Attention</strong></p><p>Les erreurs a ne PAS commettre...</p></blockquote>\n"
                . "- Ton calme, directif. Chaque phrase = une action concrete.\n";
        }
        if ($searchIntent === 'local') {
            $baseSystemPrompt .= "\n\nOBLIGATOIRE — INTENTION LOCALE :\n"
                . "- Tableau <table> avec colonnes : Nom, Adresse, Contact, Langues, Horaires.\n"
                . "- Listes de ressources officielles (ambassade, consulat, associations).\n"
                . "- Encadre liens officiels : <div class=\"official-links\"><p><strong>Sources officielles</strong></p><ul><li><a href=\"...\">...</a></li></ul></div>\n";
        }

        // Extract brand names from topic
        $brandsFromTopic = [];
        if (preg_match_all('/\b(SOS-Expat\.com|Wise|Revolut|Airbnb|Uber|Uber Eats|Booking(?:\.com)?|Skyscanner|Duolingo|N26|PayPal|Stripe|WhatsApp|Google Maps|Google Translate|Nomad List|Notion|Erasmusu|Student Universe|Interactive Brokers|IBKR|Monzo|Starling|Kayak|Expedia|Hostelworld|Couchsurfing|Rome2Rio|Maps\.me)\b/iu', $topicText, $m)) {
            $brandsFromTopic = array_unique($m[0]);
        }

        // Divide sections into groups of 3
        $groups = array_chunk($sections, 3);

        // Distribute LSI keywords across groups
        $lsiChunks = array_chunk($lsiKeywords, max(1, (int) ceil(count($lsiKeywords) / count($groups))));

        $allSectionsHtml = '';
        $previousSummary = "Introduction deja redigee (ne pas repeter) :\n" . mb_substr(strip_tags($introHtml), 0, 300);
        $writtenH2s = []; // Track H2 headings already generated to prevent duplication

        // Determine which group gets brands (the one whose headings match best)
        $brandGroupIndex = 0;
        if (!empty($brandsFromTopic)) {
            $bestScore = 0;
            foreach ($groups as $gi => $group) {
                $score = 0;
                $headingsText = implode(' ', array_column($group, 'heading'));
                foreach ($brandsFromTopic as $brand) {
                    if (stripos($headingsText, $brand) !== false) {
                        $score++;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $brandGroupIndex = $gi;
                }
            }
        }

        foreach ($groups as $groupIndex => $group) {
            // Build the section specs for this group
            $sectionSpecs = '';
            $groupWordBudget = 0;
            foreach ($group as $si => $section) {
                $sNum = ($groupIndex * 3) + $si + 1;
                $heading = $section['heading'] ?? "Section {$sNum}";
                $targetW = $section['target_words'] ?? 350;
                $groupWordBudget += $targetW;
                $keyPoints = !empty($section['key_points']) ? implode(', ', $section['key_points']) : '';
                $subheadings = !empty($section['subheadings']) ? implode(', ', $section['subheadings']) : '';
                $sectionKw = !empty($section['keywords_to_use']) ? implode(', ', $section['keywords_to_use']) : '';

                $sectionSpecs .= "\n--- SECTION {$sNum} ---\n"
                    . "H2 : {$heading}\n"
                    . ($subheadings ? "Sous-sections H3 : {$subheadings}\n" : '')
                    . "Points cles a couvrir : {$keyPoints}\n"
                    . "Mots-cles a integrer : {$sectionKw}\n"
                    . "Objectif : ~{$targetW} mots\n";
            }

            // Build user prompt for this group
            $userPrompt = "ARTICLE : {$title}\n"
                . "SUJET : {$topicText}\n\n"
                . "CONTEXTE PRECEDENT (DEJA REDIGE — NE PAS REPETER ces sujets) :\n{$previousSummary}\n\n"
                . "SECTIONS A REDIGER MAINTENANT :\n{$sectionSpecs}\n";

            // Upcoming sections outline for forward coherence
            $upcomingSections = [];
            for ($ui = $groupIndex + 1; $ui < count($groups); $ui++) {
                foreach ($groups[$ui] as $us) {
                    $upcomingSections[] = $us['heading'] ?? '';
                }
            }
            if (!empty($upcomingSections)) {
                $userPrompt .= "\nSECTIONS QUI SUIVRONT (pour coherence — ne les redige PAS) :\n- " . implode("\n- ", $upcomingSections) . "\n";
            }

            // Facts (distribute: more facts to first group)
            $factsSlice = $groupIndex === 0
                ? array_slice($facts, 0, 10)
                : array_slice($facts, 5 + ($groupIndex * 3), 5);
            if (!empty($factsSlice)) {
                $userPrompt .= "\nFaits de recherche a utiliser :\n- " . implode("\n- ", $factsSlice) . "\n";
            }

            // Source content (first group only)
            if ($groupIndex === 0 && !empty($params['source_content'])) {
                $truncated = mb_substr(strip_tags($params['source_content']), 0, 2000);
                $userPrompt .= "\nCONTENU SOURCE A ENRICHIR (ne pas recopier — utiliser comme base pour creer MIEUX) :\n{$truncated}\n";
            }

            // LSI keywords for this group
            $groupLsi = $lsiChunks[$groupIndex] ?? [];
            if (!empty($groupLsi)) {
                $userPrompt .= "\nMots-cles semantiques (LSI) a integrer naturellement : " . implode(', ', $groupLsi) . "\n";
            }

            // Brands injection
            if (!empty($brandsFromTopic) && $groupIndex === $brandGroupIndex) {
                $brandList = implode(', ', $brandsFromTopic);
                $userPrompt .= "\nMARQUES A CITER OBLIGATOIREMENT par leur NOM EXACT : {$brandList}\n"
                    . "- Chaque marque a sa propre sous-section avec ses fonctionnalites reelles et son prix reel.\n"
                    . "- INTERDIT : 'Application A', 'Service X' — utilise UNIQUEMENT les vrais noms.\n"
                    . "- SOS-Expat.com : service de mise en relation telephonique avec un avocat ou expert local, en moins de 5 minutes, 24h/24, 197 pays.\n"
                    . "- Ne JAMAIS inventer de chiffres sur SOS-Expat.com.\n";
            }

            // Additional instructions
            if (!empty($instructions) && $groupIndex === 0) {
                $userPrompt .= "\nInstructions supplementaires : {$instructions}\n";
            }

            // Geo context for guide types
            if ($country && in_array($contentType, ['guide', 'pillar', 'guide_city'], true) && $groupIndex === 0) {
                $geoContext = $this->geoMeta->buildCountryContextForPrompt($country, $language);
                if (!empty($geoContext)) {
                    $userPrompt = $geoContext . "\n\n" . $userPrompt;
                }
            }

            $userPrompt .= "\nAnnee courante : {$year}. Mentionne cette annee dans les donnees chiffrees.\n"
                . "\nRedige UNIQUEMENT les sections demandees en HTML. Commence directement par le premier <h2>.";

            // Token budget for this group
            $groupMaxTokens = (int) ceil($groupWordBudget * 2.5);
            $groupMaxTokens = max(3000, min(16384, $groupMaxTokens));

            try {
                $result = $this->aiComplete($baseSystemPrompt, $userPrompt, [
                    'model'       => $typeConfig['model'] ?? null,
                    'temperature' => $typeConfig['temperature'] ?? 0.7,
                    'max_tokens'  => $groupMaxTokens,
                ]);

                if ($result['success'] && !empty($result['content'])) {
                    $groupHtml = $this->cleanAiHtml($result['content']);
                    $allSectionsHtml .= ($allSectionsHtml ? "\n\n" : '') . $groupHtml;

                    // Track written H2s and update summary for next group
                    if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $groupHtml, $h2matches)) {
                        $writtenH2s = array_merge($writtenH2s, $h2matches[1]);
                    }
                    $h2List = !empty($writtenH2s) ? "\nH2 DEJA REDIGES (ne PAS les repeter) :\n- " . implode("\n- ", array_map('strip_tags', $writtenH2s)) : '';
                    $previousSummary = mb_substr(strip_tags($allSectionsHtml), -400) . $h2List;
                } else {
                    Log::warning("phase05c: group {$groupIndex} generation failed", [
                        'error' => $result['error'] ?? 'Empty response',
                        'topic' => $topicText,
                    ]);
                    $this->sendTelegramAlert("5c — Sections groupe {$groupIndex}", $result['error'] ?? 'Empty response');
                }
            } catch (\Throwable $e) {
                Log::warning("phase05c: group {$groupIndex} exception", [
                    'error' => $e->getMessage(),
                    'topic' => $topicText,
                ]);
                $this->sendTelegramAlert("5c — Sections groupe {$groupIndex}", $e->getMessage());
            }
        }

        if (empty($allSectionsHtml)) {
            throw new \RuntimeException('Content generation failed: all section groups failed');
        }

        return $allSectionsHtml;
    }

    /**
     * Phase 5d — Conversion Copywriter: generate a compelling conclusion.
     *
     * Returns HTML string (may include a final H2).
     */
    private function phase05d_generateConclusion(string $title, string $introHtml, string $bodyHtml, array $params, array $typeConfig): string
    {
        $language    = $params['language'] ?? 'fr';
        $country     = $params['country'] ?? null;
        $topicText   = $params['topic'] ?? '';
        $year        = date('Y');

        $introSummary = mb_substr(strip_tags($introHtml), 0, 200);
        $bodySummary  = mb_substr(strip_tags($bodyHtml), 0, 1200);

        // Intent-specific CTA and conclusion style
        $searchIntent = $params['search_intent'] ?? $params['intent'] ?? self::defaultIntent($params['content_type'] ?? 'article');
        $ctaInstruction = match ($searchIntent) {
            'informational' => "CTA (1 seul, naturel et soft) :\n"
                . "  Integre un CTA subtil vers SOS-Expat.com en fin de conclusion.\n"
                . "  Ex: 'Pour un accompagnement personnalise, SOS-Expat.com vous met en relation avec un avocat ou expert local en moins de 5 minutes.'\n"
                . "  Utilise le template : <div class=\"cta-box\"><p><strong>Besoin d'aide ?</strong></p><p>Un expert disponible en 5 min, 24h/24, 197 pays.</p><p><a href=\"https://sos-expat.com\" class=\"cta-button\">Consulter un expert</a></p></div>\n",
            'commercial_investigation' => "CTA (1, guide vers l'action) :\n"
                . "  Ex: 'Besoin d'aide pour choisir ? Un expert SOS-Expat vous guide en moins de 5 minutes.'\n"
                . "  Utilise le template : <div class=\"cta-box\"><p><strong>Besoin d'aide pour choisir ?</strong></p><p>Un expert vous guide en 5 min.</p><p><a href=\"https://sos-expat.com\" class=\"cta-button\">Consulter un expert</a></p></div>\n",
            'transactional' => "CTA (2-3, agressifs mais honnetes) :\n"
                . "  L'utilisateur veut AGIR — mets un CTA SOS-Expat au milieu ET en fin de conclusion.\n"
                . "  Template : <div class=\"cta-box\"><p><strong>Pret a agir ?</strong></p><p>Avocat ou expert local en moins de 5 min.</p><p><a href=\"https://sos-expat.com\" class=\"cta-button\">Agir maintenant</a></p></div>\n",
            'urgency' => "CTA URGENT (1, direct et rassurant) :\n"
                . "  L'utilisateur a un probleme MAINTENANT.\n"
                . "  Template : <div class=\"cta-box\"><p><strong>Besoin d'un avocat MAINTENANT ?</strong></p><p>SOS-Expat.com : mise en relation en moins de 5 min, 24h/24.</p><p><a href=\"https://sos-expat.com\" class=\"cta-button\">Appeler un expert</a></p></div>\n",
            default => "Integre un CTA naturel vers SOS-Expat.com via : <div class=\"cta-box\">...</div>\n",
        };

        $systemPrompt = $this->kbPrompt . "\n\n"
            . "Tu es un COPYWRITER DE CONVERSION expert en expatriation.\n"
            . "Tu rediges la conclusion d'un article de reference.\n\n"
            . "OBJECTIF : que le lecteur se sente EQUIPE et CONFIANT pour passer a l'action.\n\n"
            . "REGLES :\n"
            . "- 150-300 mots maximum.\n"
            . "- Commence par un recap des 3-4 insights les PLUS IMPORTANTS (pas tout restater — les points cles seulement).\n"
            . "- Donne 3-5 PROCHAINES ETAPES ACTIONNABLES que le lecteur peut faire immediatement.\n"
            . "- " . $ctaInstruction
            . "- Termine par une phrase TOURNEE VERS L'AVENIR (pas un resume).\n"
            . "- Tu peux utiliser un H2 comme 'Vos prochaines etapes' ou 'Passer a l'action' (PAS 'Conclusion' ou 'En resume').\n\n"
            . "STRICTEMENT INTERDIT :\n"
            . "- 'En conclusion', 'Pour conclure', 'Pour recapituler'\n"
            . "- 'N'hesitez pas a', 'Nous esperons que cet article'\n"
            . "- 'Comme nous l'avons vu dans cet article'\n"
            . "- Toute formule qui sonne comme une dissertation scolaire\n\n"
            . "HTML : <h2> (optionnel, ex: 'Vos prochaines etapes'), <p>, <ol>/<ul> pour les etapes, <strong> pour l'emphase, <div class=\"cta-box\"> pour le CTA.\n"
            . "DISCLAIMER : si l'article traite de droit, fiscalite ou sante, TERMINE par :\n"
            . "<div class=\"disclaimer-box\"><p><strong>Avertissement</strong></p><p>Cet article est fourni a titre informatif uniquement et ne constitue pas un conseil juridique. Consultez un professionnel qualifie pour votre situation specifique.</p></div>\n\n"
            . "Langue: {$language}. Retourne UNIQUEMENT le HTML de la conclusion.";

        $userPrompt = "ARTICLE : {$title}\n"
            . "SUJET : {$topicText}\n"
            . "ANNEE : {$year}\n\n"
            . "INTRODUCTION (resume) :\n{$introSummary}\n\n"
            . "CORPS DE L'ARTICLE (resume) :\n{$bodySummary}\n";

        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model'       => $typeConfig['model'] ?? null,
            'temperature' => $typeConfig['temperature'] ?? 0.7,
            'max_tokens'  => 1500,
        ]);

        if ($result['success'] && !empty($result['content'])) {
            $conclusion = $this->cleanAiHtml($result['content']);
            $wordCount = str_word_count(strip_tags($conclusion));

            // If conclusion is too short (<80 words), retry with stronger instruction
            if ($wordCount < 80) {
                Log::info("phase05d: conclusion too short ({$wordCount} words), retrying...");
                $retryResult = $this->aiComplete(
                    $systemPrompt,
                    $userPrompt . "\n\nATTENTION : la conclusion DOIT faire entre 150 et 300 mots. "
                        . "Elle DOIT contenir : 1) un recap de 3-4 points cles, 2) une liste de prochaines etapes en <ol>, "
                        . "3) un <div class=\"cta-box\"> avec lien vers SOS-Expat, 4) un <div class=\"disclaimer-box\"> si juridique.",
                    [
                        'model'       => $typeConfig['model'] ?? null,
                        'temperature' => ($typeConfig['temperature'] ?? 0.7) + 0.1,
                        'max_tokens'  => 1500,
                    ]
                );
                if ($retryResult['success'] && !empty($retryResult['content'])) {
                    $retry = $this->cleanAiHtml($retryResult['content']);
                    if (str_word_count(strip_tags($retry)) > $wordCount) {
                        return $retry;
                    }
                }
            }

            return $conclusion;
        }

        // Fallback: rich conclusion with CTA + disclaimer (used when both GPT and Claude fail)
        Log::warning('phase05d: conclusion generation failed, using fallback', ['topic' => $topicText]);
        $langLabel = match ($language) { 'en' => 'Next steps', 'es' => 'Proximos pasos', 'de' => 'Nachste Schritte', default => 'Vos prochaines etapes' };
        $fallback = "<h2>{$langLabel}</h2>\n"
            . "<p>Face a cette situation, voici les etapes essentielles a suivre :</p>\n"
            . "<ol>\n"
            . "<li><strong>Rassemblez tous vos documents</strong> pertinents (contrats, preuves, correspondances).</li>\n"
            . "<li><strong>Contactez votre ambassade</strong> ou consulat pour connaitre vos droits locaux.</li>\n"
            . "<li><strong>Consultez un professionnel local</strong> pour un avis adapte a votre situation.</li>\n"
            . "</ol>\n"
            . "<div class=\"cta-box\">\n"
            . "<p><strong>Besoin d'aide ?</strong></p>\n"
            . "<p>Un avocat ou expert local disponible en moins de 5 minutes, 24h/24, dans 197 pays.</p>\n"
            . "<p><a href=\"https://sos-expat.com\" class=\"cta-button\">Consulter un expert</a></p>\n"
            . "</div>\n"
            . "<div class=\"disclaimer-box\">\n"
            . "<p><strong>Avertissement</strong></p>\n"
            . "<p>Cet article est fourni a titre informatif uniquement et ne constitue pas un conseil juridique. Consultez un professionnel qualifie pour votre situation specifique.</p>\n"
            . "</div>";
        return $fallback;
    }

    /**
     * Phase 5e — Editor-in-Chief: polish transitions, eliminate AI patterns, ensure consistency.
     *
     * Returns the final polished HTML. Skipped for low-value content types.
     */
    private function phase05e_polishAndUnify(string $fullHtml, string $title, array $params, array $typeConfig): string
    {
        // Skip polish for fast/low-value content types
        if (!empty($typeConfig['skip_polish'])) {
            return $fullHtml;
        }

        $language  = $params['language'] ?? 'fr';
        $toneGuide = $params['_outline_tone'] ?? 'Ami expert, chaleureux mais precis';
        $year      = date('Y');

        $prePolishWordCount = str_word_count(strip_tags($fullHtml));

        $systemPrompt = $this->kbPrompt . "\n\n"
            . "Tu es le REDACTEUR EN CHEF d'un magazine international d'expatriation premium.\n"
            . "On te confie un article assemble a partir de plusieurs sections redigees separement.\n\n"
            . "TA MISSION : POLIR l'article pour qu'il lise comme s'il avait ete ecrit D'UNE SEULE TRAITE par un journaliste humain.\n"
            . "Tu ne REECRIS PAS. Tu POLIS.\n\n"
            . "CE QUE TU DOIS FAIRE :\n"
            . "1. TRANSITIONS : ajoute 1 phrase-pont entre les sections qui semblent deconnectees.\n"
            . "   Exemples : 'Mais avant de signer votre bail, un point crucial merite votre attention.'\n"
            . "              'Cette complexite administrative a un corollaire direct sur votre quotidien.'\n"
            . "2. ELIMINER LES PATTERNS IA : detecte et reecris CHAQUE occurrence de ces formules :\n"
            . "   - 'Il est important de noter que' → reformule avec le fait directement\n"
            . "   - 'Il convient de souligner' → supprime et garde le contenu\n"
            . "   - 'Dans le paysage actuel' → remplace par un fait precis\n"
            . "   - 'En ce qui concerne' → reformule directement\n"
            . "   - 'Force est de constater' → supprime\n"
            . "   - 'Il est essentiel de' → reformule avec une action concrete\n"
            . "   - 'N'hesitez pas a' → remplace par un imperatif direct\n"
            . "   - 'Au fil des annees' → donne une date precise\n"
            . "   - 'Dans un monde de plus en plus' → supprime, commence par le fait\n"
            . "   - 'Il va sans dire' → supprime completement\n"
            . "   - 'Tout d'abord... Ensuite... Enfin...' → varie les connecteurs\n"
            . "   - 'Plongeons dans' / 'Sans plus attendre' → supprime\n"
            . "   - 'Vous vous demandez peut-etre' → pose la question directement\n"
            . "   - 'Cela dit' / 'Ceci etant dit' → reformule\n"
            . "   - 'A noter que' / 'Notons que' → integre le fait dans la phrase\n"
            . "   - 'En effet' en debut de phrase → supprime ou reformule\n"
            . "   - 'Ainsi' en debut de phrase (trop frequent) → varier\n"
            . "   - Tout paragraphe qui commence par 'Il est' suivi d'un adjectif\n"
            . "   - Toute enumeration exactement en 3 points quand le sujet en merite 5+\n"
            . "3. COHERENCE DE TON : verifie que le ton est uniforme du debut a la fin ({$toneGuide}).\n"
            . "4. RYTHME : varie la longueur des phrases (alternance 8-10 mots / 20-25 mots).\n"
            . "5. HTML : corrige les balises mal fermees ou la structure cassee.\n\n"
            . "6. DOUBLONS : si tu trouves des sections H2 au contenu similaire/redondant, FUSIONNE-les (garde la meilleure version).\n"
            . "7. COMPOSANTS : il ne doit y avoir qu'UN SEUL <div class=\"featured-snippet\"> (dans l'intro) et UN SEUL <div class=\"summary-box\"> (dans l'intro). "
            . "Si tu en trouves d'autres dans le corps ou la conclusion, SUPPRIME les doublons.\n"
            . "8. PATTERNS IA SUPPLEMENTAIRES a eliminer :\n"
            . "   - 'Dans cet article, nous allons' → supprime toute la phrase\n"
            . "   - 'Il est important de comprendre que' → reformule avec le fait directement\n"
            . "   - 'il est essentiel de' → reformule en imperatif\n\n"
            . "CE QUE TU NE DOIS ABSOLUMENT PAS FAIRE :\n"
            . "- NE PAS ajouter de nouvelles sections H2\n"
            . "- NE PAS changer significativement le contenu factuel\n"
            . "- NE PAS ajouter d'introduction ou de conclusion (elles sont deja la)\n"
            . "- Tu PEUX fusionner des sections redondantes (c'est meme encourage)\n"
            . "- Garder le MEME NOMBRE DE MOTS (+/- 10%). L'article fait ~{$prePolishWordCount} mots.\n\n"
            . "Langue: {$language}. Retourne UNIQUEMENT le HTML complet de l'article poli.";

        $userPrompt = "TITRE : {$title}\nANNEE : {$year}\n\nARTICLE A POLIR :\n\n{$fullHtml}";

        // Token budget: accommodate the full article
        $maxTokens = (int) ceil(mb_strlen($fullHtml) / 3.5);
        $maxTokens = max(4000, min(16384, $maxTokens));

        // Polish needs lower temperature than generation for precision (cap at 0.5)
        $polishTemp = min(0.5, ($typeConfig['temperature'] ?? 0.6) - 0.2);
        $result = $this->aiComplete($systemPrompt, $userPrompt, [
            'model'       => $typeConfig['model'] ?? null,
            'temperature' => max(0.2, $polishTemp),
            'max_tokens'  => $maxTokens,
        ]);

        if ($result['success'] && !empty($result['content'])) {
            $polished = $this->cleanAiHtml($result['content']);
            $polishedWordCount = str_word_count(strip_tags($polished));

            // Safety: if polish lost more than 25% of content, keep pre-polish version
            // (allow up to 25% reduction because polish may remove duplicate sections)
            if ($polishedWordCount < $prePolishWordCount * 0.75) {
                Log::warning('phase05e: polish lost too many words, keeping pre-polish version', [
                    'pre_polish' => $prePolishWordCount,
                    'post_polish' => $polishedWordCount,
                ]);
                return $fullHtml;
            }

            return $polished;
        }

        // Fallback: keep pre-polish HTML (already decent from specialized prompts)
        Log::warning('phase05e: polish failed, keeping pre-polish version', [
            'error' => $result['error'] ?? 'Unknown',
        ]);
        return $fullHtml;
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

            // Deduplicate: only send the same (phase + error signature) once per 24h.
            // Avoids spamming when a persistent cause (e.g. API credits exhausted, network down)
            // breaks every article in the queue and generates hundreds of identical alerts.
            $errorSignature = md5($phase . '|' . mb_substr($error, 0, 120));
            $cacheKey = "telegram_alert_dedup:{$errorSignature}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return;
            }
            \Illuminate\Support\Facades\Cache::put($cacheKey, 1, now()->addDay());

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

            $article->refresh();
            $editorial = $article->editorial_score;
            $editorialReport = $article->editorial_review ?? [];
            $editorialLine = '';
            if ($editorial !== null) {
                $emoji = $editorial >= 85 ? '🟢' : ($editorial >= 70 ? '🟡' : '🔴');
                $editorialLine = "Éditorial: {$emoji} {$editorial}/100";
                if (!empty($editorialReport) && is_array($editorialReport)) {
                    $t = $editorialReport['title_score'] ?? '?';
                    $m = $editorialReport['meta_score'] ?? '?';
                    $c = $editorialReport['content_score'] ?? '?';
                    $f = $editorialReport['fact_score'] ?? '?';
                    $i = $editorialReport['intent_score'] ?? '?';
                    $editorialLine .= " (T:{$t} M:{$m} C:{$c} F:{$f} I:{$i})";
                }
                $editorialLine .= "\n";

                // Flag concrete issues found by the judge
                if (!empty($editorialReport['issues']) && is_array($editorialReport['issues'])) {
                    $topIssues = array_slice($editorialReport['issues'], 0, 3);
                    $editorialLine .= "Issues: " . implode(' · ', $topIssues) . "\n";
                }
            }

            $text = "✅ *Nouvel article généré*\n\n"
                . "Titre: {$article->title}\n"
                . "Type: {$article->content_type} | Langue: {$article->language} | Pays: {$article->country}\n"
                . "Mots: {$article->word_count} | SEO: {$article->seo_score}/100 | Qualité: {$article->quality_score}/100\n"
                . $editorialLine
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
     * Route a completion request — GPT primary, Claude as automatic fallback.
     *
     * Switched 2026-04-11: previously dispatched directly based on the model
     * name prefix, which created a hard dependency on whichever vendor was
     * requested. Now we ALWAYS try GPT first (mapping claude-* requests to
     * the closest GPT equivalent), and only fall back to the originally
     * requested Claude model if GPT fails. Isolates the entire generation
     * pipeline from any single-vendor outage.
     */
    /**
     * Route a completion request — GPT primary with retry, Claude only if GPT has no credits.
     *
     * Flow: GPT → (if rate-limited: wait 3s + retry GPT) → (if billing error: try Claude) → fail
     * Claude fallback is ONLY used when GPT returns a billing/payment error (402, insufficient_quota).
     * Rate limits (429) and transient errors are retried on GPT — no point hitting Claude for those.
     */
    private function aiComplete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $requestedModel    = $options['model'] ?? 'gpt-4o';
        $isClaudeRequested = is_string($requestedModel) && str_starts_with($requestedModel, 'claude-');

        // Map any model request to a GPT primary
        $gptModel = match (true) {
            !is_string($requestedModel)                  => 'gpt-4o',
            str_starts_with($requestedModel, 'gpt-')     => $requestedModel,
            str_contains($requestedModel, 'haiku')       => 'gpt-4o-mini',
            str_contains($requestedModel, 'opus'),
            str_contains($requestedModel, 'sonnet')      => 'gpt-4o',
            default                                      => 'gpt-4o',
        };

        // Attempt 1: GPT
        $gptOptions = $options;
        $gptOptions['model'] = $gptModel;
        $result = $this->openAi->complete($systemPrompt, $userPrompt, $gptOptions);

        if (!empty($result['success']) && !empty($result['content'])) {
            return $result;
        }

        $gptError = $result['error'] ?? 'unknown';

        // Check if it's a rate limit (429) or transient error — retry GPT once after 3s
        $isRateLimit = str_contains($gptError, '429')
            || str_contains(strtolower($gptError), 'rate')
            || str_contains(strtolower($gptError), 'too many');
        $isTransient = str_contains($gptError, '500')
            || str_contains($gptError, '502')
            || str_contains($gptError, '503')
            || str_contains(strtolower($gptError), 'timeout');

        if ($isRateLimit || $isTransient) {
            // Parse "Please try again in 5.354s" from OpenAI's error message
            // to wait exactly the amount they're asking for, + jitter.
            $waitSeconds = 3.0;
            if (preg_match('/try again in ([\d.]+)s/i', $gptError, $m)) {
                $waitSeconds = min(30.0, (float) $m[1] + 1.0); // cap at 30s
            }
            Log::info('aiComplete: GPT rate-limited/transient, retrying', [
                'model' => $gptModel,
                'wait'  => $waitSeconds,
                'error' => mb_substr($gptError, 0, 100),
            ]);
            usleep((int) ($waitSeconds * 1_000_000));

            $result = $this->openAi->complete($systemPrompt, $userPrompt, $gptOptions);
            if (!empty($result['success']) && !empty($result['content'])) {
                return $result;
            }
            $gptError = $result['error'] ?? 'unknown';

            // Second retry with exponential backoff
            if (str_contains($gptError, '429') || str_contains(strtolower($gptError), 'rate')) {
                $waitSeconds2 = $waitSeconds * 2;
                Log::info('aiComplete: still rate-limited, second retry with longer wait', [
                    'wait' => $waitSeconds2,
                ]);
                usleep((int) ($waitSeconds2 * 1_000_000));
                $result = $this->openAi->complete($systemPrompt, $userPrompt, $gptOptions);
                if (!empty($result['success']) && !empty($result['content'])) {
                    return $result;
                }
                $gptError = $result['error'] ?? 'unknown';
            }

            // Third attempt: downgrade to gpt-4o-mini which has a 10x higher
            // TPM quota than gpt-4o. Only done for non-critical section
            // writing — other phases (title, meta) already use gpt-4o-mini.
            if (($isRateLimit || str_contains($gptError, '429'))
                && $gptModel === 'gpt-4o') {
                Log::warning('aiComplete: sustained rate limit on gpt-4o, falling back to gpt-4o-mini', [
                    'original_model' => $gptModel,
                ]);
                $gptOptions['model'] = 'gpt-4o-mini';
                $result = $this->openAi->complete($systemPrompt, $userPrompt, $gptOptions);
                if (!empty($result['success']) && !empty($result['content'])) {
                    return $result;
                }
                $gptError = $result['error'] ?? 'unknown';
            }
        }

        // Check if GPT has a billing/quota problem (insufficient_quota / 402).
        // CRITICAL POLICY (2026-05-02): we DO NOT fall back to Claude on GPT billing
        // failure. Claude Sonnet costs ~20× more than GPT-4o for the same prompt
        // (see incident 2026-05-01 17h-22h UTC: $21 of Claude credits drained in
        // 4h after GPT key hit insufficient_quota — 13 articles × 9 langs × 11 AI
        // calls/article all silently routed to Claude Sonnet at ~$0.10/call).
        //
        // Behaviour now:
        //   - GPT billing error → return the failure immediately (job marked failed).
        //   - The orchestrator retries the article on the next cycle (GPT may be
        //     unblocked by then), and the operator gets an alert via Telegram.
        //   - Claude is reserved for non-billing transient cases handled below.
        $isGptBillingError = str_contains(strtolower($gptError), 'billing')
            || str_contains(strtolower($gptError), 'quota')
            || str_contains(strtolower($gptError), 'insufficient')
            || str_contains($gptError, '402');

        if ($isGptBillingError) {
            Log::error('aiComplete: GPT billing error — STOP, no Claude fallback (cost protection)', [
                'gpt_model' => $gptModel,
                'error'     => mb_substr($gptError, 0, 200),
                'action'    => 'job will fail; recharge OpenAI to resume',
            ]);
            // Optional: ping Telegram so the operator notices fast.
            try {
                $tgUrl = config('services.telegram.engine_url');
                $tgKey = config('services.telegram.engine_key');
                if ($tgUrl && $tgKey) {
                    \Illuminate\Support\Facades\Http::withToken($tgKey)->timeout(5)
                        ->post(rtrim($tgUrl, '/') . '/api/events/security-alert', [
                            'level'   => 'critical',
                            'title'   => 'OpenAI quota épuisé',
                            'message' => "Génération arrêtée. Erreur: " . mb_substr($gptError, 0, 200),
                        ]);
                }
            } catch (\Throwable $e) {
                // best-effort, don't fail the whole call on telegram
            }
            return $result; // billing failure surfaces to caller
        }

        // GPT failed for a non-billing reason (e.g. content moderation, schema validation)
        // and retries didn't help. Try Claude Haiku as a last resort — it's 10× cheaper
        // than Sonnet and good enough for the small post-failure rescue cases this
        // path covers (typically <1% of calls).
        $claudeFallback = $isClaudeRequested ? $requestedModel : 'claude-haiku-4-5-20251001';
        Log::warning('aiComplete: GPT non-billing failure, trying Claude Haiku fallback', [
            'gpt_model'       => $gptModel,
            'claude_fallback' => $claudeFallback,
            'gpt_error'       => mb_substr($gptError, 0, 100),
        ]);

        $claudeOptions = $options;
        $claudeOptions['model'] = $claudeFallback;
        return $this->claude->complete($systemPrompt, $userPrompt, $claudeOptions);
    }
}
