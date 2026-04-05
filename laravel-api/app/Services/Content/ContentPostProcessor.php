<?php

namespace App\Services\Content;

use App\Models\ApiCost;
use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\Log;

/**
 * Universal post-generation processor for ALL content types.
 * Runs quality checks, SEO scoring, plagiarism detection, and cost tracking
 * on any generated content before it reaches the blog.
 *
 * Called by: GenerateQrBlogJob, NewsGenerationService, FichesPays (blog-side),
 *           and indirectly by ArticleGenerationService (which has its own Phase 14).
 */
class ContentPostProcessor
{

    /**
     * Process generated content and return enriched data with quality metrics.
     *
     * @param  array  $content  The generated content array with keys:
     *   - content_html (required)
     *   - meta_title (required)
     *   - meta_description
     *   - excerpt
     *   - keywords_primary
     *   - keywords_secondary
     *   - faqs
     *   - ai_summary
     * @param  string  $contentType  article, qa, news, guide, comparative
     * @param  string  $language     Language code (fr, en, etc.)
     * @param  string|null  $country Country code
     * @param  string|null  $source  Source identifier for cost tracking
     * @return array  Original content enriched with quality_metrics key
     */
    public function process(
        array $content,
        string $contentType,
        string $language,
        ?string $country = null,
        ?string $source = null,
    ): array {
        $metrics = [
            'seo_score' => 0,
            'quality_score' => 0,
            'word_count' => 0,
            'readability_score' => 0,
            'plagiarism_status' => 'unknown',
            'plagiarism_percent' => 0,
            'issues' => [],
            'passed' => true,
        ];

        $html = $content['content_html'] ?? '';
        $text = strip_tags($html);
        $metrics['word_count'] = str_word_count($text);

        // ─── SEO SCORE ───────────────────────────────
        try {
            $seoResult = $this->calculateSeoScore($content, $contentType, $language);
            $metrics['seo_score'] = $seoResult['score'];
            $metrics['issues'] = array_merge($metrics['issues'], $seoResult['issues']);
        } catch (\Throwable $e) {
            Log::warning('ContentPostProcessor: SEO check failed', ['error' => $e->getMessage()]);
        }

        // ─── READABILITY ─────────────────────────────
        try {
            $metrics['readability_score'] = $this->calculateReadability($text, $language);
        } catch (\Throwable $e) {
            Log::warning('ContentPostProcessor: Readability check failed', ['error' => $e->getMessage()]);
        }

        // ─── QUALITY SCORE (weighted) ────────────────
        $seoWeight = 40;
        $readabilityWeight = 25;
        $lengthWeight = 20;
        $faqWeight = 15;

        $lengthScore = $this->calculateLengthScore($metrics['word_count'], $contentType);
        $faqScore = $this->calculateFaqScore($content['faqs'] ?? [], $contentType);

        $metrics['quality_score'] = (int) round(
            ($metrics['seo_score'] * $seoWeight / 100) +
            ($metrics['readability_score'] * $readabilityWeight / 100) +
            ($lengthScore * $lengthWeight / 100) +
            ($faqScore * $faqWeight / 100)
        );

        // ─── PLAGIARISM CHECK (lightweight — shingling without model dependency) ──
        try {
            if ($metrics['word_count'] > 100) {
                $plagResult = $this->checkPlagiarismLightweight($text, $language, $contentType, $country);
                $metrics['plagiarism_status'] = $plagResult['status'];
                $metrics['plagiarism_percent'] = $plagResult['similarity_percent'];

                if ($metrics['plagiarism_percent'] > 30) {
                    $metrics['quality_score'] = max(0, $metrics['quality_score'] - 15);
                    $metrics['issues'][] = "Similarite elevee ({$metrics['plagiarism_percent']}%) avec du contenu existant";
                }
            }
        } catch (\Throwable $e) {
            // Plagiarism check is non-blocking
            Log::warning('ContentPostProcessor: Plagiarism check failed', ['error' => $e->getMessage()]);
        }

        // ─── MINIMUM QUALITY GATE ────────────────────
        if ($metrics['quality_score'] < 30) {
            $metrics['passed'] = false;
            $metrics['issues'][] = "Score qualite insuffisant ({$metrics['quality_score']}/100)";
        }

        // ─── COST TRACKING ───────────────────────────
        if ($source) {
            try {
                ApiCost::create([
                    'service' => 'post_processor',
                    'model' => 'rules_based',
                    'operation' => "quality_check_{$contentType}",
                    'input_tokens' => $metrics['word_count'],
                    'output_tokens' => 0,
                    'cost_cents' => 0, // Rule-based checks are free
                ]);
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        // Enrich content with metrics
        $content['quality_metrics'] = $metrics;

        return $content;
    }

    /**
     * Calculate SEO score for raw content (before it's a model).
     */
    private function calculateSeoScore(array $content, string $contentType, string $language): array
    {
        $score = 0;
        $issues = [];
        $html = $content['content_html'] ?? '';
        $title = $content['meta_title'] ?? '';
        $desc = $content['meta_description'] ?? '';
        $keyword = $content['keywords_primary'] ?? '';

        // Title check (10 points)
        $titleLen = mb_strlen($title);
        if ($titleLen >= 50 && $titleLen <= 60) {
            $score += 10;
        } elseif ($titleLen >= 40 && $titleLen <= 70) {
            $score += 7;
        } else {
            $score += 3;
            $issues[] = "Meta title: {$titleLen} chars (ideal 50-60)";
        }

        // Description check (10 points)
        $descLen = mb_strlen($desc);
        if ($descLen >= 140 && $descLen <= 155) {
            $score += 10;
        } elseif ($descLen >= 120 && $descLen <= 170) {
            $score += 7;
        } else {
            $score += 3;
            $issues[] = "Meta description: {$descLen} chars (ideal 140-155)";
        }

        // Headings check (10 points)
        $h2Count = preg_match_all('/<h2/i', $html);
        if ($h2Count >= 4 && $h2Count <= 12) {
            $score += 10;
        } elseif ($h2Count >= 2) {
            $score += 6;
        } else {
            $score += 2;
            $issues[] = "Seulement {$h2Count} H2 (ideal 4-12)";
        }

        // Content length check (20 points)
        $wordCount = str_word_count(strip_tags($html));
        $minWords = match ($contentType) {
            'qa' => 300,
            'news' => 500,
            default => 1000,
        };
        if ($wordCount >= $minWords) {
            $score += 20;
        } elseif ($wordCount >= $minWords * 0.7) {
            $score += 12;
        } else {
            $score += 5;
            $issues[] = "Contenu: {$wordCount} mots (min {$minWords})";
        }

        // Keyword presence (15 points)
        if ($keyword) {
            $keywordLower = mb_strtolower($keyword);
            $titleHasKw = str_contains(mb_strtolower($title), $keywordLower);
            $htmlHasKw = str_contains(mb_strtolower(strip_tags($html)), $keywordLower);
            $descHasKw = str_contains(mb_strtolower($desc), $keywordLower);

            if ($titleHasKw) $score += 5;
            if ($htmlHasKw) $score += 5;
            if ($descHasKw) $score += 5;

            if (!$titleHasKw) $issues[] = "Mot-cle absent du titre";
            if (!$htmlHasKw) $issues[] = "Mot-cle absent du contenu";
        } else {
            $score += 8; // Neutral if no keyword specified
        }

        // Images check (5 points)
        $imgCount = preg_match_all('/<img/i', $html);
        if ($imgCount > 0) $score += 5;
        else $issues[] = "Aucune image dans le contenu";

        // Internal links check (5 points)
        $linkCount = preg_match_all('/<a\s/i', $html);
        if ($linkCount >= 2) $score += 5;
        elseif ($linkCount >= 1) $score += 3;
        else $issues[] = "Aucun lien dans le contenu";

        // FAQ check (5 points)
        $faqCount = count($content['faqs'] ?? []);
        if ($faqCount >= 3) $score += 5;
        elseif ($faqCount >= 1) $score += 3;

        // Structured data potential (5 points) — always give if FAQs present
        if ($faqCount > 0) $score += 5;

        // Lists check (5 points)
        $listCount = preg_match_all('/<(ul|ol)/i', $html);
        if ($listCount >= 2) $score += 5;
        elseif ($listCount >= 1) $score += 3;

        // Tables check (5 points)
        $tableCount = preg_match_all('/<table/i', $html);
        if ($tableCount >= 1) $score += 5;

        return ['score' => min(100, $score), 'issues' => $issues];
    }

    /**
     * Simple readability score (0-100).
     */
    private function calculateReadability(string $text, string $language): int
    {
        if (empty($text)) return 0;

        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $sentenceCount = count($sentences);
        $wordCount = count($words);

        if ($sentenceCount === 0 || $wordCount === 0) return 50;

        $avgWordsPerSentence = $wordCount / $sentenceCount;

        // Ideal: 15-25 words per sentence
        if ($avgWordsPerSentence >= 15 && $avgWordsPerSentence <= 25) {
            $score = 85;
        } elseif ($avgWordsPerSentence >= 10 && $avgWordsPerSentence <= 30) {
            $score = 70;
        } elseif ($avgWordsPerSentence < 10) {
            $score = 60; // Too choppy
        } else {
            $score = 50; // Too long sentences
        }

        // Paragraph variety bonus
        $paragraphs = preg_match_all('/<p/i', $text) ?: 0;
        if ($paragraphs >= 5) $score += 10;

        return min(100, $score);
    }

    /**
     * Score based on content length for the given type.
     */
    private function calculateLengthScore(int $wordCount, string $contentType): int
    {
        [$min, $max] = match ($contentType) {
            'qa' => [300, 1000],
            'news' => [500, 1500],
            'guide', 'pillar' => [2000, 5000],
            'comparative' => [1500, 4000],
            default => [1000, 3000],
        };

        if ($wordCount >= $min && $wordCount <= $max) return 100;
        if ($wordCount < $min) return (int) round(($wordCount / $min) * 100);
        return max(60, 100 - (int) round(($wordCount - $max) / ($max * 0.5) * 40));
    }

    /**
     * Score based on FAQ count for the given type.
     */
    private function calculateFaqScore(array $faqs, string $contentType): int
    {
        $count = count($faqs);
        $target = match ($contentType) {
            'qa' => 5,
            'news' => 3,
            default => 6,
        };

        if ($count >= $target) return 100;
        if ($count === 0) return 0;
        return (int) round(($count / $target) * 100);
    }

    /**
     * Lightweight plagiarism check using 5-word shingling against existing articles.
     * Does NOT require a GeneratedArticle model — works with raw text.
     */
    private function checkPlagiarismLightweight(string $text, string $language, string $contentType, ?string $country): array
    {
        $words = preg_split('/\s+/', mb_strtolower($text));
        if (count($words) < 20) {
            return ['status' => 'original', 'similarity_percent' => 0];
        }

        // Build shingles (5-word sliding window)
        $shingles = [];
        for ($i = 0; $i <= count($words) - 5; $i++) {
            $shingle = implode(' ', array_slice($words, $i, 5));
            $shingles[crc32($shingle)] = true;
        }

        if (empty($shingles)) {
            return ['status' => 'original', 'similarity_percent' => 0];
        }

        // Compare against recent articles (same language, limit 50 for performance)
        $candidates = GeneratedArticle::where('language', $language)
            ->where('content_type', $contentType)
            ->whereNotNull('content_text')
            ->when($country, fn ($q) => $q->where('country', $country))
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('content_text');

        $maxSimilarity = 0;

        foreach ($candidates as $candidateText) {
            $candidateWords = preg_split('/\s+/', mb_strtolower($candidateText));
            $candidateShingles = [];
            for ($i = 0; $i <= count($candidateWords) - 5; $i++) {
                $candidateShingles[crc32(implode(' ', array_slice($candidateWords, $i, 5)))] = true;
            }

            if (empty($candidateShingles)) continue;

            // Jaccard similarity
            $intersection = count(array_intersect_key($shingles, $candidateShingles));
            $union = count($shingles) + count($candidateShingles) - $intersection;
            $similarity = $union > 0 ? round(($intersection / $union) * 100) : 0;

            $maxSimilarity = max($maxSimilarity, $similarity);

            // Early exit if clearly plagiarized
            if ($maxSimilarity > 50) break;
        }

        $status = match (true) {
            $maxSimilarity >= 35 => 'plagiarized',
            $maxSimilarity >= 20 => 'similar',
            default => 'original',
        };

        return ['status' => $status, 'similarity_percent' => $maxSimilarity];
    }
}
