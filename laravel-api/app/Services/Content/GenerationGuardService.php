<?php

namespace App\Services\Content;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\Log;

/**
 * Pre-generation guard — MUST be called before ANY content generation.
 *
 * Centralizes duplicate detection across ALL generators:
 * - ArticleGenerationService
 * - NewsGenerationService
 * - GenerateQrBlogJob
 * - ComparativeGenerationService
 * - Any future generator
 *
 * Three-layer check:
 *   Layer 1: Exact slug match (instant reject)
 *   Layer 2: Title similarity via Jaccard (> 0.50 → reject)
 *   Layer 3: Cross-source check (same type ≥ 0.75 → reject, cross-type ≥ 0.60 → reject, 0.40-0.60 → flag)
 *
 * Returns a GuardResult with status: 'allow', 'flag', or 'block'.
 */
class GenerationGuardService
{
    public function __construct(
        private DeduplicationService $dedup,
    ) {}

    /**
     * Check if generation should proceed.
     *
     * @param string      $title       Title of the article to generate
     * @param string      $contentType Content type (qa, news, article, guide, comparative, etc.)
     * @param string      $language    Language code (fr, en, es, etc.)
     * @param string|null $country     Country slug (optional)
     * @param string|null $sourceSlug  Source identifier for cross-source checks
     *
     * @return array{status: string, reason: string|null, existing_id: string|null, similarity: float|null}
     */
    public function check(
        string $title,
        string $contentType,
        string $language,
        ?string $country = null,
        ?string $sourceSlug = null,
    ): array {
        $logContext = compact('title', 'contentType', 'language', 'country');

        // Layer 1 + 2: Exact slug match + title similarity
        if ($country) {
            $existing = $this->dedup->findDuplicateArticle($title, $country, $language);
            if ($existing) {
                Log::warning('GenerationGuard: BLOCKED — duplicate found', [
                    ...$logContext,
                    'existing_id' => $existing->id,
                    'existing_title' => $existing->title,
                ]);

                return [
                    'status' => 'block',
                    'reason' => "Article similaire existe deja: \"{$existing->title}\" (ID: {$existing->id})",
                    'existing_id' => $existing->id,
                    'similarity' => null,
                ];
            }
        }

        // Layer 3: Cross-source duplicate detection
        if ($country) {
            $crossCheck = $this->dedup->checkCrossSourceDuplicate(
                $title,
                $country,
                $language,
                $contentType,
                $sourceSlug,
            );

            if ($crossCheck) {
                $status = $crossCheck['status'] === 'duplicate' ? 'block' : 'flag';

                Log::log(
                    $status === 'block' ? 'warning' : 'info',
                    "GenerationGuard: {$status} — {$crossCheck['reason']}",
                    $logContext,
                );

                return [
                    'status' => $status,
                    'reason' => $crossCheck['reason'],
                    'existing_id' => $crossCheck['existing_id'] ?? null,
                    'similarity' => $crossCheck['similarity'] ?? null,
                ];
            }
        }

        // Additional check: same title across ALL countries (global duplicate)
        $globalDuplicate = $this->checkGlobalTitleDuplicate($title, $contentType, $language);
        if ($globalDuplicate) {
            return $globalDuplicate;
        }

        Log::debug('GenerationGuard: ALLOWED', $logContext);

        return [
            'status' => 'allow',
            'reason' => null,
            'existing_id' => null,
            'similarity' => null,
        ];
    }

    /**
     * Check for Q/A-specific duplicates.
     */
    public function checkQa(string $question, string $language): array
    {
        $existing = $this->dedup->findDuplicateQa($question, $language);

        if ($existing) {
            Log::warning('GenerationGuard: Q/A duplicate blocked', [
                'question' => $question,
                'existing_id' => $existing->id,
            ]);

            return [
                'status' => 'block',
                'reason' => "Question similaire existe: \"{$existing->question}\"",
                'existing_id' => $existing->id,
                'similarity' => null,
            ];
        }

        return [
            'status' => 'allow',
            'reason' => null,
            'existing_id' => null,
            'similarity' => null,
        ];
    }

