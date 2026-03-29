<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OverpassService — Récupère les institutions physiques depuis OpenStreetMap.
 *
 * Couvre : hôpitaux, banques, universités, gares, aéroports, urgences, pharmacies.
 * Ces données sont universelles (pas liées à une nationalité) → nationality_code = NULL.
 * Endpoint : https://overpass-api.de/api/interpreter
 */
class OverpassService
{
    const ENDPOINT   = 'https://overpass-api.de/api/interpreter';
    const USER_AGENT = 'MissionControlSosExpat/1.0 (contact@sos-expat.com)';
    const LIMIT      = 50; // max résultats par requête (pour les grandes villes)

    // Mapping catégorie → tags OSM
    const CATEGORY_TAGS = [
        'sante' => [
            ['amenity' => 'hospital'],
            ['amenity' => 'clinic'],
        ],
        'hopitaux' => [
            ['amenity' => 'hospital'],
        ],
        'banque' => [
            ['amenity' => 'bank'],
        ],
        'education' => [
            ['amenity' => 'university'],
            ['amenity' => 'college'],
        ],
        'transport' => [
            ['railway' => 'station'],
            ['aeroway' => 'aerodrome'],
            ['aeroway' => 'international_airport'],
        ],
        'urgences' => [
            ['amenity' => 'police'],
            ['amenity' => 'fire_station'],
            ['amenity' => 'hospital'],
        ],
        'communaute' => [
            ['amenity' => 'community_centre'],
            ['amenity' => 'social_centre'],
        ],
    ];

    // Catégories supportées par OpenStreetMap
    // Note: 'hopitaux' est un alias → stocké sous category='sante', sub_category='hopitaux'
    const SUPPORTED_CATEGORIES = ['sante', 'hopitaux', 'banque', 'education', 'transport', 'urgences'];

