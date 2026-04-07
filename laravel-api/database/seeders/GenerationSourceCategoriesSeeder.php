<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds generation_source_categories — REQUIRED for orchestrator auto-pilot.
 *
 * Without these categories, POST /generation-sources/{slug}/trigger returns 404.
 * Each category maps to a content type that the RunOrchestratorCycleJob dispatches.
 */
class GenerationSourceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'art-mots-cles',          'name' => 'Art Mots Cles',           'icon' => '🔑', 'description' => 'Articles a partir de mots-cles generiques'],
            ['slug' => 'longues-traines',         'name' => 'Art Longues Traines',     'icon' => '🎯', 'description' => 'Articles longue traine avec intention de recherche'],
            ['slug' => 'villes',                  'name' => 'Fiches Villes',           'icon' => '🏙️', 'description' => 'Guides piliers par ville (1159 villes)'],
            ['slug' => 'comparatives',            'name' => 'Comparatifs SEO',         'icon' => '⚖️', 'description' => 'Comparatifs X vs Y objectifs'],
            ['slug' => 'affiliate-comparatives',  'name' => 'Comparatifs Affiliation', 'icon' => '💰', 'description' => 'Comparatifs avec liens affilies'],
            ['slug' => 'chatters',                'name' => 'Chatters',                'icon' => '💬', 'description' => 'Recrutement chatters par pays'],
            ['slug' => 'bloggeurs',               'name' => 'Influenceurs',            'icon' => '📢', 'description' => 'Recrutement influenceurs/blogueurs'],
            ['slug' => 'admin-groups',            'name' => 'Admin Groupes',           'icon' => '👥', 'description' => 'Recrutement admins groupes'],
            ['slug' => 'avocats',                 'name' => 'Partenaires Avocats',     'icon' => '⚖️', 'description' => 'Recrutement avocats prestataires'],
            ['slug' => 'expats-aidants',          'name' => 'Partenaires Expats',      'icon' => '🧳', 'description' => 'Recrutement expatries aidants'],
            ['slug' => 'temoignages',             'name' => 'Temoignages',             'icon' => '💬', 'description' => 'Temoignages expatries par pays'],
            ['slug' => 'tutoriels',               'name' => 'Tutoriels',               'icon' => '📖', 'description' => 'Guides pratiques pas-a-pas pour demarches expatries'],
            ['slug' => 'brand-content',           'name' => 'Brand Content',           'icon' => '🏷️', 'description' => 'Articles de marque scores et optimises'],
            ['slug' => 'statistiques',            'name' => 'Statistiques',            'icon' => '📊', 'description' => 'Articles statistiques expatries/voyageurs/nomades par pays (197 pays x 5 themes)'],
        ];

        $now = now();

        foreach ($categories as $i => $cat) {
            DB::table('generation_source_categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'description' => $cat['description'],
                    'sort_order' => $i,
                    'config_json' => json_encode([
                        'is_paused' => false,
                        'daily_quota' => 50,
                        'weight_percent' => 0,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command?->info("Seeded " . count($categories) . " generation source categories.");
    }
}