    /**
     * Check for comparative-specific duplicates (entity set comparison).
     */
    public function checkComparative(array $entities, string $language): array
    {
        // Sort entities for consistent comparison
        sort($entities);
        $entitiesKey = implode(' vs ', $entities);

        // Check if same comparative exists in comparatives table
        $existing = Comparative::where('language', $language)
            ->whereIn('status', ['draft', 'review', 'published'])
            ->whereNull('parent_id')
            ->get();

        foreach ($existing as $article) {
            $existingEntities = is_array($article->entities) ? $article->entities : json_decode($article->entities ?? '[]', true);
            if (is_array($existingEntities)) {
                sort($existingEntities);
                $existingKey = implode(' vs ', $existingEntities);

                if ($entitiesKey === $existingKey) {
                    return [
                        'status' => 'block',
                        'reason' => "Comparatif identique existe: \"{$article->title}\"",
                        'existing_id' => $article->id,
                        'similarity' => 1.0,
                    ];
                }
            }

            // Also check title similarity as fallback
            $titleWords = $this->normalizeWords($entitiesKey);
            $candidateWords = $this->normalizeWords($article->title ?? '');
            $similarity = $this->jaccardSimilarity($titleWords, $candidateWords);

            if ($similarity > 0.6) {
                return [
                    'status' => 'flag',
                    'reason' => "Comparatif similaire: \"{$article->title}\" (similarite " . round($similarity * 100) . '%)',
                    'existing_id' => $article->id,
                    'similarity' => $similarity,
                ];
            }
        }

        return [
            'status' => 'allow',
            'reason' => null,
            'existing_id' => null,
            'similarity' => null,
        ];
    }

    /**
     * Batch check: returns results for multiple titles at once.
     * Useful for bulk generation scheduling.
     */
    public function batchCheck(array $items, string $language): array
    {
        $results = [];

        foreach ($items as $i => $item) {
            $results[$i] = $this->check(
                $item['title'],
                $item['content_type'] ?? 'article',
                $language,
                $item['country'] ?? null,
                $item['source_slug'] ?? null,
            );
        }

        return $results;
    }

    // -----------------------------------------------------------------
    // PRIVATE
    // -----------------------------------------------------------------

    /**
     * Check for global title duplicates (same title, any country).
     * Only flags — doesn't block. Same topic in different countries is OK
     * but should have different angles.
     */
    private function checkGlobalTitleDuplicate(string $title, string $contentType, string $language): ?array
    {
        $titleWords = $this->normalizeWords($title);

        $candidates = GeneratedArticle::where('language', $language)
            ->where('content_type', $contentType)
            ->whereNull('parent_article_id')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->select('id', 'title', 'country')
            ->limit(1000)
            ->get();

        foreach ($candidates as $candidate) {
            $candidateWords = $this->normalizeWords($candidate->title ?? '');
            $similarity = $this->jaccardSimilarity($titleWords, $candidateWords);

            if ($similarity > 0.8) {
                return [
                    'status' => 'flag',
                    'reason' => "Titre tres similaire dans un autre pays ({$candidate->country}): \"{$candidate->title}\"",
                    'existing_id' => $candidate->id,
                    'similarity' => $similarity,
                ];
            }
        }

        return null;
    }

    private function normalizeWords(string $text): array
    {
        $text = mb_strtolower($text);
        $text = str_replace(['à', 'â', 'ä'], 'a', $text);
        $text = str_replace(['é', 'è', 'ê', 'ë'], 'e', $text);
        $text = str_replace(['î', 'ï'], 'i', $text);
        $text = str_replace(['ô', 'ö'], 'o', $text);
        $text = str_replace(['ù', 'û', 'ü'], 'u', $text);
        $text = str_replace(['ç'], 'c', $text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $words = preg_split('/\s+/', trim($text));
        $stopwords = ['le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'pour', 'dans', 'sur', 'par', 'au', 'aux', 'a', 'ce', 'que', 'qui', 'est', 'avec', 'son', 'sa', 'ses', 'se', 'ne', 'pas', 'the', 'a', 'an', 'in', 'on', 'of', 'for', 'to', 'and', 'is', 'it'];

        return array_values(array_diff($words, $stopwords));
    }

    private function jaccardSimilarity(array $a, array $b): float
    {
        if (empty($a) && empty($b)) {
            return 0;
        }
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0;
    }
}