    /**
     * Récupère les institutions pour un pays et une catégorie.
     *
     * @param string $countryIso  Code ISO 3166-1 alpha-2 (ex. 'TH')
     * @param string $category    Catégorie annuaire (ex. 'hopitaux')
     * @param int    $limit       Nombre max de résultats
     */
    public function getByCountryAndCategory(string $countryIso, string $category, int $limit = self::LIMIT): array
    {
        $tags = self::CATEGORY_TAGS[$category] ?? null;
        if (!$tags) return [];

        $results = [];
        foreach ($tags as $tag) {
            $key   = array_key_first($tag);
            $value = $tag[$key];
            $items = $this->queryOverpass($countryIso, $key, $value, (int) ceil($limit / count($tags)));
            $results = array_merge($results, $items);
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Normalise les résultats OSM en entrées CountryDirectory.
     */
    public function normalizeResults(array $elements, string $countryIso, string $category): array
    {
        $countryName = WikidataService::COUNTRY_NAMES_FR[$countryIso] ?? $countryIso;
        $continent   = WikidataService::COUNTRY_CONTINENT[$countryIso] ?? 'autre';
        $slug        = $this->makeSlug($countryName);
        $entries     = [];

        foreach ($elements as $el) {
            $tags = $el['tags'] ?? [];

            // Doit avoir un nom
            $name = $tags['name'] ?? $tags['name:en'] ?? $tags['name:fr'] ?? null;
            if (!$name) continue;

            // Préférer la version internationale
            $nameEn = $tags['name:en'] ?? null;
            $nameFr = $tags['name:fr'] ?? $name;

            $website = $tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? null;
            $phone   = $tags['phone'] ?? $tags['contact:phone'] ?? null;
            $email   = $tags['email'] ?? $tags['contact:email'] ?? null;
            $hours   = $tags['opening_hours'] ?? null;
            $street  = isset($tags['addr:street']) ? trim(($tags['addr:housenumber'] ?? '') . ' ' . $tags['addr:street']) : null;
            $city    = $tags['addr:city'] ?? null;

            // GPS
            $lat = $lon = null;
            if ($el['type'] === 'node') {
                $lat = $el['lat'] ?? null;
                $lon = $el['lon'] ?? null;
            } elseif (isset($el['center'])) {
                $lat = $el['center']['lat'] ?? null;
                $lon = $el['center']['lon'] ?? null;
            }

            // Construire une URL si pas de website (lien Google Maps)
            $url = $website;
            if (!$url && $lat && $lon) {
                $url = "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lon}#map=17/{$lat}/{$lon}";
            }
            if (!$url) continue; // skip sans URL

            $domain  = parse_url($url, PHP_URL_HOST) ?: 'openstreetmap.org';
            $domain  = preg_replace('/^www\./', '', $domain);
            $isOsm   = str_contains($url, 'openstreetmap.org');
            $anchor  = strtolower($nameFr . ' ' . $countryName);

            // Traductions disponibles
            $translations = [];
            if ($nameEn && $nameEn !== $nameFr) $translations['en'] = ['title' => $nameEn];
            foreach (['ar', 'de', 'es', 'pt', 'zh', 'hi', 'ru'] as $lang) {
                if (!empty($tags["name:{$lang}"])) {
                    $langKey = $lang === 'zh' ? 'ch' : $lang;
                    $translations[$langKey] = ['title' => $tags["name:{$lang}"]];
                }
            }

            // Normaliser la catégorie : 'hopitaux' est stocké sous 'sante' (sub_category='hopitaux')
            $storedCategory    = ($category === 'hopitaux') ? 'sante' : $category;
            $storedSubCategory = $tags['amenity'] ?? $tags['railway'] ?? $tags['aeroway'] ?? null;
            if ($category === 'hopitaux') $storedSubCategory = 'hopitaux';

            $entries[] = [
                'country_code'     => strtoupper($countryIso),
                'country_name'     => $countryName,
                'country_slug'     => $slug,
                'continent'        => $continent,
                'nationality_code' => null, // ressource universelle — toutes nationalités
                'nationality_name' => null,
                'category'         => $storedCategory,
                'sub_category'     => $storedSubCategory,
                'title'            => $nameFr,
                'url'              => $url,
                'domain'           => $domain,
                'description'      => null,
                'language'         => 'fr',
                'translations'     => !empty($translations) ? $translations : null, // array brut
                'address'          => $street,
                'city'             => $city,
                'phone'            => $phone,
                'email'            => $email,
                'opening_hours'    => $hours,
                'latitude'         => $lat,
                'longitude'        => $lon,
                'emergency_number' => null,
                'trust_score'      => $isOsm ? 70 : 80,
                'is_official'      => !$isOsm,
                'is_active'        => true,
                'anchor_text'      => $anchor,
                'rel_attribute'    => 'noopener',
            ];
        }

        return $entries;
    }

    // ── Privés ────────────────────────────────────────────────────────────────

    private function queryOverpass(string $countryIso, string $tagKey, string $tagValue, int $limit): array
    {
        $query = <<<OVERPASS
[out:json][timeout:60];
area["ISO3166-1"="{$countryIso}"][admin_level=2]->.searchArea;
(
  node["{$tagKey}"="{$tagValue}"](area.searchArea);
  way["{$tagKey}"="{$tagValue}"](area.searchArea);
);
out center {$limit};
OVERPASS;

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(90)
                ->asForm()
                ->post(self::ENDPOINT, ['data' => $query]);

            if (!$response->successful()) {
                Log::warning("Overpass HTTP {$response->status()} pour {$countryIso} [{$tagKey}={$tagValue}]");
                return [];
            }

            return $response->json('elements', []);
        } catch (\Exception $e) {
            Log::error("Overpass exception : " . $e->getMessage());
            return [];
        }
    }

    private function makeSlug(string $name): string
    {
        $map = ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i',
                'ô'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c',"'"=>'-',"'"=>'-'];
        $name = strtolower(strtr($name, $map));
        $name = preg_replace('/[^a-z0-9\-]/', '-', $name);
        return trim(preg_replace('/-+/', '-', $name), '-');
    }
}
