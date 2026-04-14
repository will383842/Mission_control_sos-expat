<?php

namespace App\Services\Seo;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use App\Models\LandingPage;
use App\Models\PressRelease;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * XML Sitemap generation with hreflang alternate links.
 */
class SitemapService
{
    private const BASE_URL = 'https://www.sos-expat.com';
    private const MAX_URLS_PER_SITEMAP = 50000;

    /**
     * Generate the full XML sitemap.
     */
    public function generate(): string
    {
        try {
            $urls = [];

            // Collect all published content
            $articles = GeneratedArticle::published()->get();
            foreach ($articles as $article) {
                $urls[] = $this->buildUrlEntry($article->url, $article->updated_at, 'weekly', 0.8, $article->hreflang_map);
            }

            $comparatives = Comparative::published()->get();
            foreach ($comparatives as $comparative) {
                $url = "/{$comparative->language}/comparatif/{$comparative->slug}";
                $urls[] = $this->buildUrlEntry($url, $comparative->updated_at, 'monthly', 0.7, $comparative->hreflang_map);
            }

            $landingPages = LandingPage::published()->get();
            foreach ($landingPages as $landing) {
                $url = "/{$landing->language}/{$landing->slug}";
                $urls[] = $this->buildUrlEntry($url, $landing->updated_at, 'monthly', 0.9, $landing->hreflang_map);
            }

            $pressReleases = PressRelease::published()->get();
            foreach ($pressReleases as $press) {
                $url = "/{$press->language}/communique/{$press->slug}";
                $urls[] = $this->buildUrlEntry($url, $press->updated_at, 'yearly', 0.5, $press->hreflang_map);
            }

            // If too many URLs, generate sitemap index instead
            if (count($urls) > self::MAX_URLS_PER_SITEMAP) {
                return $this->generateIndex();
            }

            $xml = $this->buildSitemapXml($urls);

            Log::info('Sitemap generated', ['url_count' => count($urls)]);

            return $xml;
        } catch (\Throwable $e) {
            Log::error('Sitemap generation failed', ['message' => $e->getMessage()]);

            return $this->buildSitemapXml([]);
        }
    }

    /**
     * Generate sitemap index with sub-sitemaps per language + sitemaps landing pages dédiés.
     *
     * Structure finale :
     * - sitemap-{lang}.xml          → articles + comparatifs (per language)
     * - sitemap-landing-{lang}.xml  → landing pages avec image sitemap (per language)
     * - sitemap-index.xml           → master index
     */
    public function generateIndex(): string
    {
        try {
            $languages = GeneratedArticle::published()
                ->distinct()
                ->pluck('language')
                ->toArray();

            // Inclure aussi les langues des LPs même sans articles
            $lpLanguages = LandingPage::published()
                ->distinct()
                ->pluck('language')
                ->toArray();
            $allLanguages = array_unique(array_merge($languages, $lpLanguages));

            $sitemaps = [];

            foreach ($allLanguages as $lang) {
                // ── Sitemap articles + comparatifs ───────────────────
                $articleUrls = [];

                $articles = GeneratedArticle::published()->language($lang)->get();
                foreach ($articles as $article) {
                    $articleUrls[] = $this->buildUrlEntry($article->url, $article->updated_at, 'weekly', 0.8, $article->hreflang_map);
                }

                $comparatives = Comparative::published()->language($lang)->get();
                foreach ($comparatives as $comparative) {
                    $url = "/{$comparative->language}/comparatif/{$comparative->slug}";
                    $articleUrls[] = $this->buildUrlEntry($url, $comparative->updated_at, 'monthly', 0.7, $comparative->hreflang_map);
                }

                if (!empty($articleUrls)) {
                    $filename = "sitemap-{$lang}.xml";
                    Storage::disk('public')->put($filename, $this->buildSitemapXml($articleUrls));
                    $sitemaps[] = ['loc' => self::BASE_URL . '/storage/' . $filename, 'lastmod' => now()->toDateString()];
                }

                // ── Sitemap landing pages dédié (avec image extension) ────
                $lpUrls = [];
                $landingPages = LandingPage::published()->language($lang)->get();

                foreach ($landingPages as $landing) {
                    $url      = "/{$landing->language}/{$landing->slug}";
                    $priority = $this->lpPriority($landing->audience_type ?? 'clients');
                    $freq     = $this->lpChangefreq($landing->audience_type ?? 'clients');
                    // Hreflang conditionnel — seulement les langues où la traduction EXISTE
                    $hreflang = $this->filterExistingHreflang($landing->hreflang_map ?? [], $landing->id);

                    $entry = $this->buildUrlEntry($url, $landing->updated_at, $freq, $priority, $hreflang);

                    // Image sitemap extension (meilleur indexing des images)
                    if (! empty($landing->featured_image_url)) {
                        $entry['image'] = [
                            'loc'     => $landing->featured_image_url,
                            'title'   => $landing->title ?? '',
                            'caption' => $landing->featured_image_alt ?? $landing->title ?? '',
                        ];
                    }

                    $lpUrls[] = $entry;
                }

                if (!empty($lpUrls)) {
                    $filename = "sitemap-landing-{$lang}.xml";
                    Storage::disk('public')->put($filename, $this->buildLandingSitemapXml($lpUrls));
                    $sitemaps[] = ['loc' => self::BASE_URL . '/storage/' . $filename, 'lastmod' => now()->toDateString()];
                }
            }

            // Build index XML
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($sitemaps as $sitemap) {
                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>" . htmlspecialchars($sitemap['loc']) . "</loc>\n";
                $xml .= "    <lastmod>{$sitemap['lastmod']}</lastmod>\n";
                $xml .= "  </sitemap>\n";
            }
            $xml .= "</sitemapindex>\n";

            Log::info('Sitemap index generated', [
                'sitemaps_count' => count($sitemaps),
                'languages'      => $allLanguages,
            ]);

            return $xml;
        } catch (\Throwable $e) {
            Log::error('Sitemap index generation failed', ['message' => $e->getMessage()]);
            return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
        }
    }

