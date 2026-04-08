<?php

namespace App\Services\Content;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\Seo\HreflangService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Translation service — translates articles and comparatives to other languages.
 * Preserves HTML structure, URLs, brand names, and numbers.
 */
class TranslationService
{
    public function __construct(
        private OpenAiService $openAi,
        private ClaudeService $claude,
        private SlugService $slugService,
        private HreflangService $hreflang,
        private SeoAnalysisService $seoAnalysis,
    ) {}

    /**
     * Translate an article to a target language.
     */
    public function translateArticle(GeneratedArticle $original, string $targetLanguage): GeneratedArticle
    {
        $startTime = microtime(true);
        $fromLang = $original->language;

        Log::info('Article translation started', [
            'original_id' => $original->id,
            'from' => $fromLang,
            'to' => $targetLanguage,
        ]);

        try {
            // Translate title (plain text — strip any HTML from source)
            $cleanTitle = strip_tags($original->title);
            $translatedTitle = strip_tags($this->translateText($cleanTitle, $fromLang, $targetLanguage));
            $translatedTitle = preg_replace('/^```\w*\s*|\s*```$/m', '', $translatedTitle);
            $translatedTitle = trim($translatedTitle, " \t\n\r\"'");

            // Translate excerpt (plain text)
            $translatedExcerpt = strip_tags($this->translateText(strip_tags($original->excerpt ?? ''), $fromLang, $targetLanguage));
            $translatedExcerpt = trim($translatedExcerpt, " \t\n\r\"'");

            // Translate content HTML (preserving structure)
            $translatedContent = $this->translateText($original->content_html ?? '', $fromLang, $targetLanguage);
            // Clean markdown fences and full HTML wrappers from translated content
            $translatedContent = preg_replace('/^```(?:html)?\s*\n?/i', '', $translatedContent);
            $translatedContent = preg_replace('/\n?```\s*$/i', '', $translatedContent);
            $translatedContent = preg_replace('/<html[^>]*>|<\/html>/i', '', $translatedContent);
            $translatedContent = preg_replace('/<head>.*?<\/head>/is', '', $translatedContent);
            $translatedContent = preg_replace('/<body[^>]*>|<\/body>/i', '', $translatedContent);
            $translatedContent = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $translatedContent);
            $translatedContent = trim($translatedContent);

            // Translate meta tags (plain text — strip HTML, enforce SEO lengths)
            $translatedMetaTitle = strip_tags($this->translateText(strip_tags($original->meta_title ?? ''), $fromLang, $targetLanguage));
            $translatedMetaTitle = trim($translatedMetaTitle, " \t\n\r\"'");
            $translatedMetaDescription = strip_tags($this->translateText(strip_tags($original->meta_description ?? ''), $fromLang, $targetLanguage));
            $translatedMetaDescription = trim($translatedMetaDescription, " \t\n\r\"'");

            // Generate localized slug
            $slug = $this->slugService->generateSlug($translatedTitle, $targetLanguage);
            $slug = $this->slugService->ensureUnique($slug, $targetLanguage);

            // Determine parent: use the root article (not a translation of a translation)
            $parentId = $original->parent_article_id ?? $original->id;

            // Adapt JSON-LD: replace language prefix in URLs (same pattern as QA translations)
            $adaptedJsonLd = $original->json_ld;
            if ($adaptedJsonLd) {
                $jsonLdStr = json_encode($adaptedJsonLd);
                $jsonLdStr = str_replace("/{$fromLang}-", "/{$targetLanguage}-", $jsonLdStr);
                $adaptedJsonLd = json_decode($jsonLdStr, true);
            }

            // Create the translated article
            $translatedArticle = GeneratedArticle::create([
                'uuid' => (string) Str::uuid(),
                'parent_article_id' => $parentId,
                'pillar_article_id' => $original->pillar_article_id,
                'source_article_id' => $original->source_article_id,
                'generation_preset_id' => $original->generation_preset_id,
                'title' => $translatedTitle,
                'slug' => $slug,
                'content_html' => $translatedContent,
                'excerpt' => $translatedExcerpt,
                'meta_title' => mb_substr($translatedMetaTitle, 0, 60),
                'meta_description' => mb_substr($translatedMetaDescription, 0, 155),
                'keywords_primary' => $original->keywords_primary,
                'keywords_secondary' => $original->keywords_secondary,
                'language' => $targetLanguage,
                'country' => $original->country,
                'content_type' => $original->content_type,
                'status' => 'review',
                'word_count' => $this->seoAnalysis->countWords($translatedContent),
                'reading_time_minutes' => max(1, (int) ceil($this->seoAnalysis->countWords($translatedContent) / 250)),
                'created_by' => $original->created_by,
                // Inherit images from parent (og_image_url is the same across all languages)
                'featured_image_url' => $original->featured_image_url,
                'featured_image_alt' => $original->featured_image_alt,
                'featured_image_attribution' => $original->featured_image_attribution,
                'featured_image_srcset' => $original->featured_image_srcset,
                'photographer_name' => $original->photographer_name,
                'photographer_url' => $original->photographer_url,
                'unsplash_photographer_name' => $original->unsplash_photographer_name,
                'unsplash_photographer_url' => $original->unsplash_photographer_url,
                'image_width' => $original->image_width,
                'image_height' => $original->image_height,
                // Inherit geo meta (same country = same geo data)
                'geo_region' => $original->geo_region,
                'geo_placename' => $original->geo_placename,
                'geo_position' => $original->geo_position,
                'icbm' => $original->icbm,
                // Adapted JSON-LD with localized URLs
                'json_ld' => $adaptedJsonLd,
            ]);

            // Translate FAQs
            $originalFaqs = $original->faqs()->get();
            if ($originalFaqs->isNotEmpty()) {
                $translatedFaqs = $this->translateFaqs($originalFaqs, $fromLang, $targetLanguage);

                foreach ($translatedFaqs as $index => $faq) {
                    GeneratedArticleFaq::create([
                        'article_id' => $translatedArticle->id,
                        'question' => mb_substr(strip_tags($faq['question'] ?? ''), 0, 255),
                        'answer' => $faq['answer'] ?? '',
                        'sort_order' => $index,
                    ]);
                }
            }

            // Sync hreflang maps
            $this->hreflang->syncAllTranslations($translatedArticle);

            // Run SEO analysis on translation
            $this->seoAnalysis->analyze($translatedArticle);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('Article translation complete', [
                'original_id' => $original->id,
                'translated_id' => $translatedArticle->id,
                'target_language' => $targetLanguage,
                'duration_ms' => $durationMs,
            ]);

            return $translatedArticle;
        } catch (\Throwable $e) {
            Log::error('Article translation failed', [
                'original_id' => $original->id,
                'target_language' => $targetLanguage,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Translate a comparative to a target language.
     */
    public function translateComparative(Comparative $original, string $targetLanguage): Comparative
    {
        $fromLang = $original->language;

        Log::info('Comparative translation started', [
            'original_id' => $original->id,
            'from' => $fromLang,
            'to' => $targetLanguage,
        ]);

        try {
            $translatedTitle = $this->translateText($original->title, $fromLang, $targetLanguage);
            $translatedExcerpt = $this->translateText($original->excerpt ?? '', $fromLang, $targetLanguage);
            $translatedContent = $this->translateText($original->content_html ?? '', $fromLang, $targetLanguage);
            $translatedMetaTitle = $this->translateText($original->meta_title ?? '', $fromLang, $targetLanguage);
            $translatedMetaDescription = $this->translateText($original->meta_description ?? '', $fromLang, $targetLanguage);

            $slug = $this->slugService->generateSlug($translatedTitle, $targetLanguage);
            $slug = $this->slugService->ensureUnique($slug, $targetLanguage, 'comparatives');

            $parentId = $original->parent_id ?? $original->id;

            $translatedComparative = Comparative::create([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $parentId,
                'title' => $translatedTitle,
                'slug' => $slug,
                'content_html' => $translatedContent,
                'excerpt' => $translatedExcerpt,
                'meta_title' => mb_substr($translatedMetaTitle, 0, 60),
                'meta_description' => mb_substr($translatedMetaDescription, 0, 155),
                'language' => $targetLanguage,
                'country' => $original->country,
                'entities' => $original->entities,
                'comparison_data' => $original->comparison_data, // Data stays same, labels translated in content
                'status' => 'review',
                'created_by' => $original->created_by,
            ]);

            // Run SEO analysis
            $this->seoAnalysis->analyze($translatedComparative);

            Log::info('Comparative translation complete', [
                'original_id' => $original->id,
                'translated_id' => $translatedComparative->id,
            ]);

            return $translatedComparative;
        } catch (\Throwable $e) {
            Log::error('Comparative translation failed', [
                'original_id' => $original->id,
                'target_language' => $targetLanguage,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Translate a text string — gpt-4o-mini primary, Claude Haiku fallback.
     * Preserves HTML structure.
     */
    private function translateText(string $text, string $from, string $to): string
    {
        if (empty(trim($text))) {
            return '';
        }

        // Primary: OpenAI gpt-4o-mini
        $result = $this->openAi->translate($text, $from, $to);

        if ($result['success']) {
            $translated = trim($result['content']);

            // Validate HTML structure is preserved (basic check)
            $originalTags = $this->countHtmlTags($text);
            $translatedTags = $this->countHtmlTags($translated);

            if ($originalTags > 0 && $translatedTags < $originalTags * 0.7) {
                Log::warning('Translation may have broken HTML structure', [
                    'original_tags' => $originalTags,
                    'translated_tags' => $translatedTags,
                    'from' => $from,
                    'to' => $to,
                ]);
            }

            return $translated;
        }

        // Fallback: Claude Haiku (cheaper, reliable for translations)
        Log::warning('OpenAI translation failed — falling back to Claude Haiku', [
            'error' => $result['error'] ?? 'unknown',
            'from' => $from,
            'to' => $to,
        ]);

        if ($this->claude->isConfigured()) {
            $fallback = $this->claude->translate($text, $from, $to);

            if ($fallback['success']) {
                Log::info('Claude Haiku fallback translation succeeded', ['from' => $from, 'to' => $to]);
                return trim($fallback['content']);
            }
        }

        // Last resort: return original text untranslated
        Log::error('Both translation providers failed, returning original text', [
            'from' => $from,
            'to' => $to,
        ]);

        return $text;
    }

    /**
     * Translate all FAQs in a single batch API call for efficiency.
     *
     * @return array<array{question: string, answer: string}>
     */
    private function translateFaqs(\Illuminate\Support\Collection $faqs, string $from, string $to): array
    {
        if ($faqs->isEmpty()) {
            return [];
        }

        // Build a single JSON structure for batch translation
        $faqData = [];
        foreach ($faqs as $faq) {
            $faqData[] = [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ];
        }

        $jsonInput = json_encode($faqData, JSON_UNESCAPED_UNICODE);

        $systemPrompt = "You are a professional translator. Translate the following FAQ items from {$from} to {$to}. "
            . "Return the EXACT same JSON structure with translated questions and answers. "
            . "Do not translate brand names, URLs, or technical terms. Preserve HTML tags in answers.";

        $result = $this->openAi->complete($systemPrompt, $jsonInput, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'max_tokens' => 4000,
            'json_mode' => true,
        ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);

            // Handle various JSON structures
            $items = $parsed['faqs'] ?? $parsed['items'] ?? $parsed ?? [];
            if (isset($items[0]['question'])) {
                return $items;
            }
        }

        // Fallback: translate individually
        Log::warning('Batch FAQ translation failed, translating individually');

        $translated = [];
        foreach ($faqs as $faq) {
            $translated[] = [
                'question' => $this->translateText($faq->question, $from, $to),
                'answer' => $this->translateText($faq->answer, $from, $to),
            ];
        }

        return $translated;
    }

    /**
     * Count HTML tags in a string for structure validation.
     */
    private function countHtmlTags(string $html): int
    {
        return preg_match_all('/<\/?[a-z][a-z0-9]*[^>]*>/i', $html);
    }
}
