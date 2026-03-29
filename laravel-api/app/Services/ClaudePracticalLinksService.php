<?php

namespace App\Services;

use App\Services\AI\ClaudeService;
use Illuminate\Support\Facades\Log;

/**
 * ClaudePracticalLinksService — Génère les liens pratiques officiels via Claude AI.
 *
 * Utilisé pour les catégories non couvertes par Wikidata/OpenStreetMap :
 * immigration, fiscalité, logement, emploi, telecom, juridique, éducation (sites web), communauté.
 *
 * Ces données sont universelles par pays (nationality_code = NULL) :
 * un site d'immigration en Thaïlande est le même pour TOUTES les nationalités.
 *
 * Le contenu est généré dans les 9 langues du projet via le champ translations.
 * Titre/description en FR = valeur principale, les 8 autres langues dans translations JSON.
 */
class ClaudePracticalLinksService
{
    // Catégories gérées par Claude (pas dans Wikidata/Overpass)
    const SUPPORTED_CATEGORIES = [
        'immigration', 'fiscalite', 'logement', 'emploi',
        'telecom', 'juridique', 'education', 'sante', 'communaute',
    ];

    // Description des catégories pour le prompt
    const CATEGORY_DESCRIPTIONS = [
        'immigration' => "administration de l'immigration, visas, titres de séjour, permis de travail (sites gouvernementaux officiels)",
        'fiscalite'   => "administration fiscale, déclaration d'impôts, conventions fiscales, numéro fiscal pour expatriés",
        'logement'    => "portails immobiliers majeurs, agences pour expatriés, sites de colocation et résidences temporaires",
        'emploi'      => "agences pour l'emploi, portails d'offres d'emploi, réglementation du travail, syndicats",
        'telecom'     => "opérateurs de téléphonie mobile et internet, cartes SIM expatriés, offres internationales",
        'juridique'   => "barreau national, annuaire d'avocats, aide juridictionnelle, associations d'aide aux étrangers",
        'education'   => "ministère de l'éducation, équivalences de diplômes, universités accueillant des expatriés, AEFE",
        'sante'       => "assurance maladie nationale, hôpitaux privés accueillant expatriés, assurances santé internationales",
        'communaute'  => "associations d'expatriés, groupes Facebook/WhatsApp, sites comme Expat.com, InterNations",
    ];

    public function __construct(private ClaudeService $claude) {}

