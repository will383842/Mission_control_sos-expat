<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\UnsplashService;
use App\Services\AI\UnsplashUsageTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the used_unsplash_photos tracking table from existing articles
 * and re-fetch fresh photos for any article family currently sharing a
 * photo with another family. A "family" is one parent article + all its
 * translations — they are the SAME article in different languages and
 * are *expected* to share the same image.
 *
 * Pass --backfill-only to populate the tracker without changing any photo.
 */
class DedupeUnsplashPhotosCommand extends Command
{
    protected $signature = 'images:dedupe-unsplash
        {--dry-run : Show what would change without writing}
        {--backfill-only : Only populate the tracker, do not re-fetch new photos}
        {--limit=0 : Stop after N families re-fetched (0 = no limit)}';

    protected $description = 'Backfill used_unsplash_photos tracker and re-fetch fresh photos for duplicate article families';

    public function handle(UnsplashService $unsplash, UnsplashUsageTracker $tracker): int
    {
        $dryRun       = (bool) $this->option('dry-run');
        $backfillOnly = (bool) $this->option('backfill-only');
        $limit        = (int) $this->option('limit');

        // ---- Phase 1: backfill ------------------------------------------------
        $this->info('Phase 1 — backfill used_unsplash_photos from existing articles');

        $rows = GeneratedArticle::query()
            ->whereNotNull('featured_image_url')
            ->whereNull('parent_article_id') // canonical = parent only
            ->orderBy('id') // earliest parent wins as canonical owner
            ->get(['id', 'featured_image_url', 'language', 'country', 'photographer_name', 'photographer_url', 'keywords_primary']);

        $inserted = 0;
        $skipped  = 0;
        foreach ($rows as $row) {
            $photoId = $tracker->extractPhotoId($row->featured_image_url);
            if (!$photoId) {
                continue;
            }
            if ($tracker->isUsed($photoId)) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $inserted++;
                continue;
            }
            $tracker->markUsed(
                $row->featured_image_url,
                $row->id,
                $row->language,
                $row->country,
                $row->keywords_primary,
                $row->photographer_name,
                $row->photographer_url,
            );
            $inserted++;
        }

        $this->info("  -> inserted {$inserted} canonical photo_ids, skipped {$skipped} already present");

        if ($backfillOnly) {
            $this->info('Backfill-only mode: stopping here.');
            return self::SUCCESS;
        }

        // ---- Phase 2: cleanup duplicates --------------------------------------
        $this->newLine();
        $this->info('Phase 2 — find article families sharing a photo with another family');

        // Group by photo_url, count how many DISTINCT parent families use it
        // (parent_id = parent_article_id, or own id if no parent).
        $clusters = DB::table('generated_articles')
            ->selectRaw('featured_image_url, COUNT(DISTINCT COALESCE(parent_article_id, id)) AS family_count')
            ->whereNotNull('featured_image_url')
            ->whereNull('deleted_at')
            ->groupBy('featured_image_url')
            ->havingRaw('COUNT(DISTINCT COALESCE(parent_article_id, id)) > 1')
            ->orderByDesc('family_count')
            ->get();

        $this->info("  -> {$clusters->count()} photos shared across multiple families");

        if ($clusters->isEmpty()) {
            return self::SUCCESS;
        }

        if (!$unsplash->isConfigured()) {
            $this->error('Unsplash access key not configured — cannot re-fetch photos.');
            return self::FAILURE;
        }

        $refetched = 0;
        $failed    = 0;
        $families_processed = 0;

