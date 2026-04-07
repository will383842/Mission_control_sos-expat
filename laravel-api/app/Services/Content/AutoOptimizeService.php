<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\Log;

/**
 * Auto-Optimize Service — improves articles that don't meet quality thresholds.
 *
 * Analyzes weaknesses (SEO, AEO, structure, E-E-A-T) and rewrites ONLY
 * the weak parts — not the entire article.
 *
 * Flow:
 *   Score ≥ 80 → publish directly (status: 'published_direct')
 *   Score 50-79 → 1 optimization pass → publish (status: 'published_optimized')
 *   Score < 50 → 2 optimization passes → publish (status: 'published_double_optimized')
 *
 * ALL articles get published. None are blocked.
 */
class AutoOptimizeService
{
    private const THRESHOLD_DIRECT = 80;
    private const THRESHOLD_SINGLE = 50;

    public function __construct(
        private OpenAiService $openAi,
        private QualityGuardService $qualityGuard,
        private KnowledgeBaseService $knowledgeBase,
        private ContentOrchestratorService $orchestrator,
    ) {}

    /**
     * Evaluate and auto-optimize an article. Returns optimization result.
     *
     * @return array{action: string, original_score: int, final_score: int, passes: int, improvements: array}
     */
    public function evaluateAndOptimize(GeneratedArticle $article): array
    {
        // Score the article
        $report = $this->qualityGuard->check($article);
        $score = $report['score'];
        $issues = $report['issues'] ?? [];
        $warnings = $report['warnings'] ?? [];

        // Track result
        $result = [
            'action' => 'published_direct',
            'original_score' => $score,
            'final_score' => $score,
            'passes' => 0,
            'improvements' => [],
        ];

        if ($score >= self::THRESHOLD_DIRECT) {
            // Score ≥ 80 → publish directly
            $result['action'] = 'published_direct';
            Log::info("AutoOptimize: direct publish (score {$score})", ['article_id' => $article->id]);
            return $result;
        }

        // Score < 80 → optimize
        $passCount = $score < self::THRESHOLD_SINGLE ? 2 : 1;

        for ($pass = 1; $pass <= $passCount; $pass++) {
            Log::info("AutoOptimize: pass {$pass}/{$passCount} (score {$score})", ['article_id' => $article->id]);

            $improvements = $this->runOptimizationPass($article, $report);
            $result['improvements'] = array_merge($result['improvements'], $improvements);

            // Re-score after optimization
            $article->refresh();
            $report = $this->qualityGuard->check($article);
            $score = $report['score'];
            $result['final_score'] = $score;
            $result['passes'] = $pass;

            // If score is now good enough, stop
            if ($score >= self::THRESHOLD_DIRECT) {
                break;
            }
        }

        $result['action'] = $passCount === 1 ? 'published_optimized' : 'published_double_optimized';

        // Alert if final score is still low
        if ($score < self::THRESHOLD_SINGLE) {
            $this->orchestrator->sendTelegramAlert(
                "Article publie avec score bas ({$score}/100) apres {$passCount} passes\n"
                . "ID: {$article->id}\nTitre: {$article->title}\n"
                . "Problemes: " . implode(', ', array_slice($result['improvements'], 0, 3)),
                'warning'
            );
        }

        Log::info("AutoOptimize: {$result['action']} (score {$result['original_score']}→{$result['final_score']}, {$result['passes']} passes)", [
            'article_id' => $article->id,
        ]);

        return $result;
    }

