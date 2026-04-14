<?php

namespace Database\Seeders;

use App\Models\LandingProblem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class LandingProblemSeeder extends Seeder
{
    /**
     * Importe les 417 problèmes depuis sos_expat_fichier.json.
     * Utilise updateOrCreate sur le slug — idempotent, re-seedable sans doublons.
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

            LandingProblem::updateOrCreate(
                ['slug' => $slug],
                [
                    'title'               => $p['title_base'] ?? $slug,
                    'category'            => $p['category'] ?? 'general',
                    'subcategory'         => $p['subcategory'] ?? null,
                    'intent'              => $p['intent'] ?? 'information',
                    'urgency_score'       => (int) ($p['urgency_score'] ?? 0),
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

        // Initialiser les 4 campagnes vides si elles n'existent pas encore
        foreach (['clients', 'lawyers', 'helpers', 'matching'] as $type) {
            \App\Models\LandingCampaign::findOrCreateForType($type);
        }

        $this->command->info("LandingProblemSeeder: {$count} problèmes importés, {$skipped} ignorés.");
        $this->command->info("4 campagnes landing initialisées (clients/lawyers/helpers/matching).");
    }
}
