<?php

namespace App\Services\Content;

/**
 * Post-processes generated HTML to fix all internal links.
 * GPT-4o invents URLs that don't exist. This sanitizer:
 * 1. Rewrites all sos-expat.com links to valid routes
 * 2. Removes www. prefix (not supported)
 * 3. Fixes country slugs to ISO codes
 * 4. Resolves {country} template variables
 * 5. Removes links to non-existent pages
 */
class LinkSanitizer
{
    // Valid internal route patterns: /{lang}-{country}/{segment}
    private const VALID_SEGMENTS = [
        'prestataires', 'providers', 'proveedores', 'anbieter', 'prestadores',
        'articles', 'articulos', 'artikel', 'artigos',
        'vie-a-letranger', 'living-abroad',
        'actualites-expats', 'expat-news',
        'outils', 'tools', 'herramientas', 'werkzeuge',
        'pays', 'countries', 'paises', 'laender',
        'annuaire', 'directory', 'directorio',
        'recherche', 'search', 'buscar',
        'sondages-expatries', 'expat-surveys',
        'programme', 'program',
        'galerie', 'gallery',
    ];

    private const LOCALE_MAP = [
        'fr' => 'fr', 'en' => 'us', 'es' => 'es', 'de' => 'de',
        'pt' => 'pt', 'ru' => 'ru', 'zh' => 'cn', 'ar' => 'sa', 'hi' => 'in',
    ];

    // Country name → ISO code for URL fixing
    private const COUNTRY_SLUGS = [
        'france' => 'fr', 'afghanistan' => 'af', 'allemagne' => 'de', 'germany' => 'de',
        'espagne' => 'es', 'spain' => 'es', 'venezuela' => 've', 'thailande' => 'th',
        'thailand' => 'th', 'japon' => 'jp', 'japan' => 'jp', 'suisse' => 'ch',
        'switzerland' => 'ch', 'belgique' => 'be', 'belgium' => 'be', 'portugal' => 'pt',
        'maroc' => 'ma', 'morocco' => 'ma', 'danemark' => 'dk', 'denmark' => 'dk',
        'tunisie' => 'tn', 'tunisia' => 'tn', 'senegal' => 'sn', 'canada' => 'ca',
        'colombie' => 'co', 'colombia' => 'co', 'bolivie' => 'bo', 'djibouti' => 'dj',
        'algerie' => 'dz', 'angola' => 'ao', 'mauritanie' => 'mr', 'taiwan' => 'tw',
        'georgie' => 'ge', 'perou' => 'pe', 'peru' => 'pe', 'liban' => 'lb',
        'lebanon' => 'lb', 'iran' => 'ir', 'israel' => 'il', 'mexique' => 'mx',
        'mexico' => 'mx', 'albanie' => 'al', 'andorre' => 'ad', 'mayotte' => 'yt',
        'sao-tome' => 'st', 'afrique-du-sud' => 'za', 'south-africa' => 'za',
    ];

    /**
     * Sanitize all links in generated HTML content.
     */
    public static function sanitize(string $html, string $language = 'fr', ?string $countryCode = null): string
    {
        $lang = strtolower(substr($language, 0, 2));
        $country = $countryCode ? strtolower($countryCode) : (self::LOCALE_MAP[$lang] ?? 'fr');
        $localePrefix = "{$lang}-{$country}";

        // 1. Fix www.sos-expat.com → sos-expat.com
        $html = str_replace('https://www.sos-expat.com', 'https://sos-expat.com', $html);
        $html = str_replace('http://www.sos-expat.com', 'https://sos-expat.com', $html);

        // 2. Fix {country} template variables in URLs
        $html = str_replace('%7Bcountry%7D', $country, $html);
        $html = str_replace('{country}', $country, $html);
        $html = str_replace('{pays}', $country, $html);

        // 3. Fix all internal links with regex
        $html = preg_replace_callback(
            '/href="(https?:\/\/(?:www\.)?sos-expat\.com\/[^"]*)"/',
            function ($match) use ($lang, $country, $localePrefix) {
                $url = $match[1];
                $url = str_replace('https://www.sos-expat.com', 'https://sos-expat.com', $url);

                $path = parse_url($url, PHP_URL_PATH) ?? '/';
                $path = trim($path, '/');
                $segments = explode('/', $path);

                // Fix country name slugs in locale prefix: /fr-france/ → /fr-fr/
                if (!empty($segments[0]) && preg_match('/^([a-z]{2})-(.+)$/', $segments[0], $m)) {
                    $urlLang = $m[1];
                    $urlCountry = $m[2];

                    // Convert country name slug to ISO code
                    if (strlen($urlCountry) > 2) {
                        $urlCountry = self::COUNTRY_SLUGS[$urlCountry] ?? $country;
                    }
                    $segments[0] = "{$urlLang}-{$urlCountry}";
                } elseif (!empty($segments[0]) && !str_contains($segments[0], '-')) {
                    // Bare segment like /france/prestataires → /fr-fr/prestataires
                    $maybeCountry = self::COUNTRY_SLUGS[$segments[0]] ?? null;
                    if ($maybeCountry) {
                        $segments[0] = "{$lang}-{$maybeCountry}";
                    }
                }

                // Validate the page segment exists
                if (count($segments) >= 2) {
                    $pageSegment = $segments[1];
                    if (!in_array($pageSegment, self::VALID_SEGMENTS) && !str_contains($pageSegment, '-')) {
                        // Unknown segment — redirect to prestataires
                        $segments[1] = $lang === 'en' ? 'providers' : 'prestataires';
                    }
                }

                // Remove /consultation, /recrutement-chatter, /blog/ etc (don't exist)
                $invalidPaths = ['consultation', 'recrutement-chatter', 'blog', 'fr/blog'];
                foreach ($invalidPaths as $invalid) {
                    if (str_contains($path, $invalid)) {
                        return 'href="https://sos-expat.com/' . $localePrefix . '/prestataires"';
                    }
                }

                return 'href="https://sos-expat.com/' . implode('/', $segments) . '"';
            },
            $html
        );

        // 4. Fix relative links that start with / (not absolute)
        $html = preg_replace_callback(
            '/href="(\/[^"]+)"/',
            function ($match) use ($localePrefix) {
                $path = $match[1];
                // /articles/... → full URL
                if (str_starts_with($path, '/articles/') || str_starts_with($path, '/vie-a-letranger/')) {
                    return 'href="https://sos-expat.com/' . $localePrefix . $path . '"';
                }
                // Other relative paths → prestataires (safe fallback)
                if (str_starts_with($path, '/fr/blog/') || str_starts_with($path, '/en/blog/')) {
                    return 'href="https://sos-expat.com/' . $localePrefix . '/prestataires"';
                }
                return $match[0]; // keep as-is if unclear
            },
            $html
        );

        return $html;
    }
}
