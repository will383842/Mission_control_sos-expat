<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\GeneratedArticleFaq;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4b — Targeted post-quality-check article improvement.
 *
 * When QualityGuardService::check() returns score < 60, this service applies
 * focused, cheap GPT-4o-mini fixes ONLY on the dimensions that failed, then
 * the caller (GenerateArticleJob) re-runs the quality check. The goal is to
 * push borderline articles over the publication threshold instead of letting
 * them rot in 'review' status forever.
 *
 * Each improve() call costs ~$0.002-0.005 (gpt-4o-mini, ~3000 tokens total).
 * The caller should cap the loop at 2 attempts per article to avoid runaway
 * cost on irredeemable content.
 *
 * Auto-fixable checks (11 of 12):
 *   1. word_count ............ expandContent()
 *   2. h2_structure .......... addH2Structure() (via expandContent)
 *   3. featured_snippet ...... improveFeaturedSnippet()
 *   4. internal_links ........ injectInternalLinks()
 *   5. faq_count ............. generateMissingFaqs()
 *   6. eeat .................. addEEATSignals()
 *   8. aeo (ai_summary) ...... regenerateAiSummary()
 *   9. meta_title/desc ....... regenerateMetaTags()
 *  10. natural_writing ....... rewriteAiPhrases()
 *  11. brand ................. fixBrandCompliance()
 *
 * NOT auto-fixable:
 *   7. anti_cannibalization (would require changing the topic entirely)
 *
 * @see QualityGuardService::check()
 */
class ArticleImprovementService
{
    /** Model used for all improvement passes — chosen for cost over quality. */
    private const MODEL = 'gpt-4o-mini';

    /** Lower temperature → more deterministic rewrites, less drift from original. */
    private const TEMPERATURE = 0.4;

    public function __construct(
        private readonly OpenAiService $openai,
        private readonly QualityGuardService $qualityGuard,
    ) {}

