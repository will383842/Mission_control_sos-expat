<?php

namespace App\Http\Controllers;

use App\Services\Content\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public read-only endpoint for the Knowledge Base.
 *
 * Exposes a curated, content-safe subset of the KB so downstream services
 * (Blog_sos-expat_frontend, Social Multi-Platform, Backlink Engine) can pin
 * a single source of truth for brand voice, pricing, commissions, content
 * rules — without pulling in sensitive internals (anti-fraud, infra, bots).
 *
 * Caching is long (24h) because the KB itself is versioned and rarely changes
 * within a day. Downstream services should cache aggressively as well.
 */
class KnowledgeBaseController extends Controller
{
    private const CACHE_KEY = 'kb:public:v2';
    private const CACHE_TTL_SECONDS = 24 * 3600;

    public function show(Request $request): JsonResponse
    {
        $etag = 'W/"' . md5(config('app.key') . $this->cacheVersion()) . '"';

        // Conditional GET — return 304 if client has current version
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        // Cache in-file (not DB) so we don't hard-fail when the database cache
        // driver is unreachable. The KB payload is small (~55KB) and read-heavy.
        $payload = Cache::store('file')->remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => (new KnowledgeBaseService())->toPublicArray()
        );

        $response = response()->json($payload);
        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'public, max-age=3600, s-maxage=' . self::CACHE_TTL_SECONDS);
        $response->header('X-KB-Version', $payload['meta']['kb_version'] ?? 'unknown');
        $response->header('X-KB-Updated-At', $payload['meta']['kb_updated_at'] ?? 'unknown');

        return $response;
    }

    /**
     * Lightweight metadata-only endpoint — allows downstream services to
     * check for KB updates without pulling the whole payload.
     */
    public function meta(): JsonResponse
    {
        $svc = new KnowledgeBaseService();
        return response()->json([
            'kb_version' => $svc->getVersion(),
            'kb_updated_at' => $svc->getUpdatedAt(),
            'scope' => 'public',
            'endpoints' => [
                'full' => url('/api/public/knowledge-base'),
                'meta' => url('/api/public/knowledge-base/meta'),
            ],
            'served_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    private function cacheVersion(): string
    {
        $svc = new KnowledgeBaseService();
        return ($svc->getVersion()) . '|' . ($svc->getUpdatedAt());
    }
}
