<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Models\QaEntry;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\Content\ContentTypeConfig;
use App\Services\PerplexitySearchService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Q&A generation — creates standalone Q&A pages from article FAQs or PAA questions.
 * Each Q&A includes a short answer (featured snippet), a detailed HTML answer,
 * meta tags, JSON-LD, and related Q&A links.
 */
class QaGenerationService
{
    public function __construct(
        private OpenAiService $openAi,
        private PerplexitySearchService $perplexity,
        private SlugService $slugService,
        private SeoAnalysisService $seoAnalysis,
        private UnsplashService $unsplash,
    ) {}

    /**
     * Generate Q&A entries from an article's FAQs.
     */
    public function generateFromArticleFaqs(GeneratedArticle $article, array $faqIds = []): Collection
    {
        try {
            $faqs = $article->faqs();
            if (!empty($faqIds)) {
                $faqs = $faqs->whereIn('id', $faqIds);
            }
            $faqs = $faqs->get();

            if ($faqs->isEmpty()) {
                Log::info('QaGeneration: no FAQs found for article', ['article_id' => $article->id]);
                return collect();
            }

            Log::info('QaGeneration: generating from article FAQs', [
                'article_id' => $article->id,
                'faq_count' => $faqs->count(),
            ]);

            $createdEntries = collect();
            $articleContext = mb_substr(strip_tags($article->content_html ?? ''), 0, 2000);
            $dedup = app(DeduplicationService::class);

            foreach ($faqs as $faq) {
                $existingQa = $dedup->findDuplicateQa($faq->question, $article->language);
                if ($existingQa) {
                    Log::info('QaGenerationService: duplicate Q&A skipped', ['question' => $faq->question, 'existing_id' => $existingQa->id]);
                    continue; // Skip this FAQ, already exists as Q&A
                }

                $entry = $this->generateSingleQa(
                    question: $faq->question,
                    country: $article->country ?? '',
                    category: $article->content_type ?? 'article',
                    language: $article->language ?? 'fr',
                    sourceType: 'faq',
                    parentArticleId: $article->id,
                    clusterId: null,
                    articleContext: $articleContext,
                );

                if ($entry) {
                    $createdEntries->push($entry);
                }
            }

            Log::info('QaGeneration: FAQ generation complete', [
                'article_id' => $article->id,
                'entries_created' => $createdEntries->count(),
            ]);

            return $createdEntries;
        } catch (\Throwable $e) {
            Log::error('QaGeneration: FAQ generation failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate Q&A entries from PAA (People Also Ask) questions.
     */
    public function generateFromPaa(string $topic, string $country, string $language = 'fr'): Collection
    {
        try {
            Log::info('QaGeneration: generating from PAA', [
                'topic' => $topic,
                'country' => $country,
                'language' => $language,
            ]);

            // Use Perplexity to find PAA questions
            $paaQuestions = [];

            if ($this->perplexity->isConfigured()) {
                $query = "What are the People Also Ask (PAA) questions on Google for \"{$topic}\" "
                    . (!empty($country) ? "in {$country} " : '')
                    . "? List all related questions people search for. Language: {$language}.";

                $result = $this->perplexity->search($query, $language);

                if ($result['success'] && !empty($result['text'])) {
                    // Parse questions from response
                    $parseResult = $this->openAi->complete(
                        "Extract all questions from this text. Return JSON: {questions: [string]}",
                        $result['text'],
                        [
                            'model' => 'gpt-4o-mini',
                            'temperature' => 0.2,
                            'max_tokens' => 1000,
                            'json_mode' => true,
                        ]
                    );

                    if ($parseResult['success']) {
                        $parsed = json_decode($parseResult['content'], true);
                        $paaQuestions = $parsed['questions'] ?? [];
                    }
                }
            }

            if (empty($paaQuestions)) {
                Log::info('QaGeneration: no PAA questions found', ['topic' => $topic]);
                return collect();
            }

            $createdEntries = collect();

            foreach (array_slice($paaQuestions, 0, 15) as $question) {
                $entry = $this->generateSingleQa(
                    question: $question,
                    country: $country,
                    category: 'general',
                    language: $language,
                    sourceType: 'paa',
                    parentArticleId: null,
                    clusterId: null,
                    articleContext: null,
                );

                if ($entry) {
                    $createdEntries->push($entry);
                }
            }

            Log::info('QaGeneration: PAA generation complete', [
                'topic' => $topic,
                'entries_created' => $createdEntries->count(),
            ]);

            return $createdEntries;
        } catch (\Throwable $e) {
            Log::error('QaGeneration: PAA generation failed', [
                'topic' => $topic,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a single Q&A entry with detailed answer, meta, and JSON-LD.
     */
    private function generateSingleQa(
        string $question,
        string $country,
        string $category,
        string $language,
        string $sourceType,
        ?int $parentArticleId,
        ?int $clusterId,
        ?string $articleContext,
    ): ?QaEntry {
        try {
            // Generate detailed answer
            $answerData = $this->generateDetailedAnswer($question, $country, $category, $articleContext);

            if (empty($answerData['answer_short']) && empty($answerData['answer_detailed_html'])) {
                return null;
            }

            // Generate meta tags
            $year = date('Y');
            $metaResult = $this->openAi->complete(
                "Generate SEO meta tags for a Q&A page. Language: {$language}. "
                . "Return JSON: {meta_title: string (max 60 chars), meta_description: string (140-160 chars)}\n\n"
                . "Meta title : max 60 caractères, DOIT contenir le sujet principal + année {$year}. Format: \"{Sujet} {$year} : Réponse d'Expert | SOS-Expat\"",
                "Question: {$question}\nAnswer summary: {$answerData['answer_short']}",
                [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'max_tokens' => 300,
                    'json_mode' => true,
                ]
            );

            $metaTitle = $question;
            $metaDescription = $answerData['answer_short'] ?? '';

            if ($metaResult['success']) {
                $parsedMeta = json_decode($metaResult['content'], true);
                $metaTitle = $parsedMeta['meta_title'] ?? $question;
                $metaDescription = $parsedMeta['meta_description'] ?? $answerData['answer_short'];
            }

            // Generate slug + canonical URL
            $slug = $this->slugService->generateSlug($question, $language);
            $slug = $this->slugService->ensureUnique($slug, $language, 'qa_entries');
            $canonical = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/') . '/' . $language . '/qa/' . $slug;

            // Find related Q&A (exclude current entry after creation)
            $relatedIds = QaEntry::where('country', $country)
                ->where('category', $category)
                ->where('language', $language)
                ->where('status', '!=', 'draft')
                ->limit(5)
                ->pluck('id')
                ->toArray();

            // Generate JSON-LD
            $jsonLd = $this->generateJsonLd($question, $answerData, $country, $language, $slug, $relatedIds);

            // Create entry
            $entry = QaEntry::create([
                'uuid' => (string) Str::uuid(),
                'parent_article_id' => $parentArticleId,
                'cluster_id' => $clusterId,
                'question' => $question,
                'answer_short' => mb_substr($answerData['answer_short'] ?? '', 0, 500),
                'answer_detailed_html' => $answerData['answer_detailed_html'] ?? '',
                'language' => $language,
                'country' => $country,
                'category' => $category,
                'slug' => $slug,
                'canonical_url' => $canonical,
                'meta_title' => mb_substr($metaTitle, 0, 60),
                'meta_description' => mb_substr($metaDescription, 0, 160),
                'json_ld' => $jsonLd,
                'keywords_primary' => mb_substr($question, 0, 100),
                'seo_score' => 0,
                'word_count' => $answerData['word_count'] ?? 0,
                'source_type' => $sourceType,
                'status' => 'draft',
                'related_qa_ids' => $relatedIds,
                'sources' => $answerData['sources'] ?? [],
            ]);

            // Run SEO analysis to populate seo_score
            try {
                $this->seoAnalysis->analyze($entry);
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: SEO analysis failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Simple plagiarism check against existing Q&A
            try {
                $answerText = strip_tags($entry->answer_detailed_html ?? '');
                if (mb_strlen($answerText) > 100) {
                    $existingQas = QaEntry::where('language', $entry->language)
                        ->where('id', '!=', $entry->id)
                        ->whereIn('status', ['draft', 'review', 'published'])
                        ->select('id', 'question', 'answer_detailed_html')
                        ->limit(100)
                        ->get();

                    foreach ($existingQas as $existing) {
                        $existingText = strip_tags($existing->answer_detailed_html ?? '');
                        similar_text($answerText, $existingText, $percent);
                        if ($percent > 40) {
                            Log::warning('QaGenerationService: similar Q&A detected', [
                                'new_qa' => $entry->id,
                                'existing_qa' => $existing->id,
                                'similarity' => $percent,
                            ]);
                            $entry->update(['status' => 'review']);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: plagiarism check failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Add image (Unsplash)
            try {
                if ($this->unsplash->isConfigured()) {
                    $imgResult = $this->unsplash->search($question, 1);
                    if ($imgResult['success'] && !empty($imgResult['images'])) {
                        $img = $imgResult['images'][0];
                        $imgAlt = mb_substr(($entry->keywords_primary ?? '') . ' - ' . $entry->question, 0, 125);
                        $imgTag = '<figure><img src="' . e($img['url']) . '" alt="' . e($imgAlt) . '" loading="lazy" />';
                        if (!empty($img['attribution'])) {
                            $imgTag .= '<figcaption>' . e($img['attribution']) . '</figcaption>';
                        }
                        $imgTag .= '</figure>';
                        $html = $entry->answer_detailed_html ?? '';
                        $pos = strpos($html, '</h2>');
                        if ($pos !== false) {
                            $html = substr($html, 0, $pos + 5) . "\n" . $imgTag . "\n" . substr($html, $pos + 5);
                        } else {
                            $html = $imgTag . "\n" . $html;
                        }
                        $entry->update(['answer_detailed_html' => $html]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: image addition failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Add internal links (related articles)
            try {
                $relatedArticles = GeneratedArticle::where('language', $entry->language)
                    ->where('country', $entry->country)
                    ->where('status', 'published')
                    ->whereNull('parent_article_id')
                    ->limit(3)
                    ->get();
                if ($relatedArticles->isNotEmpty()) {
                    $linksHtml = "\n<h2>Articles connexes</h2>\n<ul>\n";
                    foreach ($relatedArticles as $ra) {
                        $linksHtml .= '<li><a href="/' . $ra->language . '/articles/' . $ra->slug . '">' . e($ra->title) . '</a></li>' . "\n";
                    }
                    $linksHtml .= "</ul>\n";
                    $entry->update(['answer_detailed_html' => ($entry->answer_detailed_html ?? '') . $linksHtml]);
                }
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: internal links failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // External links from research sources
            try {
                $entrySources = $answerData['sources'] ?? [];
                if (!empty($entrySources)) {
                    $sourcesHtml = "\n<h2>Sources officielles</h2>\n<ul>\n";
                    foreach (array_slice($entrySources, 0, 3) as $sourceUrl) {
                        $domain = parse_url($sourceUrl, PHP_URL_HOST) ?: $sourceUrl;
                        $sourcesHtml .= '<li><a href="' . e($sourceUrl) . '" target="_blank" rel="noopener">' . e($domain) . '</a></li>' . "\n";
                    }
                    $sourcesHtml .= "</ul>\n";
                    $entry->update(['answer_detailed_html' => ($entry->answer_detailed_html ?? '') . $sourcesHtml]);
                }
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: external links failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Affiliate CTA link
            try {
                $siteUrl = config('services.site.url', 'https://sos-expat.com');
                $cta = '<p class="cta-box"><strong>Besoin d\'une réponse personnalisée ?</strong> <a href="' . $siteUrl . '?utm_source=blog&utm_medium=qa&utm_campaign=' . ($entry->slug ?? '') . '">Consultez nos experts SOS-Expat</a></p>';
                $entry->update(['answer_detailed_html' => ($entry->answer_detailed_html ?? '') . "\n" . $cta]);
            } catch (\Throwable $e) {
                Log::warning('QaGeneration: affiliate CTA failed (non-blocking)', [
                    'qa_entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $entry;
        } catch (\Throwable $e) {
            Log::error('QaGeneration: single Q&A generation failed', [
                'question' => $question,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate a detailed answer for a question using GPT-4o.
     *
     * @return array{answer_short: string, answer_detailed_html: string, word_count: int}
     */
    private function generateDetailedAnswer(
        string $question,
        string $country,
        string $category,
        ?string $articleContext = null,
    ): array {
        $countryContext = !empty($country) ? " pour les expatriés en {$country}" : '';
        $contextBlock = !empty($articleContext)
            ? "\n\nContexte de l'article parent:\n" . mb_substr($articleContext, 0, 1500)
            : '';

        $systemPrompt = "Tu es un expert en expatriation. Réponds à cette question{$countryContext}.\n\n"
            . "INSTRUCTIONS:\n"
            . "1. answer_short: réponse directe de 40-60 mots (format featured snippet Google). La réponse DOIT commencer par une reformulation du sujet (ex: Q: 'Quel est le coût de la vie en France ?' R: 'Le coût de la vie en France est en moyenne de...')\n"
            . "2. answer_detailed_html: réponse détaillée de 800-2000 mots en HTML\n"
            . "   - Utilise <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>\n"
            . "   - Inclus des sources officielles et des données chiffrées\n"
            . "   - Donne des conseils pratiques et concrets\n"
            . "   - Pas de <h1>, <html>, <head>, <body>\n\n"
            . "Retourne en JSON: {answer_short: string, answer_detailed_html: string}";

        $typeConfig = ContentTypeConfig::get('qa');
        $result = $this->openAi->complete(
            $systemPrompt,
            "Question: {$question}{$contextBlock}",
            [
                'model' => $typeConfig['model'],
                'temperature' => $typeConfig['temperature'],
                'max_tokens' => $typeConfig['max_tokens_content'] ?? 4000,
                'json_mode' => true,
            ]
        );

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);

            $answerShort = $parsed['answer_short'] ?? '';
            $answerHtml = $parsed['answer_detailed_html'] ?? '';
            $wordCount = str_word_count(strip_tags($answerHtml));

            // Validate answer_short word count (target: 40-60 words)
            $answerShortWordCount = str_word_count($answerShort);

            if ($answerShortWordCount > 70) {
                // Truncate to ~60 words
                $words = explode(' ', $answerShort);
                $answerShort = implode(' ', array_slice($words, 0, 60));
                // Find last sentence boundary
                $lastDot = strrpos($answerShort, '.');
                if ($lastDot !== false && $lastDot > strlen($answerShort) * 0.6) {
                    $answerShort = substr($answerShort, 0, $lastDot + 1);
                }
            } elseif ($answerShortWordCount < 30) {
                // Too short — regenerate with explicit instruction
                $retryResult = $this->openAi->complete(
                    "Génère une réponse de EXACTEMENT 40-60 mots. La réponse DOIT commencer par une reformulation du sujet. "
                    . "Exemple: Q: \"Quel est le coût de la vie en France ?\" R: \"Le coût de la vie en France est en moyenne de 1 500€ par mois pour...\"",
                    "Question: {$question}\nPays: {$country}\nRéponse actuelle trop courte: {$answerShort}\n\nRégénère en 40-60 mots:",
                    ['temperature' => 0.5, 'max_tokens' => 150]
                );
                if ($retryResult['success']) {
                    $answerShort = trim($retryResult['content']);
                }
            }

            return [
                'answer_short' => $answerShort,
                'answer_detailed_html' => $answerHtml,
                'word_count' => $wordCount,
            ];
        }

        return ['answer_short' => '', 'answer_detailed_html' => '', 'word_count' => 0];
    }

    /**
     * Generate JSON-LD (QAPage + BreadcrumbList + Speakable) for a Q&A entry.
     */
    private function generateJsonLd(
        string $question,
        array $answerData,
        string $country,
        string $language,
        string $slug,
        array $relatedQaIds = [],
        array $sources = [],
    ): array {
        $countrySlug = Str::slug($country ?: 'general');
        $url = "/{$language}/qa/{$countrySlug}/{$slug}";

        $schema = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'QAPage',
                'mainEntity' => [
                    '@type' => 'Question',
                    'name' => $question,
                    'text' => $question,
                    'answerCount' => 1,
                    'author' => [
                        '@type' => 'Organization',
                        'name' => config('services.site.name', 'SOS-Expat'),
                    ],
                    'dateCreated' => now()->toIso8601String(),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($answerData['answer_detailed_html'] ?? $answerData['answer_short'] ?? ''),
                        'dateCreated' => now()->toIso8601String(),
                        'dateModified' => now()->toIso8601String(),
                        'author' => [
                            '@type' => 'Organization',
                            'name' => config('services.site.name', 'SOS-Expat'),
                            'url' => config('services.site.url', 'https://sos-expat.com'),
                        ],
                        'upvoteCount' => 1,
                    ],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => "/{$language}",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Q&A',
                        'item' => "/{$language}/qa",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $country ?: 'General',
                        'item' => "/{$language}/qa/{$countrySlug}",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 4,
                        'name' => mb_substr($question, 0, 50),
                        'item' => $url,
                    ],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'speakable' => [
                    '@type' => 'SpeakableSpecification',
                    'cssSelector' => ['.qa-answer-short', '.qa-question'],
                ],
            ],
        ];

        // If related Q&A exist, add FAQPage schema
        if (!empty($relatedQaIds)) {
            $relatedQas = \App\Models\QaEntry::whereIn('id', $relatedQaIds)
                ->select('question', 'answer_short')
                ->get();

            if ($relatedQas->isNotEmpty()) {
                $schema[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $relatedQas->map(fn ($qa) => [
                        '@type' => 'Question',
                        'name' => $qa->question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $qa->answer_short,
                        ],
                    ])->values()->toArray(),
                ];
            }
        }

        // Add isBasedOn for sources (inside the QAPage schema object, not at root level)
        if (!empty($sources) && isset($schema[0])) {
            $schema[0]['isBasedOn'] = array_map(fn ($url) => ['@type' => 'WebPage', 'url' => $url], array_slice($sources, 0, 5));
        }

        return $schema;
    }
}
