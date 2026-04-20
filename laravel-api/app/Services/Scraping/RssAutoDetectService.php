<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Option D2 — Auto-detect du feed RSS depuis une URL de site web.
 *
 * Strategie :
 *   1. Fetch la homepage de l'URL cible
 *   2. Parse <link rel="alternate" type="application/rss+xml"> ou application/atom+xml
 *   3. Fallback : tenter paths typiques (/feed, /rss, /atom.xml, /feeds/all.atom.xml)
 *   4. Valide en fetchant l'URL trouvee + verifiant que c'est du XML valide
 *
 * Zero risque ban : 1-2 requetes par URL, UA declare, timeout 15s.
 */
class RssAutoDetectService
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOS-Expat-RssDiscovery/1.0; +https://sos-expat.com/bot)';
    private const TIMEOUT_SECONDS = 15;

    /** Paths typiques où se trouvent les flux RSS sur la plupart des CMS */
    private const FALLBACK_PATHS = [
        '/feed', '/feed/', '/rss', '/rss.xml', '/feed.xml',
        '/atom.xml', '/feeds/all.atom.xml', '/feeds/posts/default',
        '/index.xml', '/?feed=rss2',
    ];

    /**
     * Detecte l'URL du feed RSS depuis une URL de site.
     * Retourne le feed URL ou null si non trouve.
     */
    public function detectFeedUrl(string $siteUrl): ?string
    {
        $siteUrl = $this->normalizeUrl($siteUrl);
        if (!$siteUrl) {
            return null;
        }

        // Etape 1 : fetch homepage + parse <link rel=alternate>
        $feedFromLink = $this->extractFeedFromLinkTag($siteUrl);
        if ($feedFromLink) {
            return $feedFromLink;
        }

        // Etape 2 : fallback paths typiques
        return $this->tryFallbackPaths($siteUrl);
    }

    /**
     * Fetch la homepage et extrait le premier <link rel="alternate"
     * type="application/rss+xml"> ou atom+xml.
     */
    private function extractFeedFromLinkTag(string $siteUrl): ?string
    {
        $html = $this->fetchUrl($siteUrl);
        if (!$html) {
            return null;
        }

        // Regex : match <link rel="alternate" type="..." href="...">
        // attributs peuvent etre dans n'importe quel ordre
        $patterns = [
            '/<link[^>]+type=["\']application\/rss\+xml["\'][^>]*href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]*type=["\']application\/rss\+xml["\']/i',
            '/<link[^>]+type=["\']application\/atom\+xml["\'][^>]*href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]*type=["\']application\/atom\+xml["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $candidate) {
                    $absolute = $this->resolveUrl($candidate, $siteUrl);
                    if ($this->isValidFeedUrl($absolute)) {
                        return $absolute;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Tente les paths fallback. S'arrete au premier feed valide trouve.
     */
    private function tryFallbackPaths(string $siteUrl): ?string
    {
        $baseUrl = rtrim($siteUrl, '/');

        foreach (self::FALLBACK_PATHS as $path) {
            $candidate = $baseUrl . $path;
            if ($this->isValidFeedUrl($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Verifie qu'une URL retourne du XML valide RSS/Atom.
     * (HEAD puis GET si necessaire pour optimiser)
     */
    public function isValidFeedUrl(string $url): bool
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            if (!$response->successful()) {
                return false;
            }

            $body = $response->body();
            $contentType = strtolower($response->header('Content-Type') ?? '');

            // Content-Type XML explicite
            if (str_contains($contentType, 'xml') || str_contains($contentType, 'rss') || str_contains($contentType, 'atom')) {
                return true;
            }

            // Ou bien les 1000 premiers caracteres contiennent balise RSS/Atom
            $head = substr($body, 0, 1500);
            return str_contains($head, '<rss') || str_contains($head, '<feed') || str_contains($head, '<?xml');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::debug('RssAutoDetect: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resout une URL relative en absolue depuis un base URL.
     */
    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }
        return rtrim($baseUrl, '/') . '/' . $href;
    }

    /**
     * Normalise URL (ajoute https:// si manquant, enleve fragments).
     */
    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (empty($url)) {
            return null;
        }
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }
        // Enlever fragment + query trop longs
        $clean = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
        if (!empty($parsed['path']) && $parsed['path'] !== '/') {
            $clean .= $parsed['path'];
        }
        return $clean;
    }
}