    /**
     * Génère les liens pratiques pour un pays et une catégorie.
     *
     * @param string $countryIso   Code ISO 3166-1 alpha-2 (ex. 'TH')
     * @param string $category     Catégorie annuaire
     * @param int    $maxLinks     Nombre de liens à générer (5-10)
     * @return array  Tableau d'entrées normalisées pour CountryDirectory
     */
    public function generateForCountry(string $countryIso, string $category, int $maxLinks = 7): array
    {
        if (!in_array($category, self::SUPPORTED_CATEGORIES)) {
            throw new \InvalidArgumentException("Catégorie non supportée par Claude: {$category}");
        }

        $countryName = WikidataService::COUNTRY_NAMES_FR[$countryIso] ?? $countryIso;
        $catDesc     = self::CATEGORY_DESCRIPTIONS[$category] ?? $category;

        $systemPrompt = <<<SYSTEM
Tu es un expert de l'expatriation mondiale. Tu connais les ressources officielles pour expatriés dans tous les pays du monde.
Tu réponds UNIQUEMENT avec un JSON valide, sans aucun texte autour.
Ne génère que des URLs que tu connais avec certitude (pas d'URLs inventées).
SYSTEM;

        $userPrompt = <<<PROMPT
Génère une liste de {$maxLinks} liens officiels et pratiques pour la catégorie "{$category}" ({$catDesc}) pour les expatriés vivant en {$countryName} ({$countryIso}).

Ces liens sont universels : ils s'appliquent à TOUTES LES NATIONALITÉS vivant dans ce pays, quelle que soit leur origine.

Retourne un tableau JSON avec cette structure exacte (pas de markdown, juste le JSON) :
[
  {
    "title_fr": "...",
    "title_en": "...",
    "title_es": "...",
    "title_ar": "...",
    "title_de": "...",
    "title_pt": "...",
    "title_zh": "...",
    "title_hi": "...",
    "title_ru": "...",
    "url": "https://...",
    "description_fr": "Description courte en français (max 120 caractères)",
    "description_en": "Short description in English",
    "sub_category": "sous-catégorie courte (ex: visa, impots, colocation)",
    "trust_score": 85,
    "is_official": true,
    "anchor_text": "texte d'ancre SEO en minuscules"
  }
]

Règles :
- Priorité aux sites GOUVERNEMENTAUX officiels (is_official=true, trust_score 85-98)
- Inclure aussi des ressources pratiques fiables (trust_score 70-84)
- trust_score 90+ = ministère/gouvernement officiel
- trust_score 80-89 = organisation reconnue
- trust_score 70-79 = ressource communautaire fiable
- Si tu n'es pas sûr d'une URL, ne l'inclus pas
- Ne génère que des vraies URLs vérifiables
PROMPT;

        $result = $this->claude->complete($systemPrompt, $userPrompt, [
            'model'       => 'claude-haiku-4-5-20251001', // Haiku = rapide et économique
            'temperature' => 0.2,
            'max_tokens'  => 3000,
        ]);

        if (!$result['success']) {
            Log::warning("Claude practical links failed for {$countryIso}/{$category}: " . ($result['error'] ?? ''));
            return [];
        }

        return $this->parseAndNormalize($result['content'], $countryIso, $category);
    }

    // ── Privés ────────────────────────────────────────────────────────────────

    private function parseAndNormalize(string $jsonContent, string $countryIso, string $category): array
    {
        // Nettoyer le JSON (Claude peut ajouter des backticks)
        $clean = preg_replace('/```json\s*|\s*```/', '', trim($jsonContent));
        $clean = preg_replace('/^[^[\{]*/', '', $clean);

        $items = json_decode($clean, true);
        if (!is_array($items)) {
            Log::warning("Claude practical links: JSON invalide pour {$countryIso}/{$category}", ['raw' => substr($jsonContent, 0, 200)]);
            return [];
        }

        $countryName = WikidataService::COUNTRY_NAMES_FR[$countryIso] ?? $countryIso;
        $continent   = WikidataService::COUNTRY_CONTINENT[$countryIso] ?? 'autre';
        $slug        = $this->makeSlug($countryName);
        $entries     = [];

        foreach ($items as $item) {
            if (empty($item['url']) || empty($item['title_fr'])) continue;
            if (!filter_var($item['url'], FILTER_VALIDATE_URL))   continue;

            $domain = parse_url($item['url'], PHP_URL_HOST) ?: '';
            $domain = preg_replace('/^www\./', '', $domain);

            // Construire le JSON translations avec les 8 autres langues
            // Note : "ch" dans le projet = chinois (zh dans Wikidata/Claude)
            $translations = [];
            $langMap = ['en'=>'en','es'=>'es','ar'=>'ar','de'=>'de','pt'=>'pt','zh'=>'ch','hi'=>'hi','ru'=>'ru'];
            foreach ($langMap as $claudeKey => $projectKey) {
                $title = $item["title_{$claudeKey}"] ?? null;
                $desc  = $item["description_{$claudeKey}"] ?? null;
                if ($title || $desc) {
                    $translations[$projectKey] = array_filter(['title' => $title, 'description' => $desc]);
                }
            }

            $entries[] = [
                'country_code'     => strtoupper($countryIso),
                'country_name'     => $countryName,
                'country_slug'     => $slug,
                'continent'        => $continent,
                'nationality_code' => null, // universel pour toutes nationalités
                'nationality_name' => null,
                'category'         => $category,
                'sub_category'     => $item['sub_category'] ?? null,
                'title'            => $item['title_fr'],
                'url'              => $item['url'],
                'domain'           => $domain,
                'description'      => $item['description_fr'] ?? null,
                'language'         => 'fr',
                'translations'     => !empty($translations) ? $translations : null, // array brut — cast 'array' du model encode
                'address'          => null,
                'city'             => null,
                'phone'            => null,
                'phone_emergency'  => null,
                'email'            => null,
                'opening_hours'    => null,
                'latitude'         => null,
                'longitude'        => null,
                'emergency_number' => null,
                'trust_score'      => (int) ($item['trust_score'] ?? 80),
                'is_official'      => (bool) ($item['is_official'] ?? false),
                'is_active'        => true,
                'anchor_text'      => $item['anchor_text'] ?? strtolower($item['title_fr']),
                'rel_attribute'    => ($item['is_official'] ?? false) ? 'noopener' : 'nofollow',
            ];
        }

        return $entries;
    }

    private function makeSlug(string $name): string
    {
        $map = ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','î'=>'i','ô'=>'o','ù'=>'u','û'=>'u','ç'=>'c',"'"=>'-'];
        $name = strtolower(strtr($name, $map));
        $name = preg_replace('/[^a-z0-9\-]/', '-', $name);
        return trim(preg_replace('/-+/', '-', $name), '-');
    }
}