        foreach ($clusters as $cluster) {
            // All parent ids using this same photo, oldest first
            $parentIds = DB::table('generated_articles')
                ->selectRaw('DISTINCT COALESCE(parent_article_id, id) AS family_id')
                ->where('featured_image_url', $cluster->featured_image_url)
                ->whereNull('deleted_at')
                ->orderBy('family_id')
                ->pluck('family_id')
                ->toArray();

            // Keep the first (canonical), re-fetch for the rest
            $canonical = array_shift($parentIds);
            $this->line("  shared by " . (count($parentIds) + 1) . " families — keeping canonical #{$canonical}");

            foreach ($parentIds as $parentId) {
                if ($limit > 0 && $families_processed >= $limit) {
                    $this->warn("  reached --limit={$limit}, stopping");
                    break 2;
                }

                /** @var GeneratedArticle|null $parent */
                $parent = GeneratedArticle::find($parentId);
                if (!$parent) {
                    continue;
                }

                $searchTerm = $this->buildSearchTerm($parent);
                $this->line("    family #{$parentId} [{$parent->language}/{$parent->country}] -> \"{$searchTerm}\"");

                if ($dryRun) {
                    $families_processed++;
                    continue;
                }

                $result = $unsplash->searchUnique($searchTerm, 1, 'landscape');
                // Narrow French queries often return 0 results — fall back to
                // a generic English term keyed on the country.
                if (empty($result['success']) || empty($result['images'])) {
                    $fallback = $this->fallbackSearchTerm($parent);
                    $this->line("      retry with fallback: \"{$fallback}\"");
                    $result = $unsplash->searchUnique($fallback, 1, 'landscape');
                }
                if (empty($result['success']) || empty($result['images'])) {
                    $this->warn('      no fresh image found, leaving as-is');
                    $failed++;
                    $families_processed++;
                    continue;
                }

                $image   = $result['images'][0];
                $altText = mb_substr(trim(
                    $parent->title . ($parent->country ? ' (' . $parent->country . ')' : '')
                ), 0, 125);

                $imageData = [
                    'featured_image_url'         => $image['url'],
                    'featured_image_alt'         => $altText,
                    'featured_image_attribution' => $image['attribution'] ?? null,
                    'featured_image_srcset'      => $image['srcset'] ?? null,
                    'photographer_name'          => $image['photographer_name'] ?? null,
                    'photographer_url'           => $image['photographer_url'] ?? null,
                ];

                // Update parent + propagate to ALL its translations
                $parent->update($imageData);
                $translationCount = $parent->translations()->update($imageData);

                // Mark photo used so subsequent iterations don't re-pick it
                $tracker->markUsed(
                    $image['url'],
                    $parent->id,
                    $parent->language,
                    $parent->country,
                    $searchTerm,
                    $image['photographer_name'] ?? null,
                    $image['photographer_url'] ?? null,
                );

                $this->info("      OK -> propagated to {$translationCount} translations");
                $refetched++;
                $families_processed++;

                // Be polite to Unsplash API — searchUnique can issue several requests
                usleep(800_000); // 800 ms
            }
        }

        $this->newLine();
        $this->info("Done: {$refetched} families re-fetched, {$failed} failed" . ($dryRun ? ' (dry run)' : ''));
        return self::SUCCESS;
    }

    private function buildSearchTerm(GeneratedArticle $article): string
    {
        $keywords = trim((string) ($article->keywords_primary ?? ''));
        $country  = trim((string) ($article->country ?? ''));

        if ($country !== '' && $keywords !== '') {
            return $country . ' ' . mb_substr($keywords, 0, 60);
        }
        if ($country !== '') {
            return $country . ' travel landscape';
        }
        if ($keywords !== '') {
            return mb_substr($keywords, 0, 60);
        }
        return mb_substr((string) $article->title, 0, 60);
    }

    private const COUNTRY_FALLBACKS = [
        'TH' => 'Thailand landscape temple',
        'VE' => 'Venezuela landscape Caracas',
        'FR' => 'France Paris landscape',
        'DE' => 'Germany landscape',
        'JP' => 'Japan landscape',
        'PT' => 'Portugal Lisbon coast',
        'AE' => 'Dubai skyline',
        'ES' => 'Spain landscape',
        'IT' => 'Italy landscape',
        'GB' => 'United Kingdom London',
        'US' => 'United States landscape',
        'CA' => 'Canada landscape',
        'AU' => 'Australia landscape',
        'BR' => 'Brazil landscape',
        'MX' => 'Mexico landscape',
        'MA' => 'Morocco landscape',
        'TN' => 'Tunisia landscape',
        'DZ' => 'Algeria landscape',
        'SN' => 'Senegal landscape',
    ];

    private function fallbackSearchTerm(GeneratedArticle $article): string
    {
        $country = strtoupper(trim((string) ($article->country ?? '')));
        if ($country !== '' && isset(self::COUNTRY_FALLBACKS[$country])) {
            return self::COUNTRY_FALLBACKS[$country];
        }
        if ($country !== '') {
            return $country . ' travel city landscape';
        }
        return 'travel landscape';
    }
}