    /**
     * Run one optimization pass — fix specific weaknesses.
     */
    private function runOptimizationPass(GeneratedArticle $article, array $report): array
    {
        $improvements = [];
        $checks = $report['checks'] ?? [];
        $contentType = $article->content_type ?? 'article';
        $language = $article->language ?? 'fr';

        // 1. Fix featured snippet if missing/weak
        if (!($checks['featured_snippet'] ?? true)) {
            $this->optimizeFeaturedSnippet($article);
            $improvements[] = 'featured_snippet';
        }

        // 2. Fix meta title if wrong length
        if (!($checks['meta_title'] ?? true)) {
            $this->optimizeMetaTitle($article, $language);
            $improvements[] = 'meta_title';
        }

        // 3. Fix meta description if wrong length
        if (!($checks['meta_desc'] ?? true)) {
            $this->optimizeMetaDescription($article, $language);
            $improvements[] = 'meta_description';
        }

        // 4. Fix AEO (ai_summary) if missing
        if (!($checks['aeo'] ?? true)) {
            $this->optimizeAeoSummary($article, $language);
            $improvements[] = 'aeo_summary';
        }

        // 5. Fix word count if too short — extend content
        if (!($checks['word_count'] ?? true)) {
            $this->extendContent($article, $contentType, $language);
            $improvements[] = 'content_extended';
        }

        // 6. Fix H2 structure if not enough
        if (!($checks['h2_structure'] ?? true)) {
            $this->addH2Sections($article, $language);
            $improvements[] = 'h2_structure';
        }

        // 7. Fix internal links if not enough
        if (!($checks['internal_links'] ?? true)) {
            $improvements[] = 'internal_links_noted'; // Can't auto-fix without knowing other articles
        }

        // 8. Fix E-E-A-T signals
        if (!($checks['eeat'] ?? true)) {
            $this->improveEeat($article, $language);
            $improvements[] = 'eeat_signals';
        }

        // 9. Ensure CTA is present (ALWAYS — every article must have a CTA)
        if (!$this->hasCta($article)) {
            $this->addCta($article);
            $improvements[] = 'cta_added';
        }

        return $improvements;
    }

    private function optimizeFeaturedSnippet(GeneratedArticle $article): void
    {
        $html = $article->content_html ?? '';
        $title = $article->title ?? '';

        $result = $this->openAi->complete(
            "Tu es expert SEO. Genere un paragraphe de featured snippet (40-60 mots) qui repond directement au sujet de l'article. Commence par une reformulation du sujet.",
            "Titre: {$title}\nPremiers 500 chars du contenu: " . mb_substr(strip_tags($html), 0, 500),
            ['temperature' => 0.5, 'max_tokens' => 200]
        );

        $snippet = trim($result['content'] ?? $result['text'] ?? '');
        if (empty($snippet)) return;

        // Insert snippet as first paragraph
        $snippetHtml = "<p><strong>{$snippet}</strong></p>\n";
        $article->update([
            'content_html' => $snippetHtml . $html,
        ]);
    }

