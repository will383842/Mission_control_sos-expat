<?php

namespace App\Services\Quality;

use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Services\AI\OpenAiService;
use App\Services\Content\SeoChecklistService;
use App\Services\Seo\InternalLinkingService;
use App\Services\Seo\JsonLdService;
use Illuminate\Support\Facades\Log;

/**
 * Iterative quality improvement engine — runs SEO checklist,
 * identifies failures, and auto-fixes them in successive passes.
 */
class AutoQualityImproverService
{
    private SeoChecklistService $seoChecklist;
    private OpenAiService $openAi;
    private ToneAnalyzerService $toneAnalyzer;
    private BrandComplianceService $brandCompliance;
    private InternalLinkingService $internalLinking;
    private JsonLdService $jsonLd;

    public function __construct(
        SeoChecklistService $seoChecklist,
        OpenAiService $openAi,
        ToneAnalyzerService $toneAnalyzer,
        BrandComplianceService $brandCompliance,
        InternalLinkingService $internalLinking,
        JsonLdService $jsonLd,
    ) {
        $this->seoChecklist = $seoChecklist;
        $this->openAi = $openAi;
        $this->toneAnalyzer = $toneAnalyzer;
        $this->brandCompliance = $brandCompliance;
        $this->internalLinking = $internalLinking;
        $this->jsonLd = $jsonLd;
    }

