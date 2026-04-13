<?php

namespace App\Services\Content;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\Content\AudienceContextService;
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
            // Translate title using dedicated short-text method (NOT the generic translateText)
            $cleanTitle = strip_tags($original->title);
            $translatedTitle = $this->translateShortText($cleanTitle, $fromLang, $targetLanguage, 'title');

            // Translate excerpt (plain text — short text method)
            $translatedExcerpt = $this->translateShortText(strip_tags($original->excerpt ?? ''), $fromLang, $targetLanguage, 'excerpt');

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

            // Translate meta tags (plain text — short text method with SEO context)
            $translatedMetaTitle = $this->translateShortText(strip_tags($original->meta_title ?? ''), $fromLang, $targetLanguage, 'meta_title');
            $translatedMetaDescription = $this->translateShortText(strip_tags($original->meta_description ?? ''), $fromLang, $targetLanguage, 'meta_description');

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
                'title' => mb_substr($translatedTitle, 0, 255),
                'slug' => $slug,
                'content_html' => $translatedContent,
                'excerpt' => mb_substr($translatedExcerpt, 0, 500),
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
                // Inherit OG & AEO metadata (will be translated below)
                'og_type' => $original->og_type ?? 'article',
                'og_locale' => $this->mapLocale($targetLanguage),
                'og_site_name' => $original->og_site_name,
                'twitter_card' => $original->twitter_card ?? 'summary_large_image',
                'content_language' => $targetLanguage,
                'last_reviewed_at' => now(),
            ]);

            // Generate OG title, OG description, and AI summary for translation
            $this->generateTranslationOgMeta($translatedArticle, $translatedTitle, $translatedExcerpt, $targetLanguage);

            // Set canonical URL for translation
            $siteUrl = config('services.blog.site_url', config('services.site.url', 'https://sos-expat.com'));
            $translatedArticle->update([
                'canonical_url' => "{$siteUrl}/{$targetLanguage}/articles/{$slug}",
                'og_url' => "{$siteUrl}/{$targetLanguage}/articles/{$slug}",
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
            $translatedTitle = $this->translateShortText(strip_tags($original->title), $fromLang, $targetLanguage, 'title');
            $translatedExcerpt = $this->translateShortText(strip_tags($original->excerpt ?? ''), $fromLang, $targetLanguage, 'excerpt');
            $translatedContent = $this->translateText($original->content_html ?? '', $fromLang, $targetLanguage);
            $translatedMetaTitle = $this->translateShortText(strip_tags($original->meta_title ?? ''), $fromLang, $targetLanguage, 'meta_title');
            $translatedMetaDescription = $this->translateShortText(strip_tags($original->meta_description ?? ''), $fromLang, $targetLanguage, 'meta_description');

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

        // Inject the target-language audience context so the translator adapts
        // culture-specific examples (banks, tax authorities, first names) to the
        // new readership rather than translating them literally.
        $audienceContext = AudienceContextService::getContextFor($to);

        // Primary: OpenAI gpt-4o-mini
        $result = $this->openAi->translate($text, $from, $to, [
            'audience_context' => $audienceContext,
        ]);

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
     * Translate a short text (title, excerpt, meta) with a dedicated prompt and length guard.
     * Unlike translateText(), this uses max_tokens limit and validates output length.
     */
    private function translateShortText(string $text, string $from, string $to, string $field = 'title'): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $langNames = [
            'fr' => 'français', 'en' => 'English', 'es' => 'español', 'de' => 'Deutsch',
            'pt' => 'português', 'ru' => 'русский', 'zh' => '中文', 'ar' => 'العربية', 'hi' => 'हिन्दी',
        ];
        $toName = $langNames[$to] ?? $to;

        $maxTokensByField = [
            'title' => 150,
            'excerpt' => 300,
            'meta_title' => 100,
            'meta_description' => 200,
        ];
        $maxTokens = $maxTokensByField[$field] ?? 200;

        // Max acceptable length ratio: translated text should not exceed 3x the original
        $maxLenRatio = 3.0;

        $prompt = match ($field) {
            'title' => "Translate this article TITLE to {$toName}. Return ONLY the translated title on a single line. No explanation, no HTML, no extra text.",
            'meta_title' => "Translate this SEO meta title to {$toName}. Return ONLY the translated meta title (max 60 characters). No explanation.",
            'meta_description' => "Translate this SEO meta description to {$toName}. Return ONLY the translated meta description (max 155 characters). No explanation.",
            'excerpt' => "Translate this article excerpt to {$toName}. Return ONLY the translated excerpt. No explanation, no HTML.",
            default => "Translate this text to {$toName}. Return ONLY the translation, nothing else.",
        };

        $result = $this->openAi->complete($prompt, $text, [
            'temperature' => 0.3,
            'max_tokens' => $maxTokens,
        ]);

        if ($result['success']) {
            $translated = strip_tags(trim($result['content']));
            $translated = preg_replace('/^```\w*\s*|\s*```$/m', '', $translated);
            $translated = trim($translated, " \t\n\r\"'");

            // Take only the first line (guard against multi-line responses)
            $firstLine = explode("\n", $translated)[0];
            $translated = trim($firstLine);

            // Length guard: if translated text is suspiciously long, it's contaminated
            $originalLen = mb_strlen($text);
            if ($originalLen > 0 && mb_strlen($translated) > $originalLen * $maxLenRatio) {
                Log::warning("Translation {$field} too long — likely contaminated, retrying", [
                    'from' => $from, 'to' => $to, 'original_len' => $originalLen,
                    'translated_len' => mb_strlen($translated), 'field' => $field,
                ]);

                // Retry with even stricter prompt
                $retryResult = $this->openAi->complete(
                    "Translate to {$toName}. Output ONLY the translation. ONE LINE. NOTHING ELSE.",
                    $text,
                    ['temperature' => 0.2, 'max_tokens' => $maxTokens]
                );

                if ($retryResult['success']) {
                    $retried = strip_tags(trim($retryResult['content'], " \t\n\r\"'"));
                    $retried = explode("\n", $retried)[0];
                    if (!empty($retried) && mb_strlen($retried) <= $originalLen * $maxLenRatio) {
                        $translated = trim($retried);
                    }
                }
            }

            // If still empty or identical to source, keep original
            if (empty($translated)) {
                return $text;
            }
            if ($translated === $text && $from !== $to) {
                // Identical to source — one last retry
                $lastResult = $this->openAi->complete(
                    "Translate this {$field} to {$toName}. Return ONLY the translated {$field}, nothing else.",
                    $text,
                    ['temperature' => 0.3, 'max_tokens' => $maxTokens]
                );
                if ($lastResult['success']) {
                    $last = strip_tags(trim($lastResult['content'], " \t\n\r\"'"));
                    if (!empty($last) && $last !== $text) {
                        return $last;
                    }
                }
            }

            return $translated;
        }

        // Fallback: Claude
        if ($this->claude->isConfigured()) {
            $fallback = $this->claude->translate($text, $from, $to);
            if ($fallback['success']) {
                $translated = strip_tags(trim($fallback['content']));
                $firstLine = explode("\n", $translated)[0];
                return trim($firstLine, " \t\n\r\"'");
            }
        }

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

    /**
     * Generate OG title, OG description, and AI summary for a translated article.
     */
    private function generateTranslationOgMeta(GeneratedArticle $article, string $title, string $excerpt, string $lang): void
    {
        try {
            $langNames = [
                'fr' => 'français', 'en' => 'English', 'es' => 'español', 'de' => 'Deutsch',
                'pt' => 'português', 'ru' => 'русский', 'zh' => '中文', 'ar' => 'العربية', 'hi' => 'हिन्दी',
            ];
            $langName = $langNames[$lang] ?? $lang;

            $result = $this->openAi->complete(
                "Generate social media metadata in {$langName}. Return valid JSON only.",
                "Article title: {$title}\nExcerpt: {$excerpt}\n\n"
                    . "Return JSON: {\"og_title\": \"engaging social title max 95 chars\", "
                    . "\"og_description\": \"call-to-action max 200 chars\", "
                    . "\"ai_summary\": \"factual 2-3 sentences max 160 chars, NO 'This article...'\"}",
                ['temperature' => 0.5, 'max_tokens' => 400, 'json' => true]
            );

            if ($result['success']) {
                $data = json_decode($result['content'], true);
                if ($data) {
                    $article->update(array_filter([
                        'og_title' => mb_substr($data['og_title'] ?? '', 0, 95) ?: null,
                        'og_description' => mb_substr($data['og_description'] ?? '', 0, 200) ?: null,
                        'ai_summary' => mb_substr($data['ai_summary'] ?? '', 0, 160) ?: null,
                    ]));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Translation OG meta generation failed (non-blocking)', [
                'article_id' => $article->id,
                'language' => $lang,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map language code to OG locale format.
     */
    private function mapLocale(string $lang): string
    {
        return match ($lang) {
            'fr' => 'fr_FR', 'en' => 'en_US', 'es' => 'es_ES', 'de' => 'de_DE',
            'pt' => 'pt_BR', 'ru' => 'ru_RU', 'zh' => 'zh_CN', 'ar' => 'ar_SA', 'hi' => 'hi_IN',
            default => $lang,
        };
    }
}
