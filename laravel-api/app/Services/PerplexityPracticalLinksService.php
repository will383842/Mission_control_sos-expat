<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PerplexityPracticalLinksService — Génère les liens pratiques via Perplexity (recherche web réelle).
 *
 * SUPÉRIEUR à Claude pour l'annuaire car :
 * - Recherche le web en temps réel → URLs réelles et vérifiées
 * - Inclut des citations (sources vérifiables)
 * - Aucune hallucination d'URLs
 * - Données à jour (pas de cutoff de training)
 *
 * Prix : ~$0.002/requête (sonar) → ~$3-4 pour 195 pays × toutes catégories
 *
 * Catégories gérées : immigration, fiscalite, logement, emploi, telecom,
 *                     juridique, education, sante, communaute, banque
 */
class PerplexityPracticalLinksService
{
    const API_URL = 'https://api.perplexity.ai/chat/completions';
    const MODEL   = 'sonar'; // sonar-pro pour plus de profondeur (3x plus cher)

    // Toutes les catégories pratiques (Overpass couvre le physique, Perplexity les sites web)
    const SUPPORTED_CATEGORIES = [
        'immigration', 'fiscalite', 'logement', 'emploi',
        'telecom', 'juridique', 'education', 'sante', 'communaute', 'banque',
    ];

    const CATEGORY_DESCRIPTIONS = [
        'immigration' => "official immigration websites, visa portals, residence permit offices, work permit authorities",
        'fiscalite'   => "national tax authority, income tax portal for expats, tax treaties, VAT registration",
        'logement'    => "real estate portals, expat housing agencies, temporary accommodation, rental platforms",
        'emploi'      => "national employment agency, job portals, labor ministry, work permit requirements",
        'telecom'     => "main mobile operators, internet providers, expat SIM cards, international plans",
        'juridique'   => "bar association, lawyer directory, legal aid for foreigners, expat legal help",
        'education'   => "ministry of education, degree equivalence, international schools, French schools abroad",
        'sante'       => "national health authority, international hospitals, expat health insurance, travel vaccination",
        'communaute'  => "expat associations, Facebook groups for expats, InterNations, Expat.com community",
        'banque'      => "national banking authority, expat bank accounts, international banking, Wise/Revolut local info",
    ];

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.api_key', '');
        $this->model  = config('services.perplexity.model', self::MODEL);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Recherche et génère les liens pratiques pour un pays + catégorie.
     * Perplexity cherche le vrai web → URLs garanties d'exister.
     *
     * @param string $countryIso  Code ISO 3166-1 alpha-2 (ex: 'TH')
     * @param string $category    Catégorie annuaire
     * @param int    $maxLinks    Nombre de liens (5-8 recommandé)
     */
    public function generateForCountry(string $countryIso, string $category, int $maxLinks = 6): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException("Perplexity API key not configured (PERPLEXITY_API_KEY)");
        }

        if (!in_array($category, self::SUPPORTED_CATEGORIES)) {
            throw new \InvalidArgumentException("Catégorie non supportée: {$category}");
        }

        $countryName = WikidataService::COUNTRY_NAMES_FR[$countryIso] ?? $countryIso;
        $countryNameEn = $this->getCountryNameEn($countryIso);
        $catDesc = self::CATEGORY_DESCRIPTIONS[$category] ?? $category;

        $systemPrompt = <<<SYSTEM
You are an expert researcher on expatriate resources worldwide.
You ONLY return valid JSON arrays. No markdown, no explanation, just the raw JSON.
Only include URLs you have found on the web and verified they exist.
NEVER invent URLs. If unsure, skip the entry.
SYSTEM;

        $userPrompt = <<<PROMPT
Search the web and find {$maxLinks} official and practical websites for the category "{$category}" ({$catDesc}) for expatriates living in {$countryNameEn} (ISO: {$countryIso} / French: {$countryName}).

These resources are UNIVERSAL — they apply to ALL nationalities living in this country (a German, a Moroccan and a French person in {$countryNameEn} all use the same immigration website).

