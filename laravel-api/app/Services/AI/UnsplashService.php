<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unsplash API — royalty-free image search with proper attribution.
 */
class UnsplashService
{
    private string $accessKey;

    /** Max requests per hour (Unsplash limit is 50, keep 10 margin). */
    private const RATE_LIMIT_PER_HOUR = 40;

    public function __construct()
    {
        $this->accessKey = config('services.unsplash.access_key', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessKey);
    }

    /**
     * Check if we are within the hourly rate limit.
     * Returns true if request is allowed, false if limit reached.
     */
    private function checkRateLimit(): bool
    {
        $key = 'unsplash:rate:' . now()->format('Y-m-d-H');
        $current = (int) Cache::get($key, 0);

        if ($current >= self::RATE_LIMIT_PER_HOUR) {
            Log::warning('Unsplash rate limit reached', [
                'current'  => $current,
                'limit'    => self::RATE_LIMIT_PER_HOUR,
                'hour_key' => $key,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Increment the rate limit counter after a successful API call.
     */
    private function incrementRateLimit(): void
    {
        $key = 'unsplash:rate:' . now()->format('Y-m-d-H');
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, 3600);
    }

    /**
     * Search for photos on Unsplash.
     *
     * @param string $query        Search query
     * @param int    $perPage      Results per page (1-30)
     * @param string $orientation  landscape|portrait|squarish
     * @param int    $page         Page number (1-based) — used by callers
     *                             that need to paginate past already-used
     *                             photos to find fresh ones
     */
    public function search(string $query, int $perPage = 5, string $orientation = 'landscape', int $page = 1): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'images' => [], 'error' => 'Unsplash access key not configured'];
        }

        if (!$this->checkRateLimit()) {
            return ['success' => false, 'images' => [], 'error' => 'Unsplash rate limit reached (40/hour)'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(30)->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'per_page' => $perPage,
                'orientation' => $orientation,
                'page' => max(1, $page),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];
                $images = [];

                foreach ($results as $photo) {
                    $rawUrl = $photo['urls']['raw'] ?? $photo['urls']['regular'] ?? '';
                    $photographerName = $photo['user']['name'] ?? 'Unknown';
                    $photographerUrl = $photo['user']['links']['html'] ?? 'https://unsplash.com';

                    $images[] = [
                        'url' => $rawUrl ? $rawUrl . '&w=1200&q=80&auto=format' : '',
                        'thumb_url' => $rawUrl ? $rawUrl . '&w=400&q=75&auto=format' : ($photo['urls']['thumb'] ?? ''),
                        'alt_text' => $photo['description'] ?? $photo['alt_description'] ?? $query,
                        'attribution' => "Photo by {$photographerName} on Unsplash",
                        'photographer_name' => $photographerName,
                        'photographer_url' => $photographerUrl . '?utm_source=sos-expat&utm_medium=referral',
                        'unsplash_url' => ($photo['links']['html'] ?? 'https://unsplash.com') . '?utm_source=sos-expat&utm_medium=referral',
                        'raw_url' => $rawUrl,
                        'width' => $photo['width'] ?? 0,
                        'height' => $photo['height'] ?? 0,
                        'download_url' => $photo['links']['download_location'] ?? '',
                        'srcset' => $rawUrl ? implode(', ', [
                            $rawUrl . '&w=640&q=80&auto=format 640w',
                            $rawUrl . '&w=960&q=80&auto=format 960w',
                            $rawUrl . '&w=1200&q=80&auto=format 1200w',
                        ]) : '',
                    ];

                    // Trigger download tracking (Unsplash API requirement)
                    $downloadLocation = $photo['links']['download_location'] ?? null;
                    if ($downloadLocation) {
                        $this->triggerDownloadTracking($downloadLocation);
                    }
                }

                $this->incrementRateLimit();

                Log::info('Unsplash search OK', [
                    'query' => $query,
                    'results' => count($images),
                ]);

                return [
                    'success' => true,
                    'images' => $images,
                ];
            }

            // Still counts against our rate limit (Unsplash counts all requests)
            $this->incrementRateLimit();

            Log::warning('Unsplash search error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'images' => [],
                'error' => 'HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Unsplash search exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'images' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for photos on Unsplash, returning only photos that have NEVER
     * been used on the blog. Paginates up to maxPages searching for fresh
     * results. Falls back to allowing reuse if the entire query space is
     * exhausted (rare, only for very narrow niche queries).
     *
     * Returns the same shape as search(): ['success' => bool, 'images' => array]
     */
    public function searchUnique(
        string $query,
        int $count = 3,
        string $orientation = 'landscape',
        int $maxPages = 5,
    ): array {
        $tracker = app(UnsplashUsageTracker::class);
        $unique = [];
        $page = 1;
        $perPage = 20; // pull a wider batch per page so dedup has more to work with
        $lastResult = null;

        while (count($unique) < $count && $page <= $maxPages) {
            $result = $this->search($query, $perPage, $orientation, $page);
            $lastResult = $result;
            if (empty($result['success']) || empty($result['images'])) {
                break;
            }

            foreach ($result['images'] as $img) {
                $url = $img['url'] ?? '';
                if (empty($url)) continue;
                $photoId = $tracker->extractPhotoId($url);
                if (!$photoId || $tracker->isUsed($photoId)) {
                    continue;
                }
                $unique[] = $img;
                if (count($unique) >= $count) break 2;
            }

            // If this page returned fewer than perPage results, the query is
            // exhausted on Unsplash's side — no point requesting another page.
            if (count($result['images']) < $perPage) {
                break;
            }

            $page++;
        }

        if (empty($unique)) {
            // Last resort: query exhausted or all duplicates. Return the
            // standard search result so the caller still gets *something*
            // and the pipeline keeps moving. Reuse is logged for analytics.
            Log::warning('UnsplashService::searchUnique exhausted, falling back to reuse', [
                'query' => $query,
                'pages_tried' => $page,
            ]);
            if ($lastResult && !empty($lastResult['images'])) {
                return $lastResult;
            }
            // Try one more search without dedup as final fallback
            return $this->search($query, $count, $orientation, 1);
        }

        return ['success' => true, 'images' => $unique];
    }

    /**
     * Get a random photo matching a query.
     */
    public function getRandomPhoto(string $query): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if (!$this->checkRateLimit()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(30)->get('https://api.unsplash.com/photos/random', [
                'query' => $query,
                'orientation' => 'landscape',
            ]);

            if ($response->successful()) {
                $photo = $response->json();

                // Trigger download tracking
                $downloadLocation = $photo['links']['download_location'] ?? null;
                if ($downloadLocation) {
                    $this->triggerDownloadTracking($downloadLocation);
                }

                $this->incrementRateLimit();

                Log::info('Unsplash random OK', ['query' => $query]);

                $rawUrl = $photo['urls']['raw'] ?? $photo['urls']['regular'] ?? '';
                $photographerName = $photo['user']['name'] ?? 'Unknown';
                $photographerUrl = $photo['user']['links']['html'] ?? 'https://unsplash.com';

                return [
                    'url' => $rawUrl ? $rawUrl . '&w=1200&q=80&auto=format' : '',
                    'thumb_url' => $rawUrl ? $rawUrl . '&w=400&q=75&auto=format' : ($photo['urls']['thumb'] ?? ''),
                    'alt_text' => $photo['description'] ?? $photo['alt_description'] ?? $query,
                    'attribution' => "Photo by {$photographerName} on Unsplash",
                    'photographer_name' => $photographerName,
                    'photographer_url' => $photographerUrl . '?utm_source=sos-expat&utm_medium=referral',
                    'unsplash_url' => ($photo['links']['html'] ?? 'https://unsplash.com') . '?utm_source=sos-expat&utm_medium=referral',
                    'raw_url' => $rawUrl,
                    'width' => $photo['width'] ?? 0,
                    'height' => $photo['height'] ?? 0,
                    'download_url' => $downloadLocation ?? '',
                    'srcset' => $rawUrl ? implode(', ', [
                        $rawUrl . '&w=640&q=80&auto=format 640w',
                        $rawUrl . '&w=960&q=80&auto=format 960w',
                        $rawUrl . '&w=1200&q=80&auto=format 1200w',
                    ]) : '',
                ];
            }

            $this->incrementRateLimit();

            Log::warning('Unsplash random error', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Unsplash random exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return null;
        }
    }

    /**
     * Trigger Unsplash download tracking (required by API guidelines).
     */
    private function triggerDownloadTracking(string $downloadLocation): void
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(10)->get($downloadLocation);
        } catch (\Throwable $e) {
            // Non-critical — just log and move on
            Log::debug('Unsplash download tracking failed', ['message' => $e->getMessage()]);
        }
    }
}
