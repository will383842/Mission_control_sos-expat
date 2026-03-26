<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\SeoChecklist;
use Illuminate\Support\Facades\Log;

/**
 * SEO checklist evaluator — runs 30+ checks on a generated article
 * and produces a comprehensive SeoChecklist record with pass/fail scores.
 */
class SeoChecklistService
{
    /**
     * Evaluate a generated article against the full SEO checklist.
     */
    public function evaluate(GeneratedArticle $article): SeoChecklist
    {
        try {
            $article->load(['faqs', 'images', 'translations', 'internalLinksOut', 'externalLinks']);

            $html = $article->content_html ?? '';
            $text = strip_tags($html);
            $textLower = mb_strtolower($text);
            $htmlLower = mb_strtolower($html);
            $primaryKeyword = mb_strtolower($article->keywords_primary ?? '');
            $jsonLd = $article->json_ld ?? [];

            // ================================================================
            // On-Page checks
            // ================================================================
            $h1Count = preg_match_all('/<h1[^>]*>/i', $html);
            $hasSingleH1 = $h1Count <= 1; // 0 is ok (H1 is the page title), 1 is also ok

            $h1ContainsKeyword = false;
            if (!empty($primaryKeyword)) {
                preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Match);
                $h1Text = mb_strtolower(strip_tags($h1Match[1] ?? ''));
                $h1ContainsKeyword = mb_strpos($h1Text, $primaryKeyword) !== false;
            }

            $titleTagLength = mb_strlen($article->meta_title ?? '');
            $titleTagContainsKeyword = !empty($primaryKeyword)
                && mb_strpos(mb_strtolower($article->meta_title ?? ''), $primaryKeyword) !== false;

            $metaDescLength = mb_strlen($article->meta_description ?? '');
            $metaDescContainsCta = (bool) preg_match(
                '/(découvr|appren|savoir plus|en savoir|cliquez|consultez|trouvez|learn|discover|find out|click|read more)/i',
                $article->meta_description ?? ''
            );

            // First paragraph contains keyword
            $keywordInFirstParagraph = false;
            if (!empty($primaryKeyword)) {
                preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $firstP);
                $firstPText = mb_strtolower(strip_tags($firstP[1] ?? ''));
                $keywordInFirstParagraph = mb_strpos($firstPText, $primaryKeyword) !== false;
            }

            // Keyword density (1-2% is ideal)
            $totalWords = str_word_count($text);
            $keywordOccurrences = !empty($primaryKeyword)
                ? mb_substr_count($textLower, $primaryKeyword)
                : 0;
            $density = ($totalWords > 0) ? ($keywordOccurrences / $totalWords) * 100 : 0;
            $keywordDensityOk = $density >= 0.5 && $density <= 3.0;

            // Heading hierarchy (H2 before H3, etc.)
            $headingHierarchyValid = $this->checkHeadingHierarchy($html);

            // Has table or list
            $hasTableOrList = (bool) preg_match('/<(table|ul|ol)[^>]*>/i', $html);

            // ================================================================
            // Structured Data checks
            // ================================================================
            $jsonLdString = json_encode($jsonLd);
            $hasArticleSchema = mb_strpos($jsonLdString, '"Article"') !== false
                || mb_strpos($jsonLdString, '"BlogPosting"') !== false;
            $hasFaqSchema = mb_strpos($jsonLdString, '"FAQPage"') !== false;
            $hasBreadcrumbSchema = mb_strpos($jsonLdString, '"BreadcrumbList"') !== false;
            $hasSpeakableSchema = mb_strpos($jsonLdString, '"SpeakableSpecification"') !== false
                || mb_strpos($jsonLdString, '"Speakable"') !== false;
            $hasHowtoSchema = mb_strpos($jsonLdString, '"HowTo"') !== false;
            $jsonLdValid = !empty($jsonLd) && is_array($jsonLd);

            // ================================================================
            // E-E-A-T checks
            // ================================================================
            $hasAuthorBox = (bool) preg_match('/(author|auteur|rédigé par|written by)/i', $html);
            $hasSourcesCited = (bool) preg_match('/(sources?|références|references)/i', $html);
            $hasDatePublished = $article->published_at !== null;
            $hasDateModified = $article->updated_at !== null && $article->updated_at->ne($article->created_at);
            $hasOfficialLinks = (bool) preg_match('/href="https?:\/\/(www\.)?(gouv|gov|europa|un\.org|who\.int)/i', $html);

