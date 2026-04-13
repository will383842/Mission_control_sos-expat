<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Suggest scraper — 100% gratuit, zéro auth, zéro quota.
 *
 * Utilise l'API autocomplete non-documentée de Google (stable depuis 2006).
 * Retourne exactement ce que les internautes tapent dans Google → intent natif.
 *
 * Stratégie :
 *  1. Seeder avec ~60 amorces de questions par pays (qui/quoi/comment/faut-il…)
 *  2. Pour chaque amorce → appel Google Suggest → 8-10 suggestions
 *  3. Classifier l'intent à partir de la formulation (regex, zéro IA)
 *  4. Déduplication + scoring par position
 */
class GoogleSuggestService
{
    // Délai entre requêtes (ms) pour ne pas se faire bloquer
    private const DELAY_MS = 800;

    // Amorces de questions par langue/catégorie
    // {country} = nom du pays, {en} = "en Thaïlande" / "au Japon" / etc.
    private const SEEDS_FR = [
        // Visa & entrée
        'visa {country}',
        'visa pour {country}',
        'visa {country} touriste',
        'visa {country} long sejour',
        'entrer {en} sans visa',
        'duree visa {country}',

        // Vie quotidienne
        'vivre {en}',
        'vivre {en} expatrie',
        's installer {en}',
        'cout de la vie {en}',
        'budget mensuel {en}',
        'logement {en} expatrie',
        'louer appartement {en}',

        // Travail
        'travailler {en}',
        'travailler {en} etranger',
        'permis de travail {country}',
        'salaire moyen {en}',
        'chercher emploi {en}',

        // Fiscalité & banque
        'impots {en} expatrie',
        'ouvrir compte bancaire {en}',
        'transfert argent {country}',
        'double imposition {country}',

        // Sante
        'sante {en} expatrie',
        'assurance maladie {en}',
        'hopital {en} etranger',
        'medecin {en} francophone',

        // Immobilier
        'acheter maison {en}',
        'acheter appartement {en} etranger',
        'prix immobilier {en}',

        // Questions intent urgence
        'passeport vole {en}',
        'accident {en} etranger',
        'probleme juridique {en}',
        'arnaque {en}',
        'avocat {en} francophone',

        // Lifestyle
        'retraite {en}',
        'digital nomad {en}',
        'etudier {en}',
        'scolariser enfant {en}',

        // Sécurité
        'securite {en}',
        'est-ce dangereux {en}',
        'zones dangereuses {country}',

        // Démarches administratives
        'permis de conduire {en}',
        'inscription consulat {country}',
        'naturalisation {country}',
        'carte de sejour {country}',

        // Questions comparatives
        'meilleure banque {en}',
        'meilleure assurance {en}',
        'meilleur quartier {en}',

        // Questions avec starters explicites
        'comment s expatrier {en}',
        'comment trouver travail {en}',
        'comment ouvrir societe {en}',
        'faut il visa {country}',
        'peut on travailler {en} visa touriste',
        'combien coute visa {country}',
        'quels documents pour {en}',
        'quel budget pour vivre {en}',
        'quelle assurance {en}',
        'pourquoi expatrier {en}',
        'quand partir {en}',
    ];

