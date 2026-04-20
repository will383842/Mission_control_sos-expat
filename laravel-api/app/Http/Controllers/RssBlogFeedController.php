<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlFeedsBlogrollJob;
use App\Jobs\ScrapeBloggerRssFeedsJob;
use App\Models\RssBlogFeed;
use App\Services\Scraping\RssAutoDetectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Option D — P5 : Controller CRUD pour gérer les feeds RSS de blogs.
 * Tout sous middleware role:admin.
 */
class RssBlogFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RssBlogFeed::query();

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }
        if ($request->filled('search')) {
            $needle = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'ILIKE', $needle)
                  ->orWhere('url', 'ILIKE', $needle)
                  ->orWhere('base_url', 'ILIKE', $needle);
            });
        }

        $feeds = $query->orderBy('name')->paginate(50);

        return response()->json($feeds);
    }

    public function show(RssBlogFeed $feed): JsonResponse
    {
        return response()->json(['feed' => $feed]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $feed = RssBlogFeed::create($data);

        return response()->json(['feed' => $feed], 201);
    }

    public function update(Request $request, RssBlogFeed $feed): JsonResponse
    {
        $data = $this->validated($request, false, $feed->id);
        $feed->update($data);

        return response()->json(['feed' => $feed->fresh()]);
    }

    public function destroy(RssBlogFeed $feed): JsonResponse
    {
        // Soft delete logique : désactiver plutôt que supprimer
        $feed->update(['active' => false]);

        return response()->json([
            'message' => 'Feed désactivé.',
            'feed'    => $feed->fresh(),
        ]);
    }

    public function scrape(RssBlogFeed $feed): JsonResponse
    {
        ScrapeBloggerRssFeedsJob::dispatch($feed->id);

        return response()->json([
            'dispatched' => true,
            'feed_id'    => $feed->id,
            'message'    => "Scrape dispatché pour '{$feed->name}'.",
        ], 202);
    }

    // ─────────────────────────────────────────────────────────────────
    // OPTION D2 — Endpoints découverte feeds
    // ─────────────────────────────────────────────────────────────────

    /**
     * Détecte le feed RSS depuis une URL de site web.
     * POST /api/rss-blog-feeds/detect-rss {url}
     * Si detected + not dup → propose auto-add (user confirme).
     */
    public function detectRss(Request $request, RssAutoDetectService $autoDetect): JsonResponse
    {
        $data = $request->validate([
            'url'     => 'required|url|max:500',
            'auto_add' => 'sometimes|boolean',
        ]);

        $feedUrl = $autoDetect->detectFeedUrl($data['url']);
        if (!$feedUrl) {
            return response()->json([
                'detected' => false,
                'message'  => 'Aucun feed RSS détecté sur cette URL.',
            ], 404);
        }

        // Déjà en base ?
        $existing = RssBlogFeed::where('url', $feedUrl)->first();
        if ($existing) {
            return response()->json([
                'detected'        => true,
                'feed_url'        => $feedUrl,
                'already_exists'  => true,
                'existing_feed'   => $existing,
                'message'         => "Feed déjà présent : '{$existing->name}'",
            ]);
        }

        // Auto-add demandé ?
        if (!empty($data['auto_add'])) {
            $parsed = parse_url($data['url']);
            $host = $parsed['host'] ?? 'unknown';
            $cleanName = ucfirst(preg_replace('/^www\./', '', $host));
            $feed = RssBlogFeed::create([
                'name'     => $cleanName,
                'url'      => $feedUrl,
                'base_url' => $data['url'],
                'language' => 'fr',
                'category' => 'manual_import',
                'active'   => true,
                'fetch_about' => true,
                'fetch_pattern_inference' => false,
                'fetch_interval_hours' => 24,
                'notes'    => "Importé manuellement depuis $cleanName",
            ]);

            return response()->json([
                'detected'       => true,
                'feed_url'       => $feedUrl,
                'already_exists' => false,
                'created'        => true,
                'feed'           => $feed,
            ], 201);
        }

        return response()->json([
            'detected'       => true,
            'feed_url'       => $feedUrl,
            'already_exists' => false,
            'message'        => 'Feed détecté, passez auto_add=true pour l\'ajouter.',
        ]);
    }

    /**
     * Importe un fichier OPML (multi-feeds) et ajoute les nouveaux dans rss_blog_feeds.
     * POST /api/rss-blog-feeds/import-opml (multipart : file=opml_file.xml)
     */
    public function importOpml(Request $request): JsonResponse
    {
        $request->validate([
            'file'            => 'required|file|max:5120', // 5 MB max
            'default_language' => 'sometimes|string|max:5',
            'default_country' => 'nullable|string|max:100',
            'default_category' => 'nullable|string|max:100',
        ]);

        $contents = file_get_contents($request->file('file')->getRealPath());
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            return response()->json(['error' => 'Fichier OPML invalide (XML malformé)'], 422);
        }

        $outlines = $this->extractOpmlOutlines($xml);
        $added = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($outlines as $outline) {
            $url = $outline['xmlUrl'] ?? '';
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors++;
                continue;
            }
            if (RssBlogFeed::where('url', $url)->exists()) {
                $skipped++;
                continue;
            }

            try {
                RssBlogFeed::create([
                    'name'     => substr(trim($outline['title'] ?? $outline['text'] ?? parse_url($url, PHP_URL_HOST) ?: 'Feed'), 0, 255),
                    'url'      => $url,
                    'base_url' => $outline['htmlUrl'] ?? null,
                    'language' => $request->input('default_language', 'fr'),
                    'country'  => $request->input('default_country'),
                    'category' => $request->input('default_category', 'opml_import'),
                    'active'   => true,
                    'fetch_about' => true,
                    'fetch_pattern_inference' => false,
                    'fetch_interval_hours' => 24,
                    'notes'    => 'Importé depuis OPML',
                ]);
                $added++;
            } catch (\Throwable $e) {
                Log::debug('ImportOpml: insert failed', ['url' => $url, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        return response()->json([
            'added'   => $added,
            'skipped' => $skipped,
            'errors'  => $errors,
            'total_outlines' => count($outlines),
            'message' => "OPML importé : {$added} nouveaux, {$skipped} déjà présents, {$errors} erreurs.",
        ]);
    }

    /**
     * Lance le crawl des blogrolls pour découvrir de nouveaux feeds
     * depuis les homepages des feeds existants.
     * POST /api/rss-blog-feeds/discover/blogrolls
     */
    public function discoverBlogrolls(): JsonResponse
    {
        CrawlFeedsBlogrollJob::dispatch();
        return response()->json([
            'dispatched' => true,
            'message'    => 'Crawl des blogrolls dispatché. Résultats dans les prochaines minutes (~1h max pour 78 feeds).',
        ], 202);
    }

    /**
     * Parcourt récursivement un OPML pour extraire tous les outlines
     * avec xmlUrl (RSS feeds).
     */
    private function extractOpmlOutlines(\SimpleXMLElement $node): array
    {
        $out = [];
        foreach ($node->xpath('//outline') as $outline) {
            $attrs = $outline->attributes();
            if (isset($attrs['xmlUrl'])) {
                $out[] = [
                    'xmlUrl'  => (string) $attrs['xmlUrl'],
                    'htmlUrl' => isset($attrs['htmlUrl']) ? (string) $attrs['htmlUrl'] : null,
                    'title'   => isset($attrs['title']) ? (string) $attrs['title'] : null,
                    'text'    => isset($attrs['text']) ? (string) $attrs['text'] : null,
                ];
            }
        }
        return $out;
    }

    private function validated(Request $request, bool $isCreate, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:rss_blog_feeds,url';
        if ($ignoreId) {
            $uniqueRule .= ",{$ignoreId}";
        }

        $required = $isCreate ? 'required' : 'sometimes';

        return $request->validate([
            'name'                    => "{$required}|string|max:255",
            'url'                     => "{$required}|url|max:500|{$uniqueRule}",
            'base_url'                => 'nullable|url|max:500',
            'language'                => 'sometimes|string|max:5',
            'country'                 => 'nullable|string|max:100',
            'category'                => 'nullable|string|max:100',
            'active'                  => 'sometimes|boolean',
            'fetch_about'             => 'sometimes|boolean',
            'fetch_pattern_inference' => 'sometimes|boolean',
            'fetch_interval_hours'    => 'sometimes|integer|min:1|max:168',
            'notes'                   => 'nullable|string|max:2000',
        ]);
    }
}
