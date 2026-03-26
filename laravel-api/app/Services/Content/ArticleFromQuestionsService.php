<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Models\QuestionCluster;
use App\Services\AI\OpenAiService;
use App\Services\PerplexitySearchService;
use App\Services\Seo\JsonLdService;
use App\Services\Content\SeoChecklistService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates complete articles from clusters of forum questions.
 * The top 6-8 most popular questions become H2 sections,
 * remaining questions become FAQ entries.
 */
class ArticleFromQuestionsService
{
    public function __construct(
        private OpenAiService $openAi,
        private PerplexitySearchService $perplexity,
        private SlugService $slugService,
        private JsonLdService $jsonLd,
        private SeoAnalysisService $seoAnalysis,
        private SeoChecklistService $seoChecklist,
    ) {}

    /**
     * Generate a full article from a question cluster.
     */
    public function generateFromCluster(QuestionCluster $cluster): GeneratedArticle
    {
        $startTime = microtime(true);

        $dedup = app(DeduplicationService::class);
        $existing = $dedup->findDuplicateArticle($cluster->name, $cluster->country, $cluster->language);
        if ($existing) {
            Log::warning('ArticleFromQuestionsService: duplicate article exists', ['cluster' => $cluster->id, 'existing' => $existing->id]);
            $cluster->update(['generated_article_id' => $existing->id, 'status' => 'completed']);
            return $existing;
        }

        try {
            $cluster->update(['status' => 'generating_article']);

            // Get all questions sorted by popularity
            $questions = $cluster->questions()
                ->get()
                ->sortByDesc(fn ($q) => ($q->views ?? 0) + ($q->replies ?? 0) * 10)
                ->values();

            if ($questions->isEmpty()) {
                throw new \RuntimeException("Cluster {$cluster->id} has no questions");
            }

            $year = date('Y');
            $country = $cluster->country ?? '';
            $language = $cluster->language ?? 'fr';

            // Split: top 6-8 become H2 sections, rest become FAQ
            $h2Questions = $questions->take(8);
            $faqQuestions = $questions->slice(8);

            Log::info('ArticleFromQuestions: generation started', [
                'cluster_id' => $cluster->id,
                'h2_count' => $h2Questions->count(),
                'faq_count' => $faqQuestions->count(),
            ]);

            // Phase 1: Research via Perplexity
            $researchData = '';
            if ($this->perplexity->isConfigured()) {
                $topTitles = $h2Questions->pluck('title')->implode(', ');
                $researchQuery = "Recherche exhaustive sur '{$cluster->name}' pour les expatriés en {$country}. "
                    . "Couvre: {$topTitles}. Données {$year}, sources officielles.";

                $result = $this->perplexity->search($researchQuery, $language);

                if ($result['success'] && !empty($result['text'])) {
                    $researchData = $result['text'];
                }
            }

            // Phase 2: Generate article via GPT-4o
            $questionsContext = $h2Questions->map(function ($q) {
                $views = $q->views ?? 0;
                $replies = $q->replies ?? 0;
                return "- \"{$q->title}\" ({$views} vues, {$replies} réponses)";
            })->implode("\n");

            $faqContext = '';
            if ($faqQuestions->isNotEmpty()) {
                $faqContext = "\n\nQuestions FAQ (à inclure en section FAQ):\n"
                    . $faqQuestions->map(fn ($q) => "- \"{$q->title}\"")->implode("\n");
            }

            $systemPrompt = "Tu es un rédacteur web expert SEO spécialisé en expatriation. Tu crées des articles de 2500-4000 mots à partir de vraies questions d'expatriés.\n\n"
                . "STRUCTURE OBLIGATOIRE:\n"
                . "- H1: Titre SEO optimisé avec mot-clé principal + année {$year}\n"
                . "- Paragraphe définition 40-60 mots après premier H2 (featured snippet)\n"
                . "- Chaque H2 = une VRAIE question d'expatrié (les plus populaires)\n"
                . "- Sous chaque H2: réponse directe 40-60 mots PUIS développement détaillé\n"
                . "- Au moins 1 <table> comparatif\n"
                . "- Au moins 1 <ol> pour les processus\n"
                . "- FAQ: les questions restantes du cluster\n"
                . "- Conclusion avec CTA vers SOS-Expat\n"
                . "- Pas de <h1>, <html>, <head>, <body>\n\n"
                . "Les H2-questions doivent être les VRAIES questions que les expatriés posent (données ci-dessous).\n\n"
                . "Retourne en JSON:\n"
                . "{\n"
                . "  title: string (H1 SEO, max 70 chars),\n"
                . "  excerpt: string (40-60 mots, featured snippet),\n"
                . "  content_html: string (article complet en HTML),\n"
                . "  meta_title: string (max 60 chars),\n"
                . "  meta_description: string (140-160 chars),\n"
                . "  faq: [{question: string, answer: string}]\n"
                . "}";

            $userPrompt = "Pays: {$country}\nLangue: {$language}\nAnnée: {$year}\n\n"
                . "Questions principales (H2):\n{$questionsContext}"
                . $faqContext
                . (!empty($researchData) ? "\n\nDonnées de recherche:\n" . mb_substr($researchData, 0, 4000) : '');

            $aiResult = $this->openAi->complete(
                $systemPrompt,
                $userPrompt,
                [
                    'model' => 'gpt-4o',
                    'temperature' => 0.6,
                    'max_tokens' => 8000,
                    'json_mode' => true,
                ]
            );

            if (!$aiResult['success']) {
                throw new \RuntimeException('GPT-4o article generation failed: ' . ($aiResult['error'] ?? 'unknown'));
            }

            $parsed = json_decode($aiResult['content'], true);

            if (empty($parsed['content_html'])) {
                throw new \RuntimeException('GPT-4o returned empty content_html');
            }

            // Phase 3: Create GeneratedArticle
            $title = $parsed['title'] ?? $cluster->name;
            $slug = $this->slugService->generateSlug($title, $language);
            $slug = $this->slugService->ensureUnique($slug, $language, 'generated_articles');

            $contentHtml = $parsed['content_html'] ?? '';
            $contentText = strip_tags($contentHtml);
            $wordCount = str_word_count($contentText);

            $article = GeneratedArticle::create([
                'uuid' => (string) Str::uuid(),
                'title' => Str::limit($title, 250),
                'slug' => $slug,
                'content_html' => $contentHtml,
                'content_text' => $contentText,
                'excerpt' => mb_substr($parsed['excerpt'] ?? '', 0, 500),
                'meta_title' => mb_substr($parsed['meta_title'] ?? $title, 0, 60),
                'meta_description' => mb_substr($parsed['meta_description'] ?? '', 0, 160),
                'keywords_primary' => mb_substr($cluster->name, 0, 100),
                'language' => $language,
                'country' => $country,
                'content_type' => 'article',
                'word_count' => $wordCount,
                'reading_time_minutes' => max(1, (int) ceil($wordCount / 250)),
                'generation_model' => 'gpt-4o',
                'generation_duration_seconds' => (int) (microtime(true) - $startTime),
                'status' => 'draft',
            ]);

            // Phase 4: Generate FAQ entries
            $faqItems = $parsed['faq'] ?? [];
            $sortOrder = 1;
            foreach ($faqItems as $faqItem) {
                if (!empty($faqItem['question']) && !empty($faqItem['answer'])) {
                    GeneratedArticleFaq::create([
                        'article_id' => $article->id,
                        'question' => $faqItem['question'],
                        'answer' => $faqItem['answer'],
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            // Phase 5: Generate JSON-LD
            $jsonLdData = $this->generateArticleJsonLd($article, $faqItems);
            $article->update(['json_ld' => $jsonLdData]);

            // Phase 6: SEO analysis and checklist
            try {
                $this->seoAnalysis->analyze($article);
                $this->seoChecklist->evaluate($article);
            } catch (\Throwable $e) {
                Log::warning('ArticleFromQuestions: SEO analysis failed (non-blocking)', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Phase 7: Link back to cluster and questions
            $cluster->update([
                'generated_article_id' => $article->id,
                'status' => $cluster->generated_qa_count > 0 ? 'completed' : 'ready',
            ]);

            // Update all content_questions in the cluster
            foreach ($questions as $question) {
                $question->update([
                    'generated_article_id' => $article->id,
                    'article_status' => 'generated',
                ]);
            }

            Log::info('ArticleFromQuestions: generation complete', [
                'cluster_id' => $cluster->id,
                'article_id' => $article->id,
                'word_count' => $wordCount,
                'faq_count' => count($faqItems),
                'duration_s' => (int) (microtime(true) - $startTime),
            ]);

            return $article;
        } catch (\Throwable $e) {
            Log::error('ArticleFromQuestions: generation failed', [
                'cluster_id' => $cluster->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $cluster->update(['status' => 'pending']);

            throw $e;
        }
    }

    /**
     * Generate JSON-LD for the article (Article + FAQPage + BreadcrumbList).
     */
    private function generateArticleJsonLd(GeneratedArticle $article, array $faqItems): array
    {
        $countrySlug = Str::slug($article->country ?: 'general');
        $url = "/{$article->language}/blog/{$article->slug}";

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article->title,
                'description' => $article->meta_description,
                'datePublished' => now()->toIso8601String(),
                'dateModified' => now()->toIso8601String(),
                'author' => [
                    '@type' => 'Organization',
                    'name' => 'SOS-Expat',
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'SOS-Expat',
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $url,
                ],
                'wordCount' => $article->word_count,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => "/{$article->language}",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Blog',
                        'item' => "/{$article->language}/blog",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $article->country ?: 'General',
                        'item' => "/{$article->language}/blog/{$countrySlug}",
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 4,
                        'name' => mb_substr($article->title, 0, 50),
                        'item' => $url,
                    ],
                ],
            ],
        ];

        // Add FAQPage schema if there are FAQ items
        if (!empty($faqItems)) {
            $faqEntities = [];
            foreach ($faqItems as $item) {
                if (!empty($item['question']) && !empty($item['answer'])) {
                    $faqEntities[] = [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ];
                }
            }

            if (!empty($faqEntities)) {
                $jsonLd[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqEntities,
                ];
            }
        }

        return $jsonLd;
    }
}