    /**
     * Découvrir les suggestions Google pour un pays donné.
     *
     * @param string $countryCode  Code ISO 2 lettres (TH, FR, JP…)
     * @param string $countryName  Nom du pays en français (Thaïlande, Japon…)
     * @param string $enPrep       Préposition + pays ("en Thaïlande", "au Japon"…)
     * @param string $lang         Langue de recherche (fr, en…)
     * @return array               [{question, intent, content_type, score, source}]
     */
    public function discoverForCountry(
        string $countryCode,
        string $countryName,
        string $enPrep,
        string $lang = 'fr'
    ): array {
        $discovered = [];
        $seen = [];

        $seeds = $this->buildSeeds($countryName, $enPrep, $lang);

        foreach ($seeds as $i => $seed) {
            try {
                $suggestions = $this->fetchSuggestions($seed, $lang);

                foreach ($suggestions as $pos => $suggestion) {
                    $normalized = $this->normalize($suggestion);

                    if (isset($seen[$normalized])) continue;
                    if (!$this->isRelevant($suggestion, $countryName, $enPrep)) continue;
                    if (mb_strlen($suggestion) < 15 || mb_strlen($suggestion) > 120) continue;

                    $seen[$normalized] = true;
                    $intent = $this->classifyIntent($suggestion);
                    $contentType = $this->suggestContentType($suggestion, $intent);

                    $discovered[] = [
                        'country_code' => strtoupper($countryCode),
                        'language'     => $lang,
                        'question'     => $this->capitalizeFirst($suggestion),
                        'intent'       => $intent,
                        'content_type' => $contentType,
                        'source'       => 'google_suggest',
                        'score'        => $pos, // 0 = première suggestion = plus populaire
                    ];
                }

                // Pause naturelle entre requêtes
                if ($i < count($seeds) - 1) {
                    usleep(self::DELAY_MS * 1000 + rand(0, 400_000));
                }

            } catch (\Throwable $e) {
                Log::warning("GoogleSuggestService: failed for seed '{$seed}'", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Trier par score (position suggest = popularité estimée) puis déduplication finale
        usort($discovered, fn ($a, $b) => $a['score'] <=> $b['score']);

        return array_values($discovered);
    }

    /**
     * Appel Google Autocomplete.
     * Endpoint : https://suggestqueries.google.com/complete/search
     * Paramètres : q (requête), hl (langue interface), gl (pays), client=firefox
     */
    private function fetchSuggestions(string $query, string $lang): array
    {
        // On utilise le client "firefox" qui retourne du JSON brut (pas de JSONP)
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
            'Accept-Language' => $lang . ',' . $lang . '-FR;q=0.9,en;q=0.5',
        ])
        ->timeout(8)
        ->get('https://suggestqueries.google.com/complete/search', [
            'q'      => $query,
            'client' => 'firefox',
            'hl'     => $lang,
        ]);

        if (!$response->successful()) {
            return [];
        }

        // Réponse format : ["query", ["sug1", "sug2", ...]]
        $data = $response->json();
        return $data[1] ?? [];
    }

    /**
     * Construire les amorces de seeds pour un pays.
     */
    private function buildSeeds(string $countryName, string $enPrep, string $lang): array
    {
        $seeds = [];
        $name = mb_strtolower($countryName);
        $en   = mb_strtolower($enPrep); // "en thaïlande", "au japon"…

        foreach (self::SEEDS_FR as $template) {
            $seed = str_replace(['{country}', '{en}'], [$name, $en], $template);
            $seeds[] = $seed;
        }

        return $seeds;
    }

    /**
     * Classifier l'intent à partir de la formulation — zéro IA, pur regex.
     *
     * Ordre de priorité : urgency > transactional > commercial > informational
     */
    public function classifyIntent(string $question): string
    {
        $q = mb_strtolower($question);

        // URGENCE — problème immédiat, stress élevé
        if (preg_match('/vol[eé]|vole|urgence|arrest[eé]|accident|expuls[eé]|bloqu[eé]|perdu|perte|kidnap|enl[eè]vement|fraude|arnaque|agress|hospitalis|prison|interdit|d[eé]c[eè]s|rapatriem/u', $q)) {
            return 'urgency';
        }

        // TRANSACTIONNEL — l'utilisateur veut FAIRE quelque chose
        if (preg_match('/^(comment|étapes?|tutoriel|guide étape|procédure|dossier|formulaire|demande de|faire une demande|obtenir|créer|ouvrir|souscrire|s.inscrire|déposer|remplir|renouveler|valider)/u', $q)) {
            return 'transactional';
        }
        if (preg_match('/comment (obtenir|cr[eé]er|ouvrir|souscrire|faire|demander|remplir|renouveler|trouver|s.inscrire|d[eé]poser|valider)/u', $q)) {
            return 'transactional';
        }

        // COMMERCIAL — comparaison, choix, meilleur
        if (preg_match('/meilleur|comparatif|vs\b|versus|comparer|quel (service|op[eé]rateur|assur|banque|prestataire)|avis sur|recommand|top \d|classement|prix de|combien co[uû]te|tarif/u', $q)) {
            return 'commercial_investigation';
        }

        // LOCAL — service dans un lieu précis
        if (preg_match('/à (bangkok|paris|tokyo|dubai|lisbonne|berlin|amsterdam|toronto|sydney|singapour|hong kong|new york|madrid|barcelone|rome|vienne|mexico|buenos aires|nairobi|casablanca)/iu', $q)) {
            return 'local';
        }

        // INFORMATIONNEL — par défaut
        return 'informational';
    }

    /**
     * Suggérer le type de contenu approprié selon l'intent et la formulation.
     */
    public function suggestContentType(string $question, string $intent): string
    {
        $q = mb_strtolower($question);

        return match (true) {
            $intent === 'urgency'                          => 'pain_point',
            $intent === 'commercial_investigation'         => 'comparative',
            $intent === 'transactional'                    => 'tutorial',
            preg_match('/^(faut-?il|peut-?on|est-?ce que|quand|pourquoi|quel est|combien|quelle est la|quelle|quels sont|est-?il)/u', $q) => 'qa',
            preg_match('/\?$/', $q)                       => 'qa',
            preg_match('/guide|tout savoir|comprendre|explication|fonctionn/u', $q) => 'guide',
            default                                        => 'article',
        };
    }

    /**
     * Vérifier que la suggestion est pertinente pour le pays cible.
     */
    private function isRelevant(string $suggestion, string $countryName, string $enPrep): bool
    {
        $s    = mb_strtolower($suggestion);
        $name = mb_strtolower($countryName);
        $en   = mb_strtolower($enPrep);

        // Doit contenir le nom du pays ou la préposition+pays
        return str_contains($s, $name) || str_contains($s, $en)
            || str_contains($s, mb_substr($name, 0, 5)); // Début du nom (ex: "thaï" pour Thaïlande)
    }

    private function normalize(string $s): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
    }

    private function capitalizeFirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}
