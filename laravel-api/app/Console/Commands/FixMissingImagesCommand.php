<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\UnsplashService;
use App\Services\AI\UnsplashUsageTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Batch-fix published articles that have no featured image.
 * Only targets PARENT articles (no parent_article_id), then propagates
 * the image to all their translations automatically.
 */
class FixMissingImagesCommand extends Command
{
    protected $signature = 'articles:fix-missing-images
        {--dry-run : Show what would be fixed without changing anything}
        {--limit=0 : Max parent articles to process (0 = all)}';

    protected $description = 'Search and assign featured images (Unsplash) for parent articles missing one, then propagate to translations';

    /**
     * Map of generic English search terms for common French keywords.
     * Unsplash works best with English queries.
     */
    private const SEARCH_TERM_MAP = [
        'visa' => 'visa passport travel',
        'expatriation' => 'expat abroad living',
        'avocat' => 'lawyer office professional',
        'chatter' => 'customer service chat support',
        'thailande' => 'Thailand landscape temple',
        'allemagne' => 'Germany cityscape Berlin',
        'france' => 'France Paris cityscape',
        'dubai' => 'Dubai skyline modern city',
        'japon' => 'Japan Tokyo temple',
        'portugal' => 'Portugal Lisbon coast',
        'aix-en-provence' => 'Aix-en-Provence France lavender',
        'digital nomad' => 'digital nomad laptop travel',
        'cout de la vie' => 'cost of living budget money',
        'état civil' => 'official documents paperwork',
    ];

    public function handle(UnsplashService $unsplash, UnsplashUsageTracker $tracker): int
    {
        $dryRun = $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if (!$unsplash->isConfigured()) {
            $this->error('Unsplash access key is not configured (services.unsplash.access_key).');
            return self::FAILURE;
        }

        // Only target PARENT articles (translations inherit from parent)
        $query = GeneratedArticle::where('status', 'published')
            ->whereNull('featured_image_url')
            ->whereNull('parent_article_id')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $parents = $query->get();
        $count   = $parents->count();

        $this->info("Found {$count} published PARENT articles without featured_image_url" . ($dryRun ? ' (dry run)' : ''));

        if ($count === 0) {
            $this->info('Checking for translations that need image propagation...');
            $propagated = $this->propagateImagesToTranslations($dryRun);
            if ($propagated > 0) {
                $this->info("Propagated images to {$propagated} translations.");
            }
            return self::SUCCESS;
        }

        $fixed  = 0;
        $failed = 0;

        foreach ($parents as $index => $article) {
            $searchTerm = $this->buildSearchTerm($article);
            $this->line("  [{$index}/{$count}] #{$article->id} [{$article->language}] \"{$searchTerm}\"");

            if ($dryRun) {
                $translationCount = $article->translations()->count();
                $this->line("    -> would propagate to {$translationCount} translations");
                $fixed++;
                continue;
            }

            try {
                $result = $unsplash->searchUnique($searchTerm, 1, 'landscape');

                if (!($result['success'] ?? false) || empty($result['images'])) {
                    $error = $result['error'] ?? 'no results';
                    $this->warn("    -> No image found ({$error})");

                    if (str_contains($error, 'rate limit')) {
                        $this->error('Rate limit reached — stopping.');
                        break;
                    }

                    $failed++;
                } else {
                    $image = $result['images'][0];

                    // Alt = article title (+ country). See ArticleGenerationService
                    // phase12_addImages for the same pattern. Do not concat the
                    // Unsplash image alt_text (English) or the keywords_primary
                    // (may contain legacy template artifacts).
                    $altText = mb_substr(trim(
                        $article->title . ($article->country ? ' (' . $article->country . ')' : '')
                    ), 0, 125);

                    $imageData = [
                        'featured_image_url'         => $image['url'],
                        'featured_image_alt'         => $altText,
                        'featured_image_attribution'  => $image['attribution'] ?? null,
                        'featured_image_srcset'       => $image['srcset'] ?? null,
                        'photographer_name'           => $image['photographer_name'] ?? null,
                        'photographer_url'            => $image['photographer_url'] ?? null,
                    ];

                    // Update parent
                    $article->update($imageData);

                    // Propagate to ALL translations
                    $translationCount = $article->translations()
                        ->whereNull('featured_image_url')
                        ->update($imageData);

                    // Mark photo as used so it's never picked again
                    $tracker->markUsed(
                        $image['url'],
                        $article->id,
                        $article->language,
                        $article->country,
                        $searchTerm,
                        $image['photographer_name'] ?? null,
                        $image['photographer_url'] ?? null,
                    );

                    $this->info("    -> OK: {$image['photographer_name']} (Unsplash) — propagated to {$translationCount} translations");
                    $fixed++;
                }
            } catch (\Throwable $e) {
                $this->error("    -> FAILED: {$e->getMessage()}");
                Log::error('FixMissingImages failed', [
                    'article_id' => $article->id,
                    'error'      => $e->getMessage(),
                ]);
                $failed++;
            }

            // Rate limit: 1 second between Unsplash API calls
            if (!$dryRun && $index < $count - 1) {
                sleep(1);
            }
        }

        // Also propagate images from parents that already have images
        // but whose translations are missing them
        if (!$dryRun) {
            $propagated = $this->propagateImagesToTranslations(false);
            if ($propagated > 0) {
                $this->info("Additionally propagated existing parent images to {$propagated} translations.");
            }
        }

        $this->newLine();
        $this->info("Done: {$fixed} parents fixed, {$failed} failed" . ($dryRun ? ' (dry run -- no changes made)' : ''));

        return self::SUCCESS;
    }

    /**
     * Build an English-friendly search term for Unsplash.
     */
    private function buildSearchTerm(GeneratedArticle $article): string
    {
        $keywords = strtolower($article->keywords_primary ?? '');
        $title    = strtolower($article->title ?? '');

        // Try to match known French terms to English equivalents
        foreach (self::SEARCH_TERM_MAP as $french => $english) {
            if (str_contains($keywords, $french) || str_contains($title, $french)) {
                return $english;
            }
        }

        // If country is set, use it as a search term
        if ($article->country) {
            return $article->country . ' expatriate travel';
        }

        // Fallback: use keywords or title, but try to extract meaningful words
        return $keywords ?: mb_substr($title, 0, 40);
    }

    /**
     * Propagate images from parents to translations that are missing them.
     */
    private function propagateImagesToTranslations(bool $dryRun): int
    {
        $parentsWithImages = GeneratedArticle::where('status', 'published')
            ->whereNull('parent_article_id')
            ->whereNotNull('featured_image_url')
            ->get();

        $totalPropagated = 0;

        foreach ($parentsWithImages as $parent) {
            $translationsWithoutImage = $parent->translations()
                ->whereNull('featured_image_url')
                ->count();

            if ($translationsWithoutImage > 0) {
                if (!$dryRun) {
                    $parent->translations()
                        ->whereNull('featured_image_url')
                        ->update([
                            'featured_image_url'         => $parent->featured_image_url,
                            'featured_image_alt'         => $parent->featured_image_alt,
                            'featured_image_attribution'  => $parent->featured_image_attribution,
                            'featured_image_srcset'       => $parent->featured_image_srcset,
                            'photographer_name'           => $parent->photographer_name,
                            'photographer_url'            => $parent->photographer_url,
                        ]);
                }
                $totalPropagated += $translationsWithoutImage;
            }
        }

        return $totalPropagated;
    }
}
