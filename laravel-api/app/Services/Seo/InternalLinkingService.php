<?php

namespace App\Services\Seo;

use App\Models\GeneratedArticle;
use App\Models\InternalLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Internal linking engine — suggests and injects contextual links between articles.
 */
class InternalLinkingService
{
    /**
     * Suggest related articles to link to from the given article.
     */
    public function suggestLinks(GeneratedArticle $article, int $maxLinks = 6): array
    {
        try {
            // Find candidate articles (same language, published, not self)
            $candidates = GeneratedArticle::published()
                ->where('language', $article->language)
                ->where('id', '!=', $article->id)
                ->whereNull('parent_article_id') // Only originals, not translations
                ->get();

            if ($candidates->isEmpty()) {
                return [];
            }

            $articleKeywords = $this->extractKeywords($article);
            $scored = [];

            foreach ($candidates as $candidate) {
                $score = 0;
                $sameCountry = !empty($article->country) && $article->country === $candidate->country;

                // Same country: +5 (strong preference — users want local info)
                if ($sameCountry) {
                    $score += 5;
                }

                // Same content type: +1
                if ($article->content_type === $candidate->content_type) {
                    $score += 1;
                }

                // Same pillar: +3
                if (!empty($article->pillar_article_id) && $article->pillar_article_id === $candidate->pillar_article_id) {
                    $score += 3;
                }

                // Keyword overlap: +2 per shared keyword
                $candidateKeywords = $this->extractKeywords($candidate);
                $overlap = count(array_intersect(
                    array_map('mb_strtolower', $articleKeywords),
                    array_map('mb_strtolower', $candidateKeywords)
                ));
                $score += min(6, $overlap * 2); // Cap at +6

                // Bonus for articles without many incoming links (spread link equity)
                $incomingCount = $candidate->internalLinksIn()->count();
                if ($incomingCount < 2) {
                    $score += 1;
                }

                // Only include if minimum relevance met:
                // - Same country articles: score >= 3 (always relevant)
                // - Different country: score >= 6 (must have strong keyword overlap)
                $minScore = $sameCountry ? 3 : 6;

                if ($score >= $minScore) {
                    $scored[] = [
                        'target_id' => $candidate->id,
                        'target_title' => $candidate->title,
                        'target_url' => $candidate->url,
                        'anchor_text' => $this->generateAnchorText($candidate),
                        'relevance_score' => $score,
                    ];
                }
            }

            // Sort by score descending, take top N
            usort($scored, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

            $suggestions = array_slice($scored, 0, $maxLinks);

            Log::debug('Internal link suggestions', [
                'article_id' => $article->id,
                'candidates' => count($candidates),
                'suggestions' => count($suggestions),
            ]);

            return $suggestions;
        } catch (\Throwable $e) {
            Log::error('Internal link suggestion failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Inject internal links into article HTML content.
     */
    public function injectLinks(GeneratedArticle $article, array $suggestions): string
    {
        try {
            $html = $article->content_html ?? '';

            if (empty($html) || empty($suggestions)) {
                return $html;
            }

            $injectedCount = 0;

            foreach ($suggestions as $suggestion) {
                $anchorText = $suggestion['anchor_text'];
                $url = $suggestion['target_url'];

                // Try to find a natural insertion point: look for the anchor text or a related phrase in the content
                $insertionResult = $this->findAndInsertLink($html, $anchorText, $url);

                if ($insertionResult['injected']) {
                    $html = $insertionResult['html'];
                    $injectedCount++;

                    // Save InternalLink record
                    InternalLink::create([
                        'source_type' => GeneratedArticle::class,
                        'source_id' => $article->id,
                        'target_type' => GeneratedArticle::class,
                        'target_id' => $suggestion['target_id'],
                        'anchor_text' => $anchorText,
                        'context_sentence' => $insertionResult['context'] ?? '',
                        'is_auto_generated' => true,
                    ]);
                }
            }

            Log::info('Internal links injected', [
                'article_id' => $article->id,
                'suggested' => count($suggestions),
                'injected' => $injectedCount,
            ]);

            return $html;
        } catch (\Throwable $e) {
            Log::error('Internal link injection failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return $article->content_html ?? '';
        }
    }

    /**
     * Find published articles with zero incoming internal links.
     */
    public function findOrphanedArticles(): Collection
    {
        try {
            return GeneratedArticle::published()
                ->whereNull('parent_article_id')
                ->whereDoesntHave('internalLinksIn')
                ->orderBy('published_at', 'desc')
                ->get();
        } catch (\Throwable $e) {
            Log::error('Orphaned articles query failed', ['message' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * Generate natural anchor text for a target article.
     */
    public function generateAnchorText(GeneratedArticle $target): string
    {
        // Vary anchor text strategy for SEO diversity
        $strategies = ['title', 'partial', 'keyword'];
        $strategy = $strategies[array_rand($strategies)];

        switch ($strategy) {
            case 'keyword':
                // Use primary keyword if available
                if (!empty($target->keywords_primary)) {
                    return $target->keywords_primary;
                }
                // Fall through to title
                // no break
            case 'partial':
                // Use first part of title (up to 5 words)
                $words = explode(' ', $target->title);
                if (count($words) > 5) {
                    return implode(' ', array_slice($words, 0, 5));
                }
                // Fall through if title is short
                // no break
            case 'title':
            default:
                return $target->title;
        }
    }

    /**
     * Extract keywords from an article for matching purposes.
     */
    private function extractKeywords(GeneratedArticle $article): array
    {
        $keywords = [];

        if (!empty($article->keywords_primary)) {
            $keywords[] = $article->keywords_primary;
        }

        if (!empty($article->keywords_secondary) && is_array($article->keywords_secondary)) {
            $keywords = array_merge($keywords, $article->keywords_secondary);
        }

        return $keywords;
    }

    /**
     * Find a natural position in HTML to insert a link.
     * Looks for the anchor text phrase within paragraph text (not inside existing links).
     */
    private function findAndInsertLink(string $html, string $anchorText, string $url): array
    {
        // Strategy 1: Find exact anchor text in a paragraph (not already linked)
        $escapedAnchor = preg_quote($anchorText, '/');
        $pattern = '/(<p[^>]*>)((?:(?!<a\b).)*?)(' . $escapedAnchor . ')((?:(?!<\/a>).)*?)(<\/p>)/iu';

        if (preg_match($pattern, $html, $match)) {
            $link = '<a href="' . htmlspecialchars($url) . '">' . $match[3] . '</a>';
            $replacement = $match[1] . $match[2] . $link . $match[4] . $match[5];
            $newHtml = preg_replace($pattern, $replacement, $html, 1);

            return [
                'injected' => true,
                'html' => $newHtml,
                'context' => strip_tags($match[0]),
            ];
        }

        // Strategy 2: Append link at the end of a relevant paragraph (one that contains related words)
        $anchorWords = explode(' ', mb_strtolower($anchorText));
        $significantWords = array_filter($anchorWords, fn ($w) => mb_strlen($w) > 3);

        if (!empty($significantWords)) {
            $firstWord = reset($significantWords);
            $escapedWord = preg_quote($firstWord, '/');
            $paragraphPattern = '/(<p[^>]*>)(.*?' . $escapedWord . '.*?)(<\/p>)/ius';

            if (preg_match($paragraphPattern, $html, $match)) {
                $link = ' <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($anchorText) . '</a>';
                // Insert before the closing </p>
                $replacement = $match[1] . $match[2] . $link . $match[3];
                $newHtml = preg_replace($paragraphPattern, $replacement, $html, 1);

                return [
                    'injected' => true,
                    'html' => $newHtml,
                    'context' => strip_tags($match[0]),
                ];
            }
        }

        // Strategy 3: Append link at the end of the last paragraph before the first h2
        if (preg_match('/(<p[^>]*>.*?<\/p>)\s*(<h2)/is', $html, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[1][1] + strlen($match[1][0]);
            $link = "\n<p><a href=\"" . htmlspecialchars($url) . '">' . htmlspecialchars($anchorText) . '</a></p>';
            $newHtml = substr($html, 0, $insertPos) . $link . substr($html, $insertPos);

            return [
                'injected' => true,
                'html' => $newHtml,
                'context' => '',
            ];
        }

        return ['injected' => false, 'html' => $html, 'context' => ''];
    }
}