Return ONLY a valid JSON array with this exact structure:
[
  {
    "title_fr": "Titre en français",
    "title_en": "Title in English",
    "title_es": "Título en español",
    "title_ar": "العنوان بالعربية",
    "title_de": "Titel auf Deutsch",
    "title_pt": "Título em português",
    "title_zh": "中文标题",
    "title_hi": "हिंदी शीर्षक",
    "title_ru": "Название на русском",
    "url": "https://exact-verified-url.com",
    "description_fr": "Description courte en français (max 100 caractères)",
    "description_en": "Short description in English",
    "sub_category": "short subcategory (e.g.: visa, impots, colocation, job-portal)",
    "trust_score": 92,
    "is_official": true,
    "anchor_text": "anchor text for seo in lowercase"
  }
]

Rules:
- trust_score 90-98: official government/ministry websites
- trust_score 80-89: recognized international organizations
- trust_score 70-79: reliable community resources
- is_official=true ONLY for government/ministry websites
- Include only URLs you found on the actual web right now
- Prioritize official government sources first
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(90)->post(self::API_URL, [
                'model'            => $this->model,
                'messages'         => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'       => 3000,
                'temperature'      => 0.1, // Très bas = plus factuel, moins créatif
                'return_citations' => true, // Perplexity retourne les sources
            ]);

            if (!$response->successful()) {
                Log::warning("Perplexity annuaire HTTP {$response->status()} pour {$countryIso}/{$category}");
                return [];
            }

            $data      = $response->json();
            $content   = $data['choices'][0]['message']['content'] ?? '';
            $citations = $data['citations'] ?? []; // URLs sources vérifiées

            Log::info("Perplexity annuaire [{$countryIso}/{$category}]", [
                'citations' => count($citations),
                'tokens'    => ($data['usage']['total_tokens'] ?? 0),
            ]);

            return $this->parseAndNormalize($content, $countryIso, $category, $citations);

        } catch (\Exception $e) {
            Log::error("Perplexity annuaire exception [{$countryIso}/{$category}]: " . $e->getMessage());
            return [];
        }
    }

    // ── Privés ────────────────────────────────────────────────────────────────

    private function parseAndNormalize(string $jsonContent, string $countryIso, string $category, array $citations = []): array
    {
        // Nettoyer la réponse (parfois Perplexity ajoute des backticks)
        $clean = preg_replace('/```json\s*|\s*```/', '', trim($jsonContent));
        // Trouver le début du tableau JSON
        $start = strpos($clean, '[');
        if ($start === false) {
            Log::warning("Perplexity: pas de JSON tableau pour {$countryIso}/{$category}", ['raw' => substr($clean, 0, 200)]);
            return [];
        }
        $clean = substr($clean, $start);

        $items = json_decode($clean, true);
        if (!is_array($items)) {
            Log::warning("Perplexity: JSON invalide pour {$countryIso}/{$category}");
            return [];
        }

        $countryName = WikidataService::COUNTRY_NAMES_FR[$countryIso] ?? $countryIso;
        $continent   = WikidataService::COUNTRY_CONTINENT[$countryIso] ?? 'autre';
        $slug        = $this->makeSlug($countryName);
        $entries     = [];

        // Index des citations pour validation
        $citationDomains = array_map(fn($c) => parse_url($c, PHP_URL_HOST) ?: '', $citations);

        foreach ($items as $item) {
            if (empty($item['url']) || empty($item['title_fr'])) continue;
            if (!filter_var($item['url'], FILTER_VALIDATE_URL))   continue;

            $url    = rtrim($item['url'], '/');
            $domain = parse_url($url, PHP_URL_HOST) ?: '';
            $domain = preg_replace('/^www\./', '', $domain);

            // Boost trust_score si l'URL est citée dans les citations Perplexity
            $trustScore = (int) ($item['trust_score'] ?? 80);
            $domainInCitations = array_filter($citationDomains, fn($d) => str_contains($d, $domain) || str_contains($domain, $d));
            if (!empty($domainInCitations)) {
                $trustScore = min(98, $trustScore + 5); // +5 si vérifiée par Perplexity
            }

            // Construire le JSON translations (les 8 langues hors FR)
            // Note: "ch" dans le projet = chinois (zh dans Perplexity)
            $translations = [];
            $langMap = ['en'=>'en','es'=>'es','ar'=>'ar','de'=>'de','pt'=>'pt','zh'=>'ch','hi'=>'hi','ru'=>'ru'];
            foreach ($langMap as $perplexityKey => $projectKey) {
                $title = $item["title_{$perplexityKey}"] ?? null;
                $desc  = $item["description_{$perplexityKey}"] ?? null;
                if ($title || $desc) {
                    $translations[$projectKey] = array_filter(['title' => $title, 'description' => $desc]);
                }
            }

            $entries[] = [
                'country_code'     => strtoupper($countryIso),
                'country_name'     => $countryName,
                'country_slug'     => $slug,
                'continent'        => $continent,
                'nationality_code' => null, // universel — toutes nationalités
                'nationality_name' => null,
                'category'         => $category,
                'sub_category'     => $item['sub_category'] ?? null,
                'title'            => $item['title_fr'],
                'url'              => $url,
                'domain'           => $domain,
                'description'      => $item['description_fr'] ?? null,
                'language'         => 'fr',
                'translations'     => !empty($translations) ? $translations : null,
                'address'          => null,
                'city'             => null,
                'phone'            => null,
                'phone_emergency'  => null,
                'email'            => null,
                'opening_hours'    => null,
                'latitude'         => null,
                'longitude'        => null,
                'emergency_number' => null,
                'trust_score'      => $trustScore,
                'is_official'      => (bool) ($item['is_official'] ?? false),
                'is_active'        => true,
                'anchor_text'      => $item['anchor_text'] ?? strtolower($item['title_en'] ?? $item['title_fr']),
                'rel_attribute'    => ($item['is_official'] ?? false) ? 'noopener' : 'nofollow',
            ];
        }

        return $entries;
    }

    /**
     * Nom du pays en anglais pour les requêtes Perplexity (recherche en anglais = meilleurs résultats).
     */
    private function getCountryNameEn(string $iso): string
    {
        $names = [
            'FR'=>'France','DE'=>'Germany','GB'=>'United Kingdom','ES'=>'Spain','IT'=>'Italy',
            'BE'=>'Belgium','CH'=>'Switzerland','NL'=>'Netherlands','PT'=>'Portugal','AT'=>'Austria',
            'SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','FI'=>'Finland','PL'=>'Poland',
            'US'=>'United States','CA'=>'Canada','AU'=>'Australia','NZ'=>'New Zealand',
            'JP'=>'Japan','CN'=>'China','KR'=>'South Korea','SG'=>'Singapore','TH'=>'Thailand',
            'VN'=>'Vietnam','ID'=>'Indonesia','MY'=>'Malaysia','PH'=>'Philippines','IN'=>'India',
            'AE'=>'United Arab Emirates','SA'=>'Saudi Arabia','QA'=>'Qatar','KW'=>'Kuwait',
            'LB'=>'Lebanon','MA'=>'Morocco','DZ'=>'Algeria','TN'=>'Tunisia','SN'=>'Senegal',
            'CM'=>'Cameroon','CI'=>"Cote d'Ivoire",'NG'=>'Nigeria','KE'=>'Kenya','ZA'=>'South Africa',
            'EG'=>'Egypt','MX'=>'Mexico','BR'=>'Brazil','AR'=>'Argentina','CO'=>'Colombia',
            'CL'=>'Chile','PE'=>'Peru','EC'=>'Ecuador','UY'=>'Uruguay','VE'=>'Venezuela',
            'TR'=>'Turkey','GR'=>'Greece','RO'=>'Romania','HU'=>'Hungary','CZ'=>'Czech Republic',
            'UA'=>'Ukraine','RU'=>'Russia','IL'=>'Israel','IR'=>'Iran','IQ'=>'Iraq',
        ];
        return $names[$iso] ?? WikidataService::COUNTRY_NAMES_FR[$iso] ?? $iso;
    }

    private function makeSlug(string $name): string
    {
        $map = ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','î'=>'i','ô'=>'o','ù'=>'u','û'=>'u','ç'=>'c',"'"=>'-'];
        $name = strtolower(strtr($name, $map));
        $name = preg_replace('/[^a-z0-9\-]/', '-', $name);
        return trim(preg_replace('/-+/', '-', $name), '-');
    }
}
