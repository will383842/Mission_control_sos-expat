<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tracks which Unsplash photos have already been used across the blog so
 * the same image is never published twice. The dedup key is the photo_id
 * extracted from the Unsplash URL (e.g. "photo-1566830646346-908d87490bba")
 * which is stable regardless of the resize/format query parameters
 * appended at fetch time.
 */
class UnsplashUsageTracker
{
    /**
     * Pull the stable photo_id out of an Unsplash URL.
     * Examples:
     *   https://images.unsplash.com/photo-1566830646346-908d87490bba?ixlib=...
     *     → "photo-1566830646346-908d87490bba"
     *   https://images.unsplash.com/reserve/AbCdEfGh?w=...
     *     → "reserve-AbCdEfGh"
     * Returns null if the URL is not recognisable.
     */
    public function extractPhotoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        // Strip query string + fragment
        $clean = preg_replace('/[?#].*$/', '', $url);
        // Match the path segment after images.unsplash.com/
        if (preg_match('#unsplash\.com/(.+?)$#', $clean, $m)) {
            // Replace remaining slashes with hyphens to make a single id
            return trim(str_replace('/', '-', $m[1]));
        }
        return null;
    }

    public function isUsed(?string $photoId): bool
    {
        if (empty($photoId)) {
            return false;
        }
        return DB::table('used_unsplash_photos')->where('photo_id', $photoId)->exists();
    }

    /**
     * Record a photo as used. Idempotent — if the photo_id already exists
     * we silently keep the previous record (no upsert/replace, since the
     * earliest user is the canonical owner).
     */
    public function markUsed(
        string $photoUrl,
        ?int $articleId = null,
        ?string $language = null,
        ?string $country = null,
        ?string $sourceQuery = null,
        ?string $photographerName = null,
        ?string $photographerUrl = null,
    ): bool {
        $photoId = $this->extractPhotoId($photoUrl);
        if (!$photoId) {
            return false;
        }

        try {
            DB::table('used_unsplash_photos')->insertOrIgnore([
                'photo_id'          => $photoId,
                'photo_url'         => $photoUrl,
                'photographer_name' => $photographerName,
                'photographer_url'  => $photographerUrl,
                'article_id'        => $articleId,
                'language'          => $language,
                'country'           => $country,
                'source_query'      => $sourceQuery ? mb_substr($sourceQuery, 0, 250) : null,
                'used_at'           => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('UnsplashUsageTracker::markUsed failed', [
                'photo_id' => $photoId,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Bulk check — given a list of photo URLs, return only the ones NOT yet
     * used. Useful for filtering Unsplash search results before picking one.
     */
    public function filterUnused(array $photoUrls): array
    {
        if (empty($photoUrls)) {
            return [];
        }
        $idsToUrls = [];
        foreach ($photoUrls as $url) {
            $id = $this->extractPhotoId($url);
            if ($id) {
                $idsToUrls[$id] = $url;
            }
        }
        if (empty($idsToUrls)) {
            return [];
        }
        $usedIds = DB::table('used_unsplash_photos')
            ->whereIn('photo_id', array_keys($idsToUrls))
            ->pluck('photo_id')
            ->toArray();
        $unused = array_diff_key($idsToUrls, array_flip($usedIds));
        return array_values($unused);
    }

    /**
     * Total count of distinct photos already used (for monitoring).
     */
    public function count(): int
    {
        return DB::table('used_unsplash_photos')->count();
    }
}