    /**
     * Apply targeted improvements based on the issues/warnings reported by
     * QualityGuardService, then re-run the quality check.
     *
     * @param  GeneratedArticle $article
     * @param  array{score:int,issues:array,warnings:array,checks:array} $qualityResult
     * @return array{score:int,issues:array,warnings:array,improvements_applied:array}
     */
    public function improve(GeneratedArticle $article, array $qualityResult): array
    {
        $beforeScore = $qualityResult['score'] ?? 0;
        $issues = $qualityResult['issues'] ?? [];
        $warnings = $qualityResult['warnings'] ?? [];
        $applied = [];

        Log::info('ArticleImprovementService: improvement pass start', [
            'article_id' => $article->id,
            'before_score' => $beforeScore,
            'issues_count' => count($issues),
            'warnings_count' => count($warnings),
        ]);

        // Skip uncorrectable issues — abort fast on cannibalization
        foreach ($issues as $issue) {
            if (str_contains($issue, 'Cannibalisation')) {
                Log::warning('ArticleImprovementService: cannibalization is not auto-fixable, skipping improvement', [
                    'article_id' => $article->id,
                ]);
                return [
                    'score' => $beforeScore,
                    'issues' => $issues,
                    'warnings' => $warnings,
                    'improvements_applied' => [],
                    'aborted' => 'cannibalization',
                ];
            }
        }

        // ── Apply targeted fixes based on issue/warning content ──

        // 1. Word count too low → expand content
        if ($this->matchesAny($issues, ['Contenu trop court', 'too short'])) {
            try {
                $this->expandContent($article);
                $applied[] = 'expand_content';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: expandContent failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 2. AI-sounding patterns → rewrite naturally
        if ($this->matchesAny($issues, ['Contenu IA détecté', 'formules robotiques'])) {
            try {
                $this->rewriteAiPhrases($article);
                $applied[] = 'rewrite_ai_phrases';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: rewriteAiPhrases failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 3. H2 structure → add headings
        if ($this->matchesAny($issues, ['Structure H2 insuffisante'])) {
            try {
                $this->addH2Structure($article);
                $applied[] = 'add_h2_structure';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: addH2Structure failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 4. FAQs missing → generate missing ones
        if ($this->matchesAny($warnings, ['FAQ insuffisantes', 'FAQ insufficientes'])) {
            try {
                $this->generateMissingFaqs($article);
                $applied[] = 'generate_faqs';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: generateMissingFaqs failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 5. Internal links missing → inject from related articles
        if ($this->matchesAny($warnings, ['Maillage interne faible'])) {
            try {
                $this->injectInternalLinks($article);
                $applied[] = 'inject_internal_links';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: injectInternalLinks failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 6. Featured snippet → rewrite first paragraph
        if ($this->matchesAny($warnings, ['Featured snippet non optimal'])) {
            try {
                $this->improveFeaturedSnippet($article);
                $applied[] = 'improve_featured_snippet';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: improveFeaturedSnippet failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 7. AI summary → regenerate ≤160 chars
        if ($this->matchesAny($warnings, ['AEO: ai_summary'])) {
            try {
                $this->regenerateAiSummary($article);
                $applied[] = 'regenerate_ai_summary';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: regenerateAiSummary failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 8. Meta title/description → regenerate within length bounds
        if ($this->matchesAny($warnings, ['Meta title:', 'Meta description:'])) {
            try {
                $this->regenerateMetaTags($article);
                $applied[] = 'regenerate_meta_tags';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: regenerateMetaTags failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 9. E-E-A-T signals → add date/author/source mentions
        if ($this->matchesAny($warnings, ['E-E-A-T:'])) {
            try {
                $this->addEEATSignals($article);
                $applied[] = 'add_eeat_signals';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: addEEATSignals failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // 10. Brand compliance → strip forbidden terms
        if ($this->matchesAny($issues, ['Brand compliance', 'SOS Expat sans tiret', 'recruter', 'recrutement', 'salarié', 'MLM'])) {
            try {
                $this->fixBrandCompliance($article);
                $applied[] = 'fix_brand_compliance';
            } catch (\Throwable $e) {
                Log::warning('ArticleImprovementService: fixBrandCompliance failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }
        }

        // ── Re-run quality check on the improved article ──
        $article->refresh();
        $newQualityResult = $this->qualityGuard->check($article);

        Log::info('ArticleImprovementService: improvement pass done', [
            'article_id' => $article->id,
            'before_score' => $beforeScore,
            'after_score' => $newQualityResult['score'] ?? 0,
            'improvements_applied' => $applied,
        ]);

        return [
            'score' => $newQualityResult['score'] ?? 0,
            'issues' => $newQualityResult['issues'] ?? [],
            'warnings' => $newQualityResult['warnings'] ?? [],
            'checks' => $newQualityResult['checks'] ?? [],
            'improvements_applied' => $applied,
        ];
    }

    // ============================================================
    // INDIVIDUAL IMPROVEMENT METHODS
    // ============================================================

    /**
     * Expand article content by adding 1-2 new sections to reach the target
     * minimum word count for its content_type.
     */
    private function expandContent(GeneratedArticle $article): void
    {
        $currentHtml = $article->content_html ?? '';
        if (empty($currentHtml)) return;

        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);
        $title = $article->title;
        $country = $article->country ?? '';

        $system = "You are an expert content writer who expands existing articles by adding ONE new <h2> section that fits naturally with the existing content. The new section must:
- Be written in {$langName}
- Match the existing tone and style EXACTLY
- Cover an angle NOT already in the article
- Use 250-400 words inside the section
- Avoid AI-sounding phrases ('it is important to', 'in conclusion', 'as we mentioned')
- Output ONLY the new <h2>...</h2> + <p>...</p> HTML — no introduction, no explanation
- Use natural, journalist-style writing";

        $existingText = mb_substr(strip_tags($currentHtml), 0, 3000);
        $user = "Article title: \"{$title}\"\nCountry: {$country}\n\nExisting article (excerpt):\n{$existingText}\n\nGenerate ONE new <h2> section that adds value without repeating what's already covered.";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 1500,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $newSection = trim($result['content']);
        // Strip any markdown code fences
        $newSection = preg_replace('/^```html\s*/', '', $newSection);
        $newSection = preg_replace('/```\s*$/', '', $newSection);

        // Append before the FAQ/conclusion if present, else at the end
        $html = $currentHtml;
        if (preg_match('/<h2[^>]*>\s*(FAQ|Questions|Q\/R|Q&A|Conclusion)[^<]*<\/h2>/iu', $html, $m, PREG_OFFSET_CAPTURE)) {
            $insertPos = $m[0][1];
            $html = substr($html, 0, $insertPos) . "\n" . $newSection . "\n" . substr($html, $insertPos);
        } else {
            $html .= "\n" . $newSection;
        }

        $newWordCount = str_word_count(strip_tags($html));
        $article->update([
            'content_html' => $html,
            'word_count' => $newWordCount,
        ]);
    }

    /**
     * Rewrite paragraphs containing AI-sounding patterns into natural prose.
     */
    private function rewriteAiPhrases(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        if (empty($html)) return;

        // Same patterns as QualityGuardService::check()
        $aiPatterns = [
            'il est important de', 'il convient de', 'il est essentiel',
            'il est crucial', 'il est recommandé', 'il est recommande',
            'dans cet article', "n'hésitez pas", "n'hesitez pas",
            'en conclusion,', 'cela signifie que', 'il est à noter',
            'it is important to', 'it is essential', 'it is crucial',
            'in this article', 'in conclusion,',
        ];

        // Find paragraphs containing those patterns
        if (!preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) return;

        $offendingParagraphs = [];
        foreach ($matches as $m) {
            $paragraphText = mb_strtolower(strip_tags($m[1]));
            foreach ($aiPatterns as $pattern) {
                if (str_contains($paragraphText, $pattern)) {
                    $offendingParagraphs[] = $m[0]; // full <p>...</p>
                    break;
                }
            }
            if (count($offendingParagraphs) >= 5) break; // limit batch size
        }

        if (empty($offendingParagraphs)) return;

        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);

        $system = "You are an expert editor. Rewrite the given HTML paragraphs in {$langName} to remove robotic AI phrases (e.g. 'it is important to', 'in conclusion', 'we must note that') and replace them with natural, journalistic phrasing. Keep the same meaning, length, and HTML structure. Output the rewritten paragraphs in the SAME ORDER, separated by a single newline. No explanation.";

        $user = "Rewrite these " . count($offendingParagraphs) . " paragraphs:\n\n" . implode("\n", $offendingParagraphs);

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 2000,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        // Naive replacement: split rewritten content by <p> tags and replace one-by-one
        if (!preg_match_all('/<p[^>]*>.*?<\/p>/is', $result['content'], $rewrittenMatches)) return;

        $rewritten = $rewrittenMatches[0];
        if (count($rewritten) === 0) return;

        // Replace each offending paragraph with its rewritten version (in order)
        foreach ($offendingParagraphs as $i => $original) {
            if (!isset($rewritten[$i])) break;
            $html = $this->replaceFirst($html, $original, $rewritten[$i]);
        }

        $newWordCount = str_word_count(strip_tags($html));
        $article->update([
            'content_html' => $html,
            'word_count' => $newWordCount,
        ]);
    }

    /**
     * Add H2 sections if the article has fewer than 3.
     * Delegates to expandContent() since adding a new H2 section is exactly what we need.
     */
    private function addH2Structure(GeneratedArticle $article): void
    {
        $h2Count = preg_match_all('/<h2[^>]*>/i', $article->content_html ?? '');
        $needed = max(0, 3 - $h2Count);
        for ($i = 0; $i < $needed; $i++) {
            $this->expandContent($article);
            $article->refresh();
        }
    }

    /**
     * Generate missing FAQs (target: 3-5 total).
     */
    private function generateMissingFaqs(GeneratedArticle $article): void
    {
        $existingCount = $article->faqs()->count();
        $needed = max(0, 3 - $existingCount);
        if ($needed === 0) return;

        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);
        $title = $article->title;
        $excerpt = mb_substr(strip_tags($article->content_html ?? ''), 0, 1500);

        $system = "You are an expert content writer. Generate {$needed} frequently asked questions and answers in {$langName} for the given article. The questions must:
- Be REAL questions a user would search on Google
- Cover angles NOT in the article body
- Have answers of 60-120 words each, factual, no marketing fluff
- Be returned as JSON: {\"faqs\": [{\"question\": \"...\", \"answer\": \"...\"}, ...]}";

        $user = "Article title: \"{$title}\"\n\nArticle excerpt:\n{$excerpt}";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 1500,
            'json_mode' => true,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $data = json_decode($result['content'], true);
        if (!is_array($data) || !isset($data['faqs']) || !is_array($data['faqs'])) return;

        $sortStart = $existingCount + 1;
        foreach (array_slice($data['faqs'], 0, $needed) as $i => $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) continue;
            GeneratedArticleFaq::create([
                'article_id' => $article->id,
                'question' => mb_substr(trim($faq['question']), 0, 500),
                'answer' => mb_substr(trim($faq['answer']), 0, 2000),
                'sort_order' => $sortStart + $i,
            ]);
        }
    }

    /**
     * Inject internal links from existing related articles in the same language.
     * No AI call — pure SQL lookup + DOM injection.
     */
    private function injectInternalLinks(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        if (empty($html)) return;

        $existingLinks = preg_match_all('/href=["\']https?:\/\/(sos-expat\.com|blog\.life-expat\.com)/i', $html);
        $needed = max(0, 4 - $existingLinks);
        if ($needed === 0) return;

        // Find related published articles. Strict topical filter first:
        //   1) same language AND same country  ← preferred (Switzerland article links to other Swiss articles)
        //   2) if not enough, fall back to same language only (still a real internal link, just less topical)
        // Historical bug: a Nouvelle-Calédonie article was getting Swiss internal
        // links because we picked random same-language candidates with no country
        // filter. Always prefer same-country to keep links contextually relevant.
        $base = GeneratedArticle::where('language', $article->language)
            ->where('id', '!=', $article->id)
            ->where('status', 'published')
            ->whereNotNull('slug')
            ->whereNull('parent_article_id')
            ->where('word_count', '>', 0);

        $candidates = collect();
        if (!empty($article->country)) {
            $candidates = (clone $base)
                ->where('country', $article->country)
                ->inRandomOrder()
                ->limit($needed * 3)
                ->get(['id', 'title', 'slug', 'language', 'country']);
        }

        // Top up with same-language candidates only if same-country pool is too small
        if ($candidates->count() < $needed) {
            $extra = (clone $base)
                ->whereNotIn('id', $candidates->pluck('id')->all() ?: [0])
                ->inRandomOrder()
                ->limit($needed * 3)
                ->get(['id', 'title', 'slug', 'language', 'country']);
            $candidates = $candidates->concat($extra);
        }

        if ($candidates->isEmpty()) return;

        $injected = 0;
        $blogBase = rtrim((string) config('services.blog.url', 'https://sos-expat.com'), '/');

        // Pre-find all <p>...</p> blocks with their positions in the HTML.
        // We rebuild the HTML at the end so concurrent injections don't shift offsets.
        if (!preg_match_all('/<p[^>]*>.*?<\/p>/is', $html, $blockMatches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        // Track which paragraph offsets we've already touched to avoid double-linking
        // the same paragraph (would look unnatural).
        $touchedOffsets = [];

        foreach ($candidates as $candidate) {
            if ($injected >= $needed) break;
            // Already linked? skip
            if (!empty($candidate->slug) && str_contains($html, $candidate->slug)) continue;

            // Try to find a paragraph that mentions a noun from the candidate's title
            $titleWords = preg_split('/\s+/u', (string) ($candidate->title ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $longWords = array_values(array_filter($titleWords, fn($w) => mb_strlen($w) >= 5));
            if (empty($longWords)) continue;

            $anchor = $longWords[0];
            $href = "{$blogBase}/{$candidate->language}-" . strtolower($candidate->country ?? 'fr') . "/articles/{$candidate->slug}";
            $linkHtml = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') . '</a>';

            // Walk paragraphs and find the first one that:
            //  - hasn't already been touched by a previous injection
            //  - contains the anchor (case-insensitive, word boundary)
            //  - doesn't already contain an <a> tag (don't nest links)
            // Re-fetch blocks each iteration since $html may have changed.
            if (!preg_match_all('/<p[^>]*>.*?<\/p>/is', $html, $currentBlocks, PREG_OFFSET_CAPTURE)) {
                break;
            }

            foreach ($currentBlocks[0] as $blockMatch) {
                [$blockHtml, $offset] = $blockMatch;

                // Skip if we've already injected into this exact paragraph slot
                if (in_array($offset, $touchedOffsets, true)) continue;
                if (stripos($blockHtml, '<a ') !== false) continue;

                // Word-boundary, case-insensitive match
                $anchorPattern = '/\b(' . preg_quote($anchor, '/') . ')\b/iu';
                if (!preg_match($anchorPattern, $blockHtml)) continue;

                $newBlock = preg_replace($anchorPattern, $linkHtml, $blockHtml, 1);
                if ($newBlock === null || $newBlock === $blockHtml) continue;

                $html = substr_replace($html, $newBlock, $offset, strlen($blockHtml));
                $touchedOffsets[] = $offset;
                $injected++;
                break;
            }
        }

        if ($injected > 0) {
            $article->update(['content_html' => $html]);
        }
    }

    /**
     * Rewrite the first paragraph to be 35-65 words, optimized for featured snippet.
     */
    private function improveFeaturedSnippet(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        if (empty($html)) return;

        if (!preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) return;

        $firstParagraph = $m[0];
        $title = $article->title;
        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);

        $system = "You are an SEO expert specialized in Google featured snippets. Rewrite the given first paragraph in {$langName} to:
- Be exactly 35-65 words
- Directly answer the implicit question of the article title
- Be self-contained (a user reading only this should understand the topic)
- No AI phrases, no marketing fluff
- Output ONLY the new <p>...</p> HTML — nothing else.";

        $user = "Article title: \"{$title}\"\n\nCurrent first paragraph:\n{$firstParagraph}\n\nRewrite for featured snippet.";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 300,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $newParagraph = trim($result['content']);
        $newParagraph = preg_replace('/^```html\s*/', '', $newParagraph);
        $newParagraph = preg_replace('/```\s*$/', '', $newParagraph);

        // Validate it's a <p> tag
        if (!preg_match('/^<p[^>]*>.*<\/p>$/is', $newParagraph)) return;

        $html = $this->replaceFirst($html, $firstParagraph, $newParagraph);
        $article->update([
            'content_html' => $html,
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    /**
     * Regenerate ai_summary to be ≤ 160 characters and present.
     */
    private function regenerateAiSummary(GeneratedArticle $article): void
    {
        $title = $article->title;
        $excerpt = mb_substr(strip_tags($article->content_html ?? ''), 0, 800);
        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);

        $system = "You are an AEO (Answer Engine Optimization) expert. Generate a single sentence in {$langName} that summarizes the article for AI assistants (ChatGPT, Perplexity, Claude). Strict rules:
- MAXIMUM 160 characters (count carefully)
- Direct, factual, answers the implicit question of the title
- No marketing language
- No 'this article explains' meta-phrasing
- Output ONLY the sentence, no quotes, no explanation.";

        $user = "Title: \"{$title}\"\n\nArticle excerpt:\n{$excerpt}";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 100,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $summary = trim($result['content'], " \n\r\t\"'`");
        if (mb_strlen($summary) > 160) {
            $summary = mb_substr($summary, 0, 157) . '...';
        }

        $article->update(['ai_summary' => $summary]);
    }

    /**
     * Regenerate meta_title (30-65 chars) and meta_description (120-160 chars
     * with action verb).
     */
    private function regenerateMetaTags(GeneratedArticle $article): void
    {
        $title = $article->title;
        $excerpt = mb_substr(strip_tags($article->content_html ?? ''), 0, 800);
        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);
        $year = date('Y');

        $system = "You are an SEO expert. Generate optimized meta tags in {$langName} for the article. Output JSON only:
{
  \"meta_title\": \"30-65 characters, includes year {$year} if natural\",
  \"meta_description\": \"120-160 characters, MUST start with an action verb (Découvrez, Apprenez, Trouvez, Discover, Learn, Find, Descubra, Aprenda, Entdecken, Erfahren), describes the article value\"
}
Strictly enforce the character limits.";

        $user = "Article title: \"{$title}\"\n\nExcerpt:\n{$excerpt}";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 300,
            'json_mode' => true,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $data = json_decode($result['content'], true);
        if (!is_array($data)) return;

        $update = [];
        if (!empty($data['meta_title'])) {
            $mt = trim($data['meta_title']);
            if (mb_strlen($mt) > 65) $mt = mb_substr($mt, 0, 65);
            if (mb_strlen($mt) >= 30) $update['meta_title'] = $mt;
        }
        if (!empty($data['meta_description'])) {
            $md = trim($data['meta_description']);
            if (mb_strlen($md) > 160) $md = mb_substr($md, 0, 160);
            if (mb_strlen($md) >= 120) $update['meta_description'] = $md;
        }

        if (!empty($update)) {
            $article->update($update);
        }
    }

    /**
     * Append a short E-E-A-T paragraph mentioning the publication date,
     * a numeric data point, and a source attribution if missing.
     */
    private function addEEATSignals(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        if (empty($html)) return;

        $lang = $article->language ?? 'fr';
        $langName = $this->langName($lang);
        $year = date('Y');
        $title = $article->title;
        $excerpt = mb_substr(strip_tags($html), 0, 1500);

        $system = "You are an SEO E-E-A-T expert. Generate ONE final paragraph in {$langName} that adds the following missing signals:
- A specific date or year mention ({$year} or 'Mise à jour {$year}')
- At least one numeric data point (a percentage, an amount in EUR/USD, a count)
- A source attribution (e.g. 'Selon le ministère des Affaires étrangères', 'Source : OECD {$year}')
- 60-100 words
- Tone: factual, journalistic
- Output ONLY the <p>...</p> HTML, no explanation.";

        $user = "Article title: \"{$title}\"\n\nArticle excerpt:\n{$excerpt}";

        $result = $this->openai->complete($system, $user, [
            'model' => self::MODEL,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 400,
            'costable_type' => GeneratedArticle::class,
            'costable_id' => $article->id,
        ]);

        if (!$result['success'] || empty($result['content'])) return;

        $newParagraph = trim($result['content']);
        $newParagraph = preg_replace('/^```html\s*/', '', $newParagraph);
        $newParagraph = preg_replace('/```\s*$/', '', $newParagraph);

        if (!preg_match('/^<p[^>]*>.*<\/p>$/is', $newParagraph)) return;

        // Append at the end (or before FAQ section if present)
        if (preg_match('/<h2[^>]*>\s*(FAQ|Questions|Q\/R|Q&A)[^<]*<\/h2>/iu', $html, $m, PREG_OFFSET_CAPTURE)) {
            $insertPos = $m[0][1];
            $html = substr($html, 0, $insertPos) . "\n" . $newParagraph . "\n" . substr($html, $insertPos);
        } else {
            $html .= "\n" . $newParagraph;
        }

        $article->update([
            'content_html' => $html,
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    /**
     * Strip forbidden brand-compliance terms from the article body.
     * Pure regex (no AI call needed).
     */
    private function fixBrandCompliance(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        if (empty($html)) return;

        $replacements = [
            // SOS Expat → SOS-Expat
            '/SOS\s+Expat(?!\.\w)/u' => 'SOS-Expat',
            '/sos\s+expat(?!\.\w)/u' => 'SOS-Expat',
            // forbidden affiliate vocabulary
            '/\bMLM\b/i' => "programme d'affiliation",
            '/\brecrutement\b/iu' => 'parrainage',
            '/\brecruter\b/iu' => 'parrainer',
            '/\brecrutent\b/iu' => 'parrainent',
            '/\brecrute\b/iu' => 'parraine',
            '/\brecrutes\b/iu' => 'parraines',
            '/\bsalariés\b/iu' => 'affiliés',
            '/\bsalarié\b/iu' => 'affilié',
            '/\bsalarie\b/iu' => 'affilie',
        ];

        $newHtml = $html;
        foreach ($replacements as $pattern => $replacement) {
            $newHtml = preg_replace($pattern, $replacement, $newHtml);
        }

        // Also strip "free / gratuit" claims about SOS-Expat
        $newHtml = preg_replace('/(SOS-Expat[^.]{0,40})(gratuit|free|gratis)/iu', '$1', $newHtml);

        if ($newHtml !== $html) {
            $article->update([
                'content_html' => $newHtml,
                'word_count' => str_word_count(strip_tags($newHtml)),
            ]);
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Returns true if any of the needles appear (case-insensitive substring)
     * in any of the haystack strings.
     */
    private function matchesAny(array $haystack, array $needles): bool
    {
        foreach ($haystack as $entry) {
            $low = mb_strtolower($entry);
            foreach ($needles as $n) {
                if (str_contains($low, mb_strtolower($n))) return true;
            }
        }
        return false;
    }

    /**
     * Replace the first occurrence of $search in $subject. Safer than
     * str_replace which replaces all and could over-substitute.
     */
    private function replaceFirst(string $subject, string $search, string $replace): string
    {
        $pos = strpos($subject, $search);
        if ($pos === false) return $subject;
        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Map ISO language code to a full name for prompt clarity.
     */
    private function langName(string $code): string
    {
        return match (strtolower($code)) {
            'fr' => 'French',
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'zh' => 'Chinese (Simplified)',
            default => $code,
        };
    }
}
