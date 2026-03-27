<?php

namespace App\Services\Quality;

use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\Log;

/**
 * Internal plagiarism detection using shingling + Jaccard similarity.
 * Compares articles within the same library to detect content overlap.
 */
class PlagiarismService
{
    /** Default shingle size (consecutive words). */
    private const SHINGLE_SIZE = 5;

    /** Minimum words for matching phrase detection. */
    private const MIN_PHRASE_WORDS = 10;

    /** Maximum matches to return. */
    private const MAX_MATCHES = 5;

    /** Maximum matching phrases to extract per comparison. */
    private const MAX_PHRASES = 5;

    /**
     * Check an article for internal plagiarism against the library.
     */
    public function check(GeneratedArticle $article): array
    {
        try {
            $text = $this->normalize($article->content_html ?? '');

            if (empty($text)) {
                return $this->emptyResult();
            }

            $shingles = $this->generateShingles($text, self::SHINGLE_SIZE);
            $totalShingles = count($shingles);

            if ($totalShingles === 0) {
                return $this->emptyResult();
            }

            // Get candidate articles (same language, published or draft, exclude self & translations)
            $candidates = GeneratedArticle::where('language', $article->language)
                ->where('id', '!=', $article->id)
                ->whereNull('parent_article_id')
                ->when($article->parent_article_id, function ($query) use ($article) {
                    $query->where('id', '!=', $article->parent_article_id);
                })
                ->where(function ($query) use ($article) {
                    // Same country or same category for performance
                    $query->where('country', $article->country)
                        ->orWhere('content_type', $article->content_type);
                })
                ->select(['id', 'title', 'slug', 'content_html', 'language', 'country'])
                ->get();

            $matches = [];
            $allCandidateShingleSets = [];

            foreach ($candidates as $candidate) {
                $candidateText = $this->normalize($candidate->content_html ?? '');

                if (empty($candidateText)) {
                    continue;
                }

                $candidateShingles = $this->generateShingles($candidateText, self::SHINGLE_SIZE);

                if (empty($candidateShingles)) {
                    continue;
                }

                $similarity = $this->calculateJaccardSimilarity($shingles, $candidateShingles);

                if ($similarity > 20.0) {
                    $matchingPhrases = $this->findMatchingPhrases($text, $candidateText, self::MIN_PHRASE_WORDS);

                    $matches[] = [
                        'article_id'       => $candidate->id,
                        'article_title'    => $candidate->title,
                        'similarity'       => round($similarity, 2),
                        'matching_phrases' => $matchingPhrases,
                    ];
                }

                // Track all shingle hashes for unique count
                foreach ($candidateShingles as $hash) {
                    $allCandidateShingleSets[$hash] = true;
                }
            }

            // Sort by similarity descending, take top N
            usort($matches, fn (array $a, array $b) => $b['similarity'] <=> $a['similarity']);
            $matches = array_slice($matches, 0, self::MAX_MATCHES);

            // Calculate highest similarity
            $highestSimilarity = !empty($matches) ? $matches[0]['similarity'] : 0.0;

            // Determine status
            if ($highestSimilarity >= 35) {
                $status = 'plagiarized';
            } elseif ($highestSimilarity >= 20) {
                $status = 'similar';
            } else {
                $status = 'original';
            }

            // Unique shingles (how many of our shingles don't appear elsewhere)
            $uniqueShingles = 0;
            foreach ($shingles as $hash) {
                if (!isset($allCandidateShingleSets[$hash])) {
                    $uniqueShingles++;
                }
            }

            Log::info('Plagiarism check complete', [
                'article_id'         => $article->id,
                'status'             => $status,
                'similarity_percent' => $highestSimilarity,
                'matches_count'      => count($matches),
            ]);

            return [
                'is_original'       => $highestSimilarity < 20,
                'similarity_percent' => $highestSimilarity,
                'status'            => $status,
                'matches'           => $matches,
                'total_shingles'    => $totalShingles,
                'unique_shingles'   => $uniqueShingles,
            ];
        } catch (\Throwable $e) {
            Log::error('Plagiarism check failed', [
                'article_id' => $article->id ?? null,
                'message'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ============================================================
    // Shingling engine
    // ============================================================

    /**
     * Generate shingle hashes from text using a sliding window.
     *
     * @return array<int> Array of CRC32 hashes
     */
    private function generateShingles(string $text, int $size = 5): array
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < $size) {
            return [];
        }

        $hashes = [];

        for ($i = 0; $i <= count($words) - $size; $i++) {
            $shingle = implode(' ', array_slice($words, $i, $size));
            $hashes[] = crc32($shingle);
        }

        return $hashes;
    }

    /**
     * Calculate Jaccard similarity between two sets of shingle hashes.
     */
    private function calculateJaccardSimilarity(array $shinglesA, array $shinglesB): float
    {
        $setA = array_flip($shinglesA);
        $setB = array_flip($shinglesB);

        $intersection = count(array_intersect_key($setA, $setB));
        $union = count($setA + $setB); // array union by keys

        if ($union === 0) {
            return 0.0;
        }

        return ($intersection / $union) * 100;
    }

    /**
     * Find exact matching phrases of $minWords+ consecutive words.
     *
     * @return string[] Up to MAX_PHRASES longest matching phrases
     */
    private function findMatchingPhrases(string $textA, string $textB, int $minWords = 10): array
    {
        $wordsA = preg_split('/\s+/', $textA, -1, PREG_SPLIT_NO_EMPTY);
        $wordsB = preg_split('/\s+/', $textB, -1, PREG_SPLIT_NO_EMPTY);

        if (count($wordsA) < $minWords || count($wordsB) < $minWords) {
            return [];
        }

        // Build a lookup of word positions in textB
        $wordPosB = [];
        foreach ($wordsB as $idx => $word) {
            $wordPosB[$word][] = $idx;
        }

        $phrases = [];
        $usedStartsA = [];

        for ($i = 0; $i < count($wordsA); $i++) {
            if (isset($usedStartsA[$i])) {
                continue;
            }

            $word = $wordsA[$i];

            if (!isset($wordPosB[$word])) {
                continue;
            }

            foreach ($wordPosB[$word] as $j) {
                // Extend match as far as possible
                $matchLen = 0;
                while (
                    ($i + $matchLen) < count($wordsA)
                    && ($j + $matchLen) < count($wordsB)
                    && $wordsA[$i + $matchLen] === $wordsB[$j + $matchLen]
                ) {
                    $matchLen++;
                }

                if ($matchLen >= $minWords) {
                    $phrase = implode(' ', array_slice($wordsA, $i, $matchLen));
                    $phrases[] = $phrase;

                    // Mark positions as used to avoid overlapping matches
                    for ($k = $i; $k < $i + $matchLen; $k++) {
                        $usedStartsA[$k] = true;
                    }
                    break; // Move to next position in A
                }
            }
        }

        // Sort by length descending, take top N
        usort($phrases, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($phrases, 0, self::MAX_PHRASES);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Normalize text for comparison: lowercase, strip HTML, remove punctuation.
     */
    private function normalize(string $html): string
    {
        // Strip HTML
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Lowercase
        $text = mb_strtolower($text);

        // Remove punctuation but keep accented chars
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }

    /**
     * Empty result for blank articles.
     */
    private function emptyResult(): array
    {
        return [
            'is_original'        => true,
            'similarity_percent' => 0.0,
            'status'             => 'original',
            'matches'            => [],
            'total_shingles'     => 0,
            'unique_shingles'    => 0,
        ];
    }
}