            // ================================================================
            // Links checks
            // ================================================================
            $internalLinksCount = $article->internalLinksOut()->count();
            $externalLinksCount = $article->externalLinks()->count();
            $officialLinksCount = 0;
            foreach ($article->externalLinks as $link) {
                if (preg_match('/(gouv|gov|europa|un\.org|who\.int|oecd|imf)/i', $link->domain ?? '')) {
                    $officialLinksCount++;
                }
            }
            $brokenLinksCount = 0; // Would need HTTP checks, set to 0 for now

            // ================================================================
            // Featured Snippets checks
            // ================================================================
            $hasDefinitionParagraph = false;
            preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $firstParagraph);
            $firstParagraphText = strip_tags($firstParagraph[1] ?? '');
            $firstParagraphWordCount = str_word_count($firstParagraphText);
            if ($firstParagraphWordCount >= 35 && $firstParagraphWordCount <= 70) {
                $hasDefinitionParagraph = true;
            }

            $hasNumberedSteps = (bool) preg_match('/<ol[^>]*>/i', $html);
            $hasComparisonTable = (bool) preg_match('/<table[^>]*>/i', $html);

            // ================================================================
            // AEO (Answer Engine Optimization) checks
            // ================================================================
            $hasSpeakableContent = $hasSpeakableSchema;

            // Count H2s that are questions (contain ? or start with question words)
            $questionH2Count = 0;
            preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $html, $h2Matches);
            foreach ($h2Matches[1] ?? [] as $h2Text) {
                $cleanH2 = strip_tags($h2Text);
                if (str_contains($cleanH2, '?') || preg_match('/^(quel|comment|pourquoi|combien|quand|où|est-ce|faut-il|peut-on|what|how|why|when|where|which|is|can|do)/iu', trim($cleanH2))) {
                    $questionH2Count++;
                }
            }
            $hasDirectAnswers = $questionH2Count >= 3;
            $paaQuestionsCovered = $article->faqs()->count();

            // ================================================================
            // Images checks
            // ================================================================
            $imagesCount = $article->images()->count();
            $allImagesHaveAlt = true;
            foreach ($article->images as $img) {
                if (empty($img->alt_text)) {
                    $allImagesHaveAlt = false;
                    break;
                }
            }
            // Also check inline images
            preg_match_all('/<img[^>]*>/i', $html, $imgTags);
            foreach ($imgTags[0] ?? [] as $imgTag) {
                if (!preg_match('/alt\s*=\s*"[^"]+"/i', $imgTag)) {
                    $allImagesHaveAlt = false;
                    break;
                }
            }

            $featuredImageHasKeyword = false;
            if (!empty($primaryKeyword) && $article->featured_image_alt) {
                $featuredImageHasKeyword = mb_strpos(
                    mb_strtolower($article->featured_image_alt),
                    $primaryKeyword
                ) !== false;
            }

            // ================================================================
            // Translation checks
            // ================================================================
            $translationsCount = $article->translations()->count();
            $hreflangComplete = !empty($article->hreflang_map) && count($article->hreflang_map) > 1;

            // ================================================================
            // Calculate overall score (weighted)
            // ================================================================
            $weightedChecks = [
                // Featured Snippet critical (weight 3)
                'has_definition_paragraph' => ['value' => $hasDefinitionParagraph, 'weight' => 3],
                'has_direct_answers' => ['value' => $hasDirectAnswers, 'weight' => 3],
                'has_faq_schema' => ['value' => $hasFaqSchema, 'weight' => 3],
                'keyword_in_first_paragraph' => ['value' => $keywordInFirstParagraph, 'weight' => 3],
                // Structured Data important (weight 2)
                'has_article_schema' => ['value' => $hasArticleSchema, 'weight' => 2],
                'has_breadcrumb_schema' => ['value' => $hasBreadcrumbSchema, 'weight' => 2],
                'has_speakable_schema' => ['value' => $hasSpeakableSchema, 'weight' => 2],
                'has_howto_schema' => ['value' => $hasHowtoSchema, 'weight' => 1],
                'json_ld_valid' => ['value' => $jsonLdValid, 'weight' => 2],
                // On-Page standard (weight 1.5)
                'has_single_h1' => ['value' => $hasSingleH1, 'weight' => 1.5],
                'h1_contains_keyword' => ['value' => $h1ContainsKeyword, 'weight' => 1.5],
                'title_tag_contains_keyword' => ['value' => $titleTagContainsKeyword, 'weight' => 1.5],
                'meta_desc_contains_cta' => ['value' => $metaDescContainsCta, 'weight' => 1],
                'keyword_density_ok' => ['value' => $keywordDensityOk, 'weight' => 1.5],
                'heading_hierarchy_valid' => ['value' => $headingHierarchyValid, 'weight' => 1.5],
                'has_table_or_list' => ['value' => $hasTableOrList, 'weight' => 2],
                // E-E-A-T (weight 1.5)
                'has_author_box' => ['value' => $hasAuthorBox, 'weight' => 1.5],
                'has_sources_cited' => ['value' => $hasSourcesCited, 'weight' => 1.5],
                'has_date_published' => ['value' => $hasDatePublished, 'weight' => 1],
                'has_date_modified' => ['value' => $hasDateModified, 'weight' => 1],
                'has_official_links' => ['value' => $hasOfficialLinks, 'weight' => 1.5],
                // Snippets (weight 2)
                'has_numbered_steps' => ['value' => $hasNumberedSteps, 'weight' => 2],
                'has_comparison_table' => ['value' => $hasComparisonTable, 'weight' => 2],
                'has_speakable_content' => ['value' => $hasSpeakableContent, 'weight' => 2],
                // Images (weight 1)
                'all_images_have_alt' => ['value' => $allImagesHaveAlt, 'weight' => 1],
                'featured_image_has_keyword' => ['value' => $featuredImageHasKeyword, 'weight' => 1],
                // Translation (weight 1)
                'hreflang_complete' => ['value' => $hreflangComplete, 'weight' => 1],
            ];

            $totalWeight = 0;
            $earnedWeight = 0;
            foreach ($weightedChecks as $check) {
                $totalWeight += $check['weight'];
                if ($check['value']) {
                    $earnedWeight += $check['weight'];
                }
            }

            $overallScore = ($totalWeight > 0) ? (int) round(($earnedWeight / $totalWeight) * 100) : 0;

            // Create or update checklist
            $checklist = SeoChecklist::updateOrCreate(
                ['article_id' => $article->id],
                [
                    // On-Page
                    'has_single_h1' => $hasSingleH1,
                    'h1_contains_keyword' => $h1ContainsKeyword,
                    'title_tag_length' => $titleTagLength,
                    'title_tag_contains_keyword' => $titleTagContainsKeyword,
                    'meta_desc_length' => $metaDescLength,
                    'meta_desc_contains_cta' => $metaDescContainsCta,
                    'keyword_in_first_paragraph' => $keywordInFirstParagraph,
                    'keyword_density_ok' => $keywordDensityOk,
                    'heading_hierarchy_valid' => $headingHierarchyValid,
                    'has_table_or_list' => $hasTableOrList,
                    // Structured Data
                    'has_article_schema' => $hasArticleSchema,
                    'has_faq_schema' => $hasFaqSchema,
                    'has_breadcrumb_schema' => $hasBreadcrumbSchema,
                    'has_speakable_schema' => $hasSpeakableSchema,
                    'has_howto_schema' => $hasHowtoSchema,
                    'json_ld_valid' => $jsonLdValid,
                    // E-E-A-T
                    'has_author_box' => $hasAuthorBox,
                    'has_sources_cited' => $hasSourcesCited,
                    'has_date_published' => $hasDatePublished,
                    'has_date_modified' => $hasDateModified,
                    'has_official_links' => $hasOfficialLinks,
                    // Links
                    'internal_links_count' => $internalLinksCount,
                    'external_links_count' => $externalLinksCount,
                    'official_links_count' => $officialLinksCount,
                    'broken_links_count' => $brokenLinksCount,
                    // Featured Snippets
                    'has_definition_paragraph' => $hasDefinitionParagraph,
                    'has_numbered_steps' => $hasNumberedSteps,
                    'has_comparison_table' => $hasComparisonTable,
                    // AEO
                    'has_speakable_content' => $hasSpeakableContent,
                    'has_direct_answers' => $hasDirectAnswers,
                    'paa_questions_covered' => $paaQuestionsCovered,
                    // Images
                    'all_images_have_alt' => $allImagesHaveAlt,
                    'featured_image_has_keyword' => $featuredImageHasKeyword,
                    'images_count' => $imagesCount,
                    // Translation
                    'hreflang_complete' => $hreflangComplete,
                    'translations_count' => $translationsCount,
                    // Score
                    'overall_checklist_score' => $overallScore,
                ]
            );

            Log::info('SeoChecklist: evaluation complete', [
                'article_id' => $article->id,
                'score' => $overallScore,
                'earned_weight' => $earnedWeight,
                'total_weight' => $totalWeight,
            ]);

            return $checklist;
        } catch (\Throwable $e) {
            Log::error('SeoChecklist: evaluation failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get failed checks with human-readable descriptions and fix suggestions.
     *
     * @return array<array{check: string, description: string, suggestion: string}>
     */
    public function getFailedChecks(SeoChecklist $checklist): array
    {
        $failed = [];

        $checks = [
            ['field' => 'has_single_h1', 'description' => 'Article should have at most one H1 tag', 'suggestion' => 'Remove extra H1 tags, use H2 for section headings'],
            ['field' => 'h1_contains_keyword', 'description' => 'H1 should contain the primary keyword', 'suggestion' => 'Include the primary keyword in the H1 or page title'],
            ['field' => 'title_tag_contains_keyword', 'description' => 'Meta title should contain the primary keyword', 'suggestion' => 'Add the primary keyword to the meta title, preferably at the beginning'],
            ['field' => 'meta_desc_contains_cta', 'description' => 'Meta description should contain a call-to-action', 'suggestion' => 'Add action words like "Discover", "Learn", "Find out" to the meta description'],
            ['field' => 'keyword_in_first_paragraph', 'description' => 'Primary keyword should appear in the first paragraph', 'suggestion' => 'Mention the primary keyword naturally in the first paragraph'],
            ['field' => 'keyword_density_ok', 'description' => 'Keyword density should be between 0.5% and 3%', 'suggestion' => 'Adjust keyword usage: add more if below 0.5%, reduce if above 3%'],
            ['field' => 'heading_hierarchy_valid', 'description' => 'Heading hierarchy should be valid (H2 before H3, etc.)', 'suggestion' => 'Ensure headings follow proper hierarchy: H2 > H3 > H4'],
            ['field' => 'has_table_or_list', 'description' => 'Article should contain at least one list or table', 'suggestion' => 'Add a <ul>, <ol>, or <table> to improve readability and featured snippet chances'],
            ['field' => 'has_article_schema', 'description' => 'JSON-LD should include Article or BlogPosting schema', 'suggestion' => 'Add Article schema to the JSON-LD structured data'],
            ['field' => 'has_faq_schema', 'description' => 'JSON-LD should include FAQPage schema', 'suggestion' => 'Add FAQPage schema with the article FAQs'],
            ['field' => 'has_breadcrumb_schema', 'description' => 'JSON-LD should include BreadcrumbList schema', 'suggestion' => 'Add BreadcrumbList schema for better SERP display'],
            ['field' => 'has_speakable_schema', 'description' => 'JSON-LD should include Speakable schema for voice search', 'suggestion' => 'Add SpeakableSpecification schema targeting key paragraphs'],
            ['field' => 'json_ld_valid', 'description' => 'JSON-LD should be present and valid', 'suggestion' => 'Regenerate the JSON-LD structured data'],
            ['field' => 'has_author_box', 'description' => 'Article should have an author attribution (E-E-A-T)', 'suggestion' => 'Add author name/bio to demonstrate expertise'],
            ['field' => 'has_sources_cited', 'description' => 'Article should cite sources (E-E-A-T)', 'suggestion' => 'Add a Sources section with references to authoritative sites'],
            ['field' => 'has_date_published', 'description' => 'Article should have a publication date', 'suggestion' => 'Publish the article to set the publication date'],
            ['field' => 'has_official_links', 'description' => 'Article should link to official government/institution sources', 'suggestion' => 'Add links to .gouv, .gov, or international organization websites'],
            ['field' => 'has_definition_paragraph', 'description' => 'First paragraph should be 40-60 words (featured snippet)', 'suggestion' => 'Write a concise definition paragraph of 40-60 mots for Google featured snippet'],
            ['field' => 'has_numbered_steps', 'description' => 'Article should include numbered steps (<ol>)', 'suggestion' => 'Add an ordered list with step-by-step instructions'],
            ['field' => 'has_speakable_content', 'description' => 'Article should have speakable content for voice assistants', 'suggestion' => 'Mark key paragraphs as speakable in the schema'],
            ['field' => 'has_direct_answers', 'description' => 'Article should provide direct answers (for AEO)', 'suggestion' => 'Add FAQ section or concise answer paragraphs'],
            ['field' => 'all_images_have_alt', 'description' => 'All images should have descriptive alt text', 'suggestion' => 'Add descriptive alt text to every image, including the primary keyword'],
            ['field' => 'featured_image_has_keyword', 'description' => 'Featured image alt should contain the primary keyword', 'suggestion' => 'Update the featured image alt text to include the primary keyword'],
            ['field' => 'hreflang_complete', 'description' => 'Hreflang tags should cover all available translations', 'suggestion' => 'Generate translations and sync hreflang map'],
        ];

        // Numeric threshold checks
        if ($checklist->title_tag_length < 30 || $checklist->title_tag_length > 60) {
            $failed[] = [
                'check' => 'title_tag_length',
                'description' => "Meta title length is {$checklist->title_tag_length} chars (should be 30-60)",
                'suggestion' => 'Adjust meta title to be between 30 and 60 characters',
            ];
        }

        if ($checklist->meta_desc_length < 120 || $checklist->meta_desc_length > 160) {
            $failed[] = [
                'check' => 'meta_desc_length',
                'description' => "Meta description length is {$checklist->meta_desc_length} chars (should be 120-160)",
                'suggestion' => 'Adjust meta description to be between 120 and 160 characters',
            ];
        }

        if ($checklist->internal_links_count < 2) {
            $failed[] = [
                'check' => 'internal_links_count',
                'description' => "Only {$checklist->internal_links_count} internal links (minimum 2 recommended)",
                'suggestion' => 'Add internal links to related articles on the site',
            ];
        }

        if ($checklist->external_links_count < 1) {
            $failed[] = [
                'check' => 'external_links_count',
                'description' => 'No external links found (minimum 1 recommended)',
                'suggestion' => 'Add at least one external link to an authoritative source',
            ];
        }

        if ($checklist->images_count < 1) {
            $failed[] = [
                'check' => 'images_count',
                'description' => 'No images found (minimum 1 recommended)',
                'suggestion' => 'Add at least one relevant image with descriptive alt text',
            ];
        }

        // Boolean checks
        foreach ($checks as $check) {
            $value = $checklist->{$check['field']} ?? null;
            if ($value === false || $value === 0) {
                $failed[] = [
                    'check' => $check['field'],
                    'description' => $check['description'],
                    'suggestion' => $check['suggestion'],
                ];
            }
        }

        return $failed;
    }

    /**
     * Check heading hierarchy validity.
     */
    private function checkHeadingHierarchy(string $html): bool
    {
        preg_match_all('/<(h[1-6])[^>]*>/i', $html, $matches);

        $headings = $matches[1] ?? [];
        if (empty($headings)) {
            return true; // No headings = ok (content might not need them)
        }

        $lastLevel = 0;

        foreach ($headings as $heading) {
            $level = (int) substr($heading, 1);
            // Allow jumping from 0 to any level, but don't skip levels going deeper
            if ($lastLevel > 0 && $level > $lastLevel + 1) {
                return false; // Skipped a level (e.g., H2 -> H4)
            }
            $lastLevel = $level;
        }

        return true;
    }
}