    /**
     * Génère un sitemap XML dédié aux landing pages avec image sitemap extension.
     * Namespace: xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
     */
    private function buildLandingSitemapXml(array $urls): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($urls as $entry) {
            $loc = str_starts_with($entry['loc'], 'http')
                ? $entry['loc']
                : self::BASE_URL . $entry['loc'];

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";

            // hreflang alternatifs (conditionnel — seulement traductions existantes)
            if (! empty($entry['hreflang_map'])) {
                foreach ($entry['hreflang_map'] as $lang => $path) {
                    $href = str_starts_with($path, 'http') ? $path : self::BASE_URL . $path;
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($href) . '" />' . "\n";
                }
            }

            // Image sitemap extension (Google indexe les images séparément)
            if (! empty($entry['image'])) {
                $imgLoc     = htmlspecialchars($entry['image']['loc'] ?? '');
                $imgTitle   = htmlspecialchars($entry['image']['title'] ?? '');
                $imgCaption = htmlspecialchars($entry['image']['caption'] ?? '');
                if ($imgLoc) {
                    $xml .= "    <image:image>\n";
                    $xml .= "      <image:loc>{$imgLoc}</image:loc>\n";
                    if ($imgTitle)   $xml .= "      <image:title>{$imgTitle}</image:title>\n";
                    if ($imgCaption) $xml .= "      <image:caption>{$imgCaption}</image:caption>\n";
                    $xml .= "    </image:image>\n";
                }
            }

            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";
        return $xml;
    }

    /**
     * Priorité SEO par audience type.
     * Emergency = 1.0 (contenu le plus urgent/important),
     * category_pillar = 0.95 (pages piliers = meilleur ROI SEO long terme),
     * etc.
     */
    private function lpPriority(string $audienceType): float
    {
        return match ($audienceType) {
            'emergency'       => 1.0,
            'category_pillar' => 0.95,
            'nationality'     => 0.90,
            'profile'         => 0.85,
            'clients'         => 0.85,
            'matching'        => 0.80,
            'lawyers'         => 0.75,
            'helpers'         => 0.75,
            default           => 0.80,
        };
    }

    /**
     * Fréquence de crawl par audience type.
     */
    private function lpChangefreq(string $audienceType): string
    {
        return match ($audienceType) {
            'emergency'       => 'weekly',   // Situations d'urgence peuvent évoluer
            'matching'        => 'weekly',   // Pages conversion gardées fraîches
            'category_pillar' => 'monthly',
            'nationality'     => 'monthly',
            'profile'         => 'monthly',
            'clients'         => 'monthly',
            'lawyers'         => 'monthly',
            'helpers'         => 'monthly',
            default           => 'monthly',
        };
    }

    /**
     * Filtre la hreflang_map pour n'inclure que les langues où la traduction EXISTE réellement.
     * Évite d'envoyer des hreflang vers des URLs 404 (pénalité Google).
     */
    private function filterExistingHreflang(array $hreflangMap, int $landingId): array
    {
        if (empty($hreflangMap)) {
            return [];
        }

        // Slugs effectivement en base pour cet ID et ses variantes
        $existingSlugs = LandingPage::where(function ($q) use ($landingId) {
            $q->where('id', $landingId)
              ->orWhere('parent_id', $landingId);
        })
        ->published()
        ->pluck('language', 'slug') // slug → language
        ->toArray();

        if (empty($existingSlugs)) {
            // Pas de traductions connues — retourner seulement la langue courante + x-default
            return array_filter($hreflangMap, fn ($url, $lang) => $lang === 'x-default', ARRAY_FILTER_USE_BOTH);
        }

        $existingLanguages = array_values($existingSlugs);

        return array_filter(
            $hreflangMap,
            fn ($url, $lang) => $lang === 'x-default' || in_array($lang, $existingLanguages),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Save the sitemap to disk and return the file path.
     */
    public function saveToDisk(): string
    {
        try {
            $xml = $this->generate();
            $path = 'sitemap.xml';

            Storage::disk('public')->put($path, $xml);

            $fullPath = Storage::disk('public')->path($path);

            Log::info('Sitemap saved to disk', ['path' => $fullPath]);

            return $fullPath;
        } catch (\Throwable $e) {
            Log::error('Sitemap save failed', ['message' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Build a URL entry for the sitemap.
     */
    private function buildUrlEntry(string $url, $lastmod, string $changefreq, float $priority, ?array $hreflangMap = null): array
    {
        return [
            'loc' => $url,
            'lastmod' => $lastmod ? (is_string($lastmod) ? $lastmod : $lastmod->toDateString()) : now()->toDateString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
            'hreflang_map' => $hreflangMap,
        ];
    }

    /**
     * Build the XML sitemap string from URL entries.
     */
    private function buildSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $entry) {
            $loc = str_starts_with($entry['loc'], 'http')
                ? $entry['loc']
                : self::BASE_URL . $entry['loc'];

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";

            // Add hreflang alternate links
            if (!empty($entry['hreflang_map'])) {
                foreach ($entry['hreflang_map'] as $lang => $path) {
                    $href = str_starts_with($path, 'http') ? $path : self::BASE_URL . $path;
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($href) . '" />' . "\n";
                }
            }

            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return $xml;
    }
}