    private function optimizeMetaTitle(GeneratedArticle $article, string $language): void
    {
        $result = $this->openAi->complete(
            "Reecris ce meta title SEO en 50-60 caracteres exactement. Mot-cle principal au debut. Inclus l'annee 2026. Langue: {$language}.",
            "Titre actuel: {$article->meta_title}\nSujet: {$article->title}",
            ['temperature' => 0.5, 'max_tokens' => 80]
        );

        $newTitle = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($newTitle) && mb_strlen($newTitle) <= 65) {
            $article->update(['meta_title' => $newTitle]);
        }
    }

    private function optimizeMetaDescription(GeneratedArticle $article, string $language): void
    {
        $result = $this->openAi->complete(
            "Reecris cette meta description en 140-155 caracteres. Commence par un verbe d'action. Langue: {$language}.",
            "Description actuelle: {$article->meta_description}\nSujet: {$article->title}",
            ['temperature' => 0.5, 'max_tokens' => 100]
        );

        $newDesc = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($newDesc) && mb_strlen($newDesc) <= 160) {
            $article->update(['meta_description' => $newDesc]);
        }
    }

    private function optimizeAeoSummary(GeneratedArticle $article, string $language): void
    {
        $result = $this->openAi->complete(
            "Genere un ai_summary factuel en 1 phrase de max 100 caracteres. Reponse directe a l'intention de recherche. Langue: {$language}.",
            "Titre: {$article->title}\nExtrait: " . mb_substr(strip_tags($article->content_html ?? ''), 0, 300),
            ['temperature' => 0.3, 'max_tokens' => 60]
        );

        $summary = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($summary) && mb_strlen($summary) <= 120) {
            $article->update(['ai_summary' => $summary]);
        }
    }

    private function extendContent(GeneratedArticle $article, string $contentType, string $language): void
    {
        $currentWords = str_word_count(strip_tags($article->content_html ?? ''));
        $targetWords = match ($contentType) {
            'guide', 'pillar', 'guide_city' => 4000,
            'article' => 2000,
            'comparative' => 2500,
            default => 800,
        };

        if ($currentWords >= $targetWords * 0.8) return; // Close enough

        $needed = $targetWords - $currentWords;
        $kbContext = $this->knowledgeBase->getLightPrompt($contentType, $article->country, $language);

        $result = $this->openAi->complete(
            $kbContext . "\n\nDeveloppe ce contenu avec {$needed} mots supplementaires. Ajoute des donnees chiffrees, exemples concrets, et sous-sections H2/H3. Meme ton et style.",
            "Titre: {$article->title}\nContenu actuel (fin):\n" . mb_substr($article->content_html ?? '', -2000),
            ['temperature' => 0.7, 'max_tokens' => 4000]
        );

        $extension = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($extension) && str_word_count(strip_tags($extension)) > 100) {
            $article->update([
                'content_html' => $article->content_html . "\n" . $extension,
                'word_count' => str_word_count(strip_tags($article->content_html . $extension)),
            ]);
        }
    }

    private function addH2Sections(GeneratedArticle $article, string $language): void
    {
        $result = $this->openAi->complete(
            "Genere 2 sections H2 supplementaires (chaque section: 1 H2 + 2-3 paragraphes de 80 mots) en rapport avec le sujet. HTML uniquement. Langue: {$language}.",
            "Titre article: {$article->title}\nSections existantes: " . implode(', ', $this->extractH2($article->content_html ?? '')),
            ['temperature' => 0.7, 'max_tokens' => 2000]
        );

        $sections = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($sections) && str_contains($sections, '<h2')) {
            // Insert before closing CTA if present, otherwise append
            $html = $article->content_html ?? '';
            if (str_contains($html, 'cta-box')) {
                $html = str_replace('<div class="cta-box">', $sections . "\n<div class=\"cta-box\">", $html);
            } else {
                $html .= "\n" . $sections;
            }
            $article->update(['content_html' => $html]);
        }
    }

    private function improveEeat(GeneratedArticle $article, string $language): void
    {
        $result = $this->openAi->complete(
            "Ajoute des signaux E-E-A-T a ce contenu: 1 exemple concret vecu, 1 source officielle citee entre parentheses, et la mention 'Mis a jour en 2026'. HTML. Langue: {$language}.",
            "Titre: {$article->title}\nExtrait: " . mb_substr(strip_tags($article->content_html ?? ''), 0, 500),
            ['temperature' => 0.6, 'max_tokens' => 500]
        );

        $eeatBlock = trim($result['content'] ?? $result['text'] ?? '');
        if (!empty($eeatBlock)) {
            $html = $article->content_html ?? '';
            // Add E-E-A-T block after first H2
            $pos = strpos($html, '</h2>');
            if ($pos !== false) {
                $insertAfter = $pos + 5;
                $html = substr($html, 0, $insertAfter) . "\n" . $eeatBlock . "\n" . substr($html, $insertAfter);
                $article->update(['content_html' => $html]);
            }
        }
    }

    private function hasCta(GeneratedArticle $article): bool
    {
        $html = $article->content_html ?? '';
        return str_contains($html, 'cta-box')
            || str_contains($html, 'sos-expat.com')
            || str_contains($html, 'SOS-Expat.com');
    }

    private function addCta(GeneratedArticle $article): void
    {
        $cta = \App\Helpers\CtaHelper::html($article->language ?? 'fr', $article->country);

        $html = $article->content_html ?? '';
        // Don't add if already has a CTA
        if (!$this->hasCta($article)) {
            $article->update(['content_html' => $html . "\n" . $cta]);
        }
    }

    private function extractH2(string $html): array
    {
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);
        return array_map('strip_tags', $matches[1] ?? []);
    }
}
