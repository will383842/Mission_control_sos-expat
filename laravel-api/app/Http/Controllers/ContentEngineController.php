<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeContentSourceJob;
use App\Models\ContentArticle;
use App\Models\ContentCountry;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentEngineController extends Controller
{
    public function sources(): JsonResponse
    {
        $sources = ContentSource::withCount(['countries', 'articles', 'externalLinks'])
            ->orderBy('name')
            ->get();

        return response()->json($sources);
    }

    public function createSource(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100|unique:content_sources,name',
            'base_url' => ['required', 'url', 'max:500', 'regex:/^https:\/\//i', 'unique:content_sources,base_url'],
        ]);

        // Generate unique slug
        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (ContentSource::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        // Block reserved slugs that conflict with routes
        if (in_array($slug, ['links', 'articles', 'stats', 'sources'])) {
            $slug = $baseSlug . '-source';
        }

        $source = ContentSource::create([
            'name'     => $validated['name'],
            'slug'     => $slug,
            'base_url' => rtrim($validated['base_url'], '/') . '/',
        ]);

        return response()->json($source, 201);
    }

    public function showSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)
            ->withCount(['countries', 'articles', 'externalLinks'])
            ->firstOrFail();

        $scrapedCountries = $source->countries()->whereNotNull('scraped_at')->count();

        return response()->json([
            ...$source->toArray(),
            'scraped_countries' => $scrapedCountries,
        ]);
    }

    public function scrapeSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        if ($source->status === 'scraping') {
            // Auto-reset if stuck for more than 4 hours
            if ($source->updated_at && $source->updated_at->diffInHours(now()) > 4) {
                $source->update(['status' => 'pending']);
            } else {
                return response()->json(['message' => 'Scraping already in progress'], 409);
            }
        }

        $source->update(['status' => 'scraping']);
        ScrapeContentSourceJob::dispatch($source->id);

        return response()->json(['message' => 'Scraping started', 'source' => $source->fresh()]);
    }

    /**
     * Pause a running scrape.
     */
    public function pauseSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $source->update(['status' => 'paused']);
        return response()->json(['message' => 'Scraping paused', 'source' => $source]);
    }

    public function countries(string $slug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        $query = $source->countries()->withCount('articles');

        if ($request->filled('continent')) {
            $query->where('continent', $request->input('continent'));
        }

        $countries = $query->orderBy('continent')->orderBy('name')->get();

        // Extract continents from result instead of a separate query
        $continents = $countries->pluck('continent')->filter()->unique()->sort()->values();

        return response()->json([
            'countries'  => $countries,
            'continents' => $continents,
        ]);
    }

    public function countryArticles(string $slug, string $countrySlug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $country = ContentCountry::where('source_id', $source->id)
            ->where('slug', $countrySlug)
            ->firstOrFail();

        $query = ContentArticle::where('country_id', $country->id)
            ->select('id', 'title', 'slug', 'url', 'category', 'word_count', 'is_guide', 'scraped_at')
            ->withCount('links')
            ->orderByDesc('is_guide')
            ->orderBy('category')
            ->orderBy('title');

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json([
            'country'  => $country,
            'articles' => $query->paginate($perPage),
        ]);
    }

    public function showArticle(int $id): JsonResponse
    {
        $article = ContentArticle::with(['country:id,name,slug', 'source:id,name,slug'])
            ->findOrFail($id);

        $links = ContentExternalLink::where('article_id', $id)
            ->orderBy('domain')
            ->get();

        return response()->json([
            'article' => $article,
            'links'   => $links,
        ]);
    }

    public function externalLinks(Request $request): JsonResponse
    {
        $query = ContentExternalLink::with(['source:id,name,slug', 'country:id,name,slug']);

        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) {
                $query->where('source_id', $source->id);
            }
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }
        if ($request->filled('domain')) {
            $domain = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('domain'));
            $query->where('domain', 'ilike', '%' . $domain . '%');
        }
        if ($request->filled('link_type')) {
            $query->where('link_type', $request->input('link_type'));
        }
        if ($request->filled('is_affiliate')) {
            $query->where('is_affiliate', $request->boolean('is_affiliate'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('url', 'ilike', '%' . $search . '%')
                  ->orWhere('anchor_text', 'ilike', '%' . $search . '%')
                  ->orWhere('domain', 'ilike', '%' . $search . '%');
            });
        }

        // Whitelist sort columns to prevent SQL injection
        $allowedSorts = ['occurrences', 'domain', 'url', 'link_type', 'created_at', 'anchor_text'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'occurrences';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->paginate($perPage));
    }

    public function stats(): JsonResponse
    {
        $data = Cache::remember('content-engine-stats', 300, function () {
            return [
                'total_sources'   => ContentSource::count(),
                'total_countries' => ContentCountry::count(),
                'total_articles'  => ContentArticle::count(),
                'total_links'     => ContentExternalLink::count(),
                'total_words'     => (int) ContentArticle::sum('word_count'),
                'affiliate_links' => ContentExternalLink::where('is_affiliate', true)->count(),
                'top_domains'     => ContentExternalLink::selectRaw('domain, COUNT(*) as count, SUM(occurrences) as total_occurrences')
                    ->groupBy('domain')->orderByDesc('total_occurrences')->limit(20)->get(),
                'by_category'     => ContentArticle::selectRaw('category, COUNT(*) as count')
                    ->whereNotNull('category')->groupBy('category')->orderByDesc('count')->get(),
                'link_types'      => ContentExternalLink::selectRaw('link_type, COUNT(*) as count')
                    ->groupBy('link_type')->orderByDesc('count')->get(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Affiliate domains: sites with affiliate programs, grouped by domain.
     * Filters out false positives (gov sites, europa.eu, google, etc. that just have UTM params).
     */
    public function affiliateDomains(): JsonResponse
    {
        // Domains that are NOT affiliate programs (just have UTM tracking on their links)
        $excludePatterns = [
            '%.gov', '%.gov.%', '%.gouv.%', '%.gob.%', '%.edu', '%.edu.%',
            '%.int', 'europa.eu', '%.europa.eu',
            'www.google.%', 'maps.google.%',
            '%.wikipedia.org', '%.pagesjaunes.%',
            '%.ac.%', // academic
        ];

        $query = ContentExternalLink::selectRaw("
            domain,
            SUM(occurrences) as total_mentions,
            COUNT(*) as liens_uniques,
            MIN(url) as exemple_url,
            MIN(anchor_text) FILTER (WHERE anchor_text IS NOT NULL AND anchor_text != '') as exemple_anchor
        ")
        ->where('is_affiliate', true);

        foreach ($excludePatterns as $pattern) {
            $query->where('domain', 'NOT ILIKE', $pattern);
        }

        $results = $query->groupBy('domain')
            ->havingRaw('SUM(occurrences) >= 2')
            ->orderByDesc('total_mentions')
            ->get();

        return response()->json($results);
    }

    public function exportLinks(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = ContentExternalLink::query()
            ->join('content_sources', 'content_external_links.source_id', '=', 'content_sources.id')
            ->leftJoin('content_countries', 'content_external_links.country_id', '=', 'content_countries.id')
            ->select(
                'content_external_links.*',
                'content_sources.name as source_name',
                'content_countries.name as country_name'
            );

        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) $query->where('content_external_links.source_id', $source->id);
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('content_external_links.url', 'ilike', '%' . $search . '%')
                  ->orWhere('content_external_links.domain', 'ilike', '%' . $search . '%');
            });
        }
        if ($request->filled('link_type')) {
            $query->where('content_external_links.link_type', $request->input('link_type'));
        }
        if ($request->filled('is_affiliate')) {
            $query->where('content_external_links.is_affiliate', $request->boolean('is_affiliate'));
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="content-links-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['URL', 'Domain', 'Anchor Text', 'Type', 'Affiliate', 'Occurrences', 'Source', 'Country', 'Context']);

            $query->orderBy('content_external_links.domain')->chunk(500, function ($links) use ($out) {
                foreach ($links as $link) {
                    fputcsv($out, [
                        $this->csvSafe($link->url),
                        $link->domain,
                        $this->csvSafe($link->anchor_text ?? ''),
                        $link->link_type,
                        $link->is_affiliate ? 'Yes' : 'No',
                        $link->occurrences,
                        $link->source_name ?? '',
                        $link->country_name ?? '',
                        $this->csvSafe(mb_substr($link->context ?? '', 0, 200)),
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    /** Prevent CSV injection (=, +, -, @) */
    private function csvSafe(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
