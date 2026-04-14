<?php

namespace Database\Seeders;

use App\Models\LandingCampaign;
use App\Models\LandingProblem;
use Illuminate\Database\Seeder;

class LandingProblemSeeder extends Seeder
{
    /**
     * Importe les 417 problèmes depuis sos_expat_fichier.json.
     * Utilise updateOrCreate sur le slug — idempotent, re-seedable sans doublons.
     * Corrige les urgency_scores (le JSON source n'a que 1-5, les cas critiques méritent 8-10).
     */
    public function run(): void
    {
        $jsonPath = base_path('database/data/sos_expat_problems.json');

        if (! file_exists($jsonPath)) {
            $this->command->warn("Fichier non trouvé : {$jsonPath}");
            $this->command->info("Créez database/data/sos_expat_problems.json avec le contenu de sos_expat_fichier.json");
            return;
        }

        $raw  = file_get_contents($jsonPath);
        $data = json_decode($raw, true);

        if (! isset($data['problems'])) {
            $this->command->error("Format JSON invalide — clé 'problems' manquante.");
            return;
        }

        $problems = $data['problems'];
        $count    = 0;
        $skipped  = 0;

        foreach ($problems as $p) {
            $slug = $p['slug_base'] ?? ($p['id'] ?? null);

            if (! $slug) {
                $skipped++;
                continue;
            }

            $category     = $p['category'] ?? 'general';
            $rawScore     = (int) ($p['urgency_score'] ?? 0);
            $correctedScore = $this->correctUrgencyScore($slug, $category, $rawScore);

            LandingProblem::updateOrCreate(
                ['slug' => $slug],
                [
                    'title'               => $p['title_base'] ?? $slug,
                    'category'            => $category,
                    'subcategory'         => $p['subcategory'] ?? null,
                    'intent'              => $p['intent'] ?? 'information',
                    'urgency_score'       => $correctedScore,
                    'business_value'      => $p['business_value'] ?? 'mid',
                    'product_route'       => $p['product_route'] ?? 'mixed',
                    'needs_lawyer'        => (bool) ($p['needs_lawyer'] ?? false),
                    'needs_helper'        => (bool) ($p['needs_helper'] ?? false),
                    'monetizable'         => (bool) ($p['monetizable'] ?? true),
                    'lp_angle'            => $p['lp_angle'] ?? null,
                    'faq_seed'            => $p['faq_seed'] ?? null,
                    'search_queries_seed' => $p['search_queries_seed'] ?? [],
                    'user_profiles'       => $p['user_profiles'] ?? [],
                    'tags'                => $p['tags'] ?? [],
                    'status'              => $p['status'] ?? 'active',
                ]
            );

            $count++;
        }

        // Initialiser les 8 campagnes landing si elles n'existent pas encore
        $types = ['clients', 'lawyers', 'helpers', 'matching', 'category_pillar', 'profile', 'emergency', 'nationality'];
        foreach ($types as $type) {
            LandingCampaign::findOrCreateForType($type);
        }

        $this->command->info("LandingProblemSeeder: {$count} problèmes importés, {$skipped} ignorés (urgency_scores corrigés).");
        $this->command->info("8 campagnes landing initialisées : " . implode(', ', $types));
    }

    // ============================================================
    // Correction des urgency_scores
    // ============================================================

    /**
     * Corrige l'urgency_score issu du JSON source (limité à 1-5).
     * Les cas vraiment critiques doivent avoir 8-10 pour que le générateur
     * utilise le bon ton d'urgence et que l'admin puisse filtrer par priorité.
     *
     * Logique par priorité décroissante :
     * 1. Overrides par slug (cas extrêmes — vie en danger, liberté menacée)
     * 2. Minimums par catégorie
     * 3. Décalage +1 universel (évite que tout soit "informatif")
     */
    private function correctUrgencyScore(string $slug, string $category, int $original): int
    {
        // ── 1. Overrides par slug ───────────────────────────────────────────
        // Score 10 — Danger de mort immédiat
        $score10 = [
            'urgence-medicale', 'urgence-medicale-etranger',
            'accident-grave', 'accident-mortel',
            'agression-physique', 'agression-violente',
            'tentative-de-meurtre', 'homicide',
        ];
        if ($this->slugMatches($slug, $score10)) {
            return 10;
        }

        // Score 9 — Liberté / intégrité physique menacée
        $score9 = [
            'arrestation', 'arrestation-arbitraire', 'detention-arbitraire',
            'emprisonnement', 'garde-a-vue',
            'violence-conjugale', 'violence-domestique',
            'kidnapping', 'enlevement',
            'alerte-securite', 'menace-securite',
            'hospitalisation-urgence', 'evacuation-medicale', 'rapatriement-medical',
            'evacuation-urgence', 'crise-psychique', 'tentative-suicide',
        ];
        if ($this->slugMatches($slug, $score9)) {
            return 9;
        }

        // Score 8 — Situation critique, délais serrés
        $score8 = [
            'refus-visa-urgence', 'deportation-imminente', 'expulsion-imminente',
            'vol-documents', 'perte-passeport', 'perte-papiers',
            'perte-carte-identite', 'vol-passeport',
            'compte-bancaire-bloque', 'gel-compte', 'fraude-bancaire-urgence',
            'disparition-personne', 'enfant-disparu',
            'catastrophe-naturelle', 'zone-guerre', 'conflit-arme',
        ];
        if ($this->slugMatches($slug, $score8)) {
            return 8;
        }

        // Score 7 — Urgent mais gérable
        $score7 = [
            'accident-voiture', 'accident-moto', 'accident-velo',
            'hospitalisation', 'operation-chirurgicale',
            'visa-expire', 'overstay', 'regularisation-urgence',
            'garde-a-vue-routine', 'controle-police',
            'logement-expulsion', 'expulsion-locative',
        ];
        if ($this->slugMatches($slug, $score7)) {
            return 7;
        }

        // ── 2. Minimums par catégorie ────────────────────────────────────────
        $categoryMinimums = [
            'securite'           => 7,
            'police_justice'     => 7,
            'geopolitique_crise' => 7,
            'sante'              => 6,
        ];

        if (isset($categoryMinimums[$category]) && $original < $categoryMinimums[$category]) {
            return $categoryMinimums[$category];
        }

        // ── 3. Décalage universel +1 ─────────────────────────────────────────
        // Le JSON source plafonne à 5. Décaler d'1 point donne une meilleure
        // distribution (2=très informatif, 3=informatif, 4=modéré, 5=important, 6=urgent).
        return min(10, max(1, $original + 1));
    }

    /**
     * Vérifie si le slug contient l'un des mots-clés de la liste (sous-chaîne).
     * Permet de matcher 'urgence-medicale-etranger' avec le pattern 'urgence-medicale'.
     */
    private function slugMatches(string $slug, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($slug, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