    /**
     * Run iterative improvement passes on an article until targetScore is reached.
     */
    public function improve(GeneratedArticle $article, int $targetScore = 85, int $maxPasses = 3): array
    {
        try {
            // Initial evaluation
            $checklist = $this->seoChecklist->evaluate($article);
            $initialScore = $checklist->overall_checklist_score ?? 0;

            Log::info('Auto-quality improvement started', [
                'article_id'    => $article->id,
                'initial_score' => $initialScore,
                'target_score'  => $targetScore,
            ]);

            if ($initialScore >= $targetScore) {
                return [
                    'initial_score'    => $initialScore,
                    'final_score'      => $initialScore,
                    'passes'           => 0,
                    'improvements'     => [],
                    'remaining_issues' => [],
                ];
            }

            $improvements = [];
            $currentScore = $initialScore;
            $passCount = 0;

            for ($pass = 1; $pass <= $maxPasses; $pass++) {
                $passCount = $pass;

                // Get failed checks sorted by weight/impact
                $failedChecks = $this->getFailedChecks($checklist, $pass);

                if (empty($failedChecks)) {
                    break;
                }

                // Fix each issue
                foreach ($failedChecks as $check) {
                    $scoreBefore = $currentScore;

                    $fixed = $this->fixIssue($article, $check);

                    if ($fixed) {
                        // Refresh article from DB
                        $article->refresh();

                        // Re-evaluate to get new score
                        $checklist = $this->seoChecklist->evaluate($article);
                        $currentScore = $checklist->overall_checklist_score ?? $currentScore;

                        $improvements[] = [
                            'pass'         => $pass,
                            'type'         => $check['type'],
                            'description'  => $check['description'],
                            'score_before' => $scoreBefore,
                            'score_after'  => $currentScore,
                        ];
                    }
                }

                if ($currentScore >= $targetScore) {
                    break;
                }
            }

            // Final remaining issues
            $finalChecklist = $this->seoChecklist->evaluate($article);
            $finalScore = $finalChecklist->overall_score ?? $currentScore;
            $remainingIssues = $this->getRemainingIssueDescriptions($finalChecklist);

            Log::info('Auto-quality improvement complete', [
                'article_id'  => $article->id,
                'initial'     => $initialScore,
                'final'       => $finalScore,
                'passes'      => $passCount,
                'fixes_count' => count($improvements),
            ]);

            return [
                'initial_score'    => $initialScore,
                'final_score'      => $finalScore,
                'passes'           => $passCount,
                'improvements'     => $improvements,
                'remaining_issues' => $remainingIssues,
            ];
        } catch (\Throwable $e) {
            Log::error('Auto-quality improvement failed', [
                'article_id' => $article->id ?? null,
                'message'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ============================================================
    // Fix methods
    // ============================================================

    /**
     * Dispatch a fix based on the check type.
     */
    private function fixIssue(GeneratedArticle $article, array $check): bool
    {
        try {
            return match ($check['type']) {
                'missing_faqs'              => $this->fixMissingFaqs($article),
                'keyword_density'           => $this->fixKeywordDensity($article),
                'heading_hierarchy'         => $this->fixHeadingHierarchy($article),
                'missing_definition'        => $this->fixMissingDefinitionParagraph($article),
                'missing_schema'            => $this->fixMissingSchema($article),
                'missing_internal_links'    => $this->fixMissingInternalLinks($article),
                'tone_issues'               => $this->fixToneIssues($article),
                'brand_violations'          => $this->fixBrandViolations($article),
                'missing_eeat'              => $this->fixMissingEeat($article),
                default                     => false,
            };
        } catch (\Throwable $e) {
            Log::warning('Fix failed for check type', [
                'type'    => $check['type'],
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate additional FAQs to reach target count.
     */
    private function fixMissingFaqs(GeneratedArticle $article, int $targetCount = 8): bool
    {
        $currentCount = $article->faqs()->count();

        if ($currentCount >= $targetCount) {
            return false;
        }

        $needed = $targetCount - $currentCount;
        $language = $article->language ?? 'fr';

        $prompt = "Génère {$needed} questions-réponses FAQ en {$language} basées sur cet article. "
            . "Chaque FAQ doit être pertinente pour le SEO.\n\n"
            . "Titre: {$article->title}\n"
            . "Sujet: {$article->keywords_primary}\n"
            . "Pays: {$article->country}\n\n"
            . "Format JSON strict: [{\"question\": \"...\", \"answer\": \"...\"}]";

        $result = $this->openAi->complete(
            'Tu es un expert SEO. Génère des FAQ pertinentes au format JSON strict.',
            $prompt,
            ['model' => 'gpt-4o-mini']
        );

        if (!($result['success'] ?? false)) {
            return false;
        }

        $faqs = $this->parseJsonFromResponse($result['content'] ?? '');

        if (empty($faqs)) {
            return false;
        }

        $maxSort = $article->faqs()->max('sort_order') ?? 0;

        foreach ($faqs as $idx => $faq) {
            if (!isset($faq['question'], $faq['answer'])) {
                continue;
            }

            GeneratedArticleFaq::create([
                'article_id' => $article->id,
                'question'   => $faq['question'],
                'answer'     => $faq['answer'],
                'sort_order' => $maxSort + $idx + 1,
            ]);
        }

        return true;
    }

    /**
     * Adjust keyword density (too low: weave in more; too high: replace with synonyms).
     */
    private function fixKeywordDensity(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';
        $keyword = $article->keywords_primary ?? '';

        if (empty($html) || empty($keyword)) {
            return false;
        }

        $text = strip_tags($html);
        $wordCount = str_word_count($text);
        $keywordCount = mb_substr_count(mb_strtolower($text), mb_strtolower($keyword));
        $density = ($wordCount > 0) ? ($keywordCount / $wordCount) * 100 : 0;

        if ($density >= 1.0 && $density <= 2.5) {
            return false; // Already in range
        }

        $action = ($density < 1.0) ? 'increase' : 'decrease';
        $language = $article->language ?? 'fr';

        $prompt = ($action === 'increase')
            ? "Le mot-clé \"{$keyword}\" n'apparaît que {$keywordCount} fois (densité: " . round($density, 2) . "%). "
              . "Ajoute naturellement le mot-clé dans 2-3 paragraphes du HTML suivant, sans sur-optimiser. "
              . "Retourne UNIQUEMENT le HTML modifié, sans explication.\n\n{$html}"
            : "Le mot-clé \"{$keyword}\" apparaît {$keywordCount} fois (densité: " . round($density, 2) . "%). "
              . "Remplace quelques occurrences par des synonymes ou variations (LSI) en {$language}. "
              . "Retourne UNIQUEMENT le HTML modifié, sans explication.\n\n{$html}";

        $result = $this->openAi->complete(
            'Tu es un expert SEO. Ajuste la densité du mot-clé dans le HTML fourni.',
            $prompt,
            ['model' => 'gpt-4o-mini']
        );

        if (!($result['success'] ?? false) || empty($result['content'])) {
            return false;
        }

        $newHtml = $this->cleanHtmlResponse($result['content']);

        if (!empty($newHtml) && mb_strlen($newHtml) > 100) {
            $article->update(['content_html' => $newHtml]);
            return true;
        }

        return false;
    }

    /**
     * Fix heading hierarchy issues (missing H1, H3 before H2, level skips).
     */
    private function fixHeadingHierarchy(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';

        if (empty($html)) {
            return false;
        }

        $modified = false;

        // Fix missing H1: promote first H2 to H1
        if (!preg_match('/<h1[^>]*>/i', $html)) {
            $html = preg_replace('/<h2([^>]*)>(.*?)<\/h2>/is', '<h1$1>$2</h1>', $html, 1);
            $modified = true;
        }

        // Fix H3 appearing before any H2: promote to H2
        $firstH2Pos = stripos($html, '<h2');
        $firstH3Pos = stripos($html, '<h3');

        if ($firstH3Pos !== false && ($firstH2Pos === false || $firstH3Pos < $firstH2Pos)) {
            $html = preg_replace('/<h3([^>]*)>(.*?)<\/h3>/is', '<h2$1>$2</h2>', $html, 1);
            $modified = true;
        }

        if ($modified) {
            $article->update(['content_html' => $html]);
        }

        return $modified;
    }

    /**
     * Ensure first paragraph after first H2 is a 40-60 word definition (featured snippet format).
     */
    private function fixMissingDefinitionParagraph(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';
        $keyword = $article->keywords_primary ?? '';

        if (empty($html) || empty($keyword)) {
            return false;
        }

        // Check if first paragraph after first H2 is already 40-60 words
        if (preg_match('/<\/h2>\s*<p[^>]*>(.*?)<\/p>/is', $html, $match)) {
            $firstPText = strip_tags($match[1]);
            $wordCount = str_word_count($firstPText);

            if ($wordCount >= 35 && $wordCount <= 70) {
                return false; // Already good
            }
        }

        $language = $article->language ?? 'fr';

        $prompt = "Écris un paragraphe de définition de 40 à 60 mots en {$language} "
            . "pour le sujet \"{$keyword}\" dans le contexte du pays {$article->country}. "
            . "Ce paragraphe doit commencer par une reformulation du sujet. "
            . "Il sera utilisé comme featured snippet Google (Position 0). Année courante: " . date('Y') . ". "
            . "Retourne UNIQUEMENT le texte du paragraphe, sans balises HTML.";

        $result = $this->openAi->complete(
            'Tu es un expert SEO. Écris un paragraphe de définition concis de 40-60 mots.',
            $prompt,
            ['model' => 'gpt-4o-mini']
        );

        if (!($result['success'] ?? false) || empty($result['content'])) {
            return false;
        }

        $snippet = trim(strip_tags($result['content']));

        // Validate word count — regenerate if outside 35-70 range
        $snippetWordCount = str_word_count($snippet);
        if ($snippetWordCount > 70) {
            $words = explode(' ', $snippet);
            $snippet = implode(' ', array_slice($words, 0, 60));
            $lastDot = strrpos($snippet, '.');
            if ($lastDot !== false && $lastDot > strlen($snippet) * 0.6) {
                $snippet = substr($snippet, 0, $lastDot + 1);
            }
        } elseif ($snippetWordCount < 30) {
            // Too short — retry with explicit instruction
            $retryResult = $this->openAi->complete(
                "Génère une définition de EXACTEMENT 40-60 mots. Reformule le sujet dans la réponse.",
                "Sujet: {$keyword}\nPays: {$article->country}\nTexte actuel trop court: {$snippet}\n\nRégénère en 40-60 mots:",
                ['model' => 'gpt-4o-mini', 'temperature' => 0.5, 'max_tokens' => 150]
            );
            if ($retryResult['success'] ?? false) {
                $snippet = trim(strip_tags($retryResult['content']));
            }
        }

        $definition = '<p class="featured-snippet"><strong>' . e($snippet) . '</strong></p>';

        // Insert after first <h2>
        $pos = strpos($html, '</h2>');
        if ($pos !== false) {
            $html = substr($html, 0, $pos + 5) . "\n" . $definition . "\n" . substr($html, $pos + 5);
            $article->update(['content_html' => $html]);
            return true;
        }

        // No H2 found, insert at beginning
        $html = $definition . "\n" . $html;
        $article->update(['content_html' => $html]);
        return true;
    }

    /**
     * Generate missing JSON-LD schemas.
     */
    private function fixMissingSchema(GeneratedArticle $article): bool
    {
        $jsonLd = $article->json_ld ?? [];

        if (empty($jsonLd)) {
            // Generate full schema set
            $jsonLdString = json_encode($jsonLd);
        } else {
            $jsonLdString = json_encode($jsonLd);
        }

        $hasArticle = mb_strpos($jsonLdString, '"Article"') !== false
            || mb_strpos($jsonLdString, '"BlogPosting"') !== false;
        $hasFaq = mb_strpos($jsonLdString, '"FAQPage"') !== false;
        $hasBreadcrumb = mb_strpos($jsonLdString, '"BreadcrumbList"') !== false;

        if ($hasArticle && $hasFaq && $hasBreadcrumb) {
            return false; // All present
        }

        // Build schema graph
        $graph = isset($jsonLd['@graph']) ? $jsonLd['@graph'] : (empty($jsonLd) ? [] : [$jsonLd]);

        if (!$hasArticle) {
            $graph[] = [
                '@type'         => 'Article',
                'headline'      => $article->meta_title ?? $article->title,
                'description'   => $article->meta_description ?? '',
                'datePublished' => ($article->published_at ?? $article->created_at)?->toIso8601String(),
                'dateModified'  => $article->updated_at?->toIso8601String(),
                'author'        => [
                    '@type' => 'Organization',
                    'name'  => 'SOS-Expat',
                    'url'   => config('app.url', 'https://sos-expat.com'),
                ],
                'image'         => $article->featured_image_url ?? '',
                'inLanguage'    => $article->language ?? 'fr',
            ];
        }

        if (!$hasFaq && $article->faqs()->count() > 0) {
            $faqEntities = [];
            foreach ($article->faqs as $faq) {
                $faqEntities[] = [
                    '@type'          => 'Question',
                    'name'           => $faq->question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $faq->answer,
                    ],
                ];
            }
            $graph[] = [
                '@type'      => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        if (!$hasBreadcrumb) {
            $graph[] = [
                '@type'           => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type'    => 'ListItem',
                        'position' => 1,
                        'name'     => 'Accueil',
                        'item'     => config('app.url', 'https://sos-expat.com'),
                    ],
                    [
                        '@type'    => 'ListItem',
                        'position' => 2,
                        'name'     => $article->title,
                        'item'     => $article->canonical_url ?? '',
                    ],
                ],
            ];
        }

        $newJsonLd = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        $article->update(['json_ld' => $newJsonLd]);

        return true;
    }

    /**
     * Suggest and inject additional internal links.
     */
    private function fixMissingInternalLinks(GeneratedArticle $article): bool
    {
        $currentCount = $article->internalLinksOut()->count();

        if ($currentCount >= 5) {
            return false;
        }

        $suggestions = $this->internalLinking->suggestLinks($article, 5 - $currentCount);

        if (empty($suggestions)) {
            return false;
        }

        // The InternalLinkingService already handles injection
        $this->internalLinking->injectLinks($article, $suggestions);

        return true;
    }

    /**
     * Fix tone issues detected by ToneAnalyzerService.
     */
    private function fixToneIssues(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';

        if (empty($html)) {
            return false;
        }

        $toneResult = $this->toneAnalyzer->analyze($html);

        if ($toneResult['is_brand_compliant']) {
            return false;
        }

        $issues = [];
        if ($toneResult['formality'] < 55) {
            $issues[] = 'trop familier — reformuler en vouvoiement professionnel';
        }
        if ($toneResult['emotion'] > 45) {
            $issues[] = 'trop émotionnel — réduire les exclamations et mots sensationnels';
        }
        if ($toneResult['sentiment'] < 0.1) {
            $issues[] = 'trop négatif — ajouter des éléments positifs et rassurants';
        }

        if (empty($issues)) {
            return false;
        }

        $language = $article->language ?? 'fr';
        $issuesList = implode('; ', $issues);

        $prompt = "Corrige le ton de ce contenu HTML en {$language}. "
            . "Problèmes détectés: {$issuesList}. "
            . "Garde le même contenu et la structure HTML, ajuste uniquement le ton. "
            . "Retourne UNIQUEMENT le HTML modifié.\n\n{$html}";

        $result = $this->openAi->complete(
            'Tu es un rédacteur professionnel SOS-Expat. Ajuste le ton du contenu.',
            $prompt,
            ['model' => 'gpt-4o-mini']
        );

        if (!($result['success'] ?? false) || empty($result['content'])) {
            return false;
        }

        $newHtml = $this->cleanHtmlResponse($result['content']);

        if (!empty($newHtml) && mb_strlen($newHtml) > 100) {
            $article->update(['content_html' => $newHtml]);
            return true;
        }

        return false;
    }

    /**
     * Fix brand violations (tutoiement, caps, multiple exclamations, non-whitelisted emojis).
     */
    private function fixBrandViolations(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';

        if (empty($html)) {
            return false;
        }

        $brandResult = $this->brandCompliance->check($html);

        if ($brandResult['is_compliant'] && $brandResult['score'] >= 80) {
            return false;
        }

        $modified = false;

        // Fix multiple exclamations: !! or !!! → !
        if (preg_match('/!{2,}/', $html)) {
            $html = preg_replace('/!{2,}/', '!', $html);
            $modified = true;
        }

        // Fix non-whitelisted emojis: remove them
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        $whitelist = ['✅', '❌', '⚠️', 'ℹ️', '📌', '📋', '💡'];

        if (preg_match_all($emojiPattern, $html, $matches)) {
            foreach ($matches[0] as $emoji) {
                if (!in_array($emoji, $whitelist)) {
                    $html = str_replace($emoji, '', $html);
                    $modified = true;
                }
            }
        }

        // Fix CAPS abuse (4+ char non-acronym words)
        $knownAcronyms = [
            'UE', 'USA', 'UK', 'OFII', 'VFS', 'TLS', 'ANTS', 'CPAM', 'RSI',
            'TVA', 'SCI', 'SARL', 'SAS', 'EURL', 'PDF', 'FAQ', 'SEO', 'API',
            'HTML', 'CSS', 'RGPD', 'DELF', 'DALF', 'VISA', 'CNIL', 'INSEE',
        ];

        $html = preg_replace_callback('/\b([A-ZÀ-Ü]{4,})\b/u', function (array $m) use ($knownAcronyms) {
            if (in_array($m[1], $knownAcronyms)) {
                return $m[1];
            }
            return mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8');
        }, $html);

        // If there were CAPS changes
        if ($html !== ($article->content_html ?? '')) {
            $modified = true;
        }

        if ($modified) {
            $article->update(['content_html' => $html]);
        }

        return $modified;
    }

    /**
     * Add missing E-E-A-T signals (author box, sources section, dates).
     */
    private function fixMissingEeat(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';

        if (empty($html)) {
            return false;
        }

        $modified = false;
        $language = $article->language ?? 'fr';

        // Check for sources section
        $hasSourcesSection = (bool) preg_match('/(sources|références|bibliographie)/i', $html);

        if (!$hasSourcesSection && $article->sources()->count() > 0) {
            $sourcesHtml = $language === 'fr' ? '<h2>Sources et références</h2><ul>' : '<h2>Sources and references</h2><ul>';
            foreach ($article->sources as $source) {
                $url = $source->url ?? '#';
                $title = $source->title ?? $source->url ?? 'Source';
                $sourcesHtml .= "<li><a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$title}</a></li>";
            }
            $sourcesHtml .= '</ul>';

            $html .= "\n" . $sourcesHtml;
            $modified = true;
        }

        // Check for last updated date
        $hasDateMention = (bool) preg_match('/(mis à jour|dernière mise à jour|last updated|updated on)/i', $html);

        if (!$hasDateMention) {
            $date = ($article->updated_at ?? $article->created_at)?->format('d/m/Y') ?? date('d/m/Y');
            $dateLabel = ($language === 'fr') ? 'Dernière mise à jour' : 'Last updated';
            $dateHtml = "<p><em>{$dateLabel}: {$date}</em></p>";

            // Insert before first heading or at the start
            if (preg_match('/<h[1-2][^>]*>/i', $html)) {
                $html = preg_replace('/(<h[1-2][^>]*>)/i', $dateHtml . "\n" . '$1', $html, 1);
            } else {
                $html = $dateHtml . "\n" . $html;
            }
            $modified = true;
        }

        // Check for author attribution
        $hasAuthor = (bool) preg_match('/(auteur|author|rédigé par|written by|par l\'équipe)/i', $html);

        if (!$hasAuthor) {
            $authorLabel = ($language === 'fr')
                ? '<p><em>Rédigé par l\'équipe éditoriale SOS-Expat</em></p>'
                : '<p><em>Written by the SOS-Expat editorial team</em></p>';

            $html .= "\n" . $authorLabel;
            $modified = true;
        }

        if ($modified) {
            $article->update(['content_html' => $html]);
        }

        return $modified;
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Extract failed checks from a SeoChecklist record, sorted by priority.
     */
    private function getFailedChecks($checklist, int $currentPass = 1): array
    {
        $failedChecks = [];

        // Map checklist boolean fields to fix types with priority weights
        $checkMapping = [
            'heading_hierarchy'      => ['field' => 'heading_hierarchy_valid', 'type' => 'heading_hierarchy', 'weight' => 8, 'description' => 'Hiérarchie des titres invalide'],
            'keyword_density'        => ['field' => 'keyword_density_ok', 'type' => 'keyword_density', 'weight' => 7, 'description' => 'Densité du mot-clé hors plage'],
            'has_faq_schema'         => ['field' => 'has_faq_schema', 'type' => 'missing_faqs', 'weight' => 6, 'description' => 'Schema FAQ manquant'],
            'has_article_schema'     => ['field' => 'has_article_schema', 'type' => 'missing_schema', 'weight' => 6, 'description' => 'Schema Article manquant'],
            'keyword_first_para'     => ['field' => 'keyword_in_first_paragraph', 'type' => 'missing_definition', 'weight' => 5, 'description' => 'Mot-clé absent du premier paragraphe'],
            'internal_links'         => ['field' => 'internal_links_count', 'type' => 'missing_internal_links', 'weight' => 4, 'description' => 'Liens internes insuffisants', 'threshold' => 3],
            'brand_compliance'       => ['type' => 'brand_violations', 'weight' => 3, 'description' => 'Violations de la charte de marque', 'first_pass_only' => true],
            'tone'                   => ['type' => 'tone_issues', 'weight' => 2, 'description' => 'Ton inadapté à la marque', 'first_pass_only' => true],
            'eeat'                   => ['type' => 'missing_eeat', 'weight' => 1, 'description' => 'Signaux E-E-A-T manquants'],
        ];

        foreach ($checkMapping as $key => $config) {
            // For checks with a field on the checklist model
            if (isset($config['field'])) {
                // Numeric threshold check (e.g. internal_links_count < 3)
                if (isset($config['threshold'])) {
                    $value = $checklist->{$config['field']} ?? 0;
                    if ($value < $config['threshold']) {
                        $failedChecks[] = [
                            'type'        => $config['type'],
                            'weight'      => $config['weight'],
                            'description' => $config['description'],
                        ];
                    }
                } else {
                    $value = $checklist->{$config['field']} ?? null;
                    if ($value === false || $value === 0 || $value === null) {
                        $failedChecks[] = [
                            'type'        => $config['type'],
                            'weight'      => $config['weight'],
                            'description' => $config['description'],
                        ];
                    }
                }
            } else {
                // Brand/tone checks only on first pass to avoid infinite loops
                if (!empty($config['first_pass_only']) && $currentPass > 1) {
                    continue;
                }
                $failedChecks[] = [
                    'type'        => $config['type'],
                    'weight'      => $config['weight'],
                    'description' => $config['description'],
                ];
            }
        }

        // Sort by weight descending (highest impact first)
        usort($failedChecks, fn (array $a, array $b) => $b['weight'] <=> $a['weight']);

        return $failedChecks;
    }

    /**
     * Get remaining issue descriptions from the final checklist.
     */
    private function getRemainingIssueDescriptions($checklist): array
    {
        $issues = [];
        $data = $checklist->toArray();

        // Check boolean fields that are still false
        $booleanChecks = [
            'has_single_h1'             => 'H1 manquant ou multiple',
            'heading_hierarchy_valid'   => 'Hiérarchie des titres invalide',
            'keyword_density_ok'        => 'Densité mot-clé hors plage',
            'keyword_in_first_paragraph' => 'Mot-clé absent du premier paragraphe',
            'has_table_or_list'         => 'Pas de tableau ou liste',
            'has_article_schema'        => 'Schema Article manquant',
            'has_faq_schema'            => 'Schema FAQ manquant',
            'has_breadcrumb_schema'     => 'Schema BreadcrumbList manquant',
        ];

        foreach ($booleanChecks as $field => $description) {
            if (isset($data[$field]) && !$data[$field]) {
                $issues[] = $description;
            }
        }

        // Numeric threshold checks
        if (isset($data['internal_links_count']) && $data['internal_links_count'] < 3) {
            $issues[] = 'Liens internes insuffisants';
        }
        if (isset($data['external_links_count']) && $data['external_links_count'] < 2) {
            $issues[] = 'Liens externes insuffisants';
        }

        return $issues;
    }

    /**
     * Parse JSON from an AI response (handles markdown code blocks).
     */
    private function parseJsonFromResponse(string $content): array
    {
        // Strip markdown code blocks
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Clean an HTML response from AI (remove markdown wrappers).
     */
    private function cleanHtmlResponse(string $content): string
    {
        $content = preg_replace('/```html\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);

        return trim($content);
    }
}
