<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * City content templates — 15 templates × 991 cities = ~14,865 articles potentiels.
 * Each template uses {ville} and {pays} variables for city-specific content.
 */
class CityTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['title' => 'vivre a {ville} en tant qu\'expatrie : guide complet', 'cluster' => 'Expatriation', 'intent' => 'informational'],
            ['title' => 'cout de la vie a {ville} : budget mensuel pour expatrie', 'cluster' => 'Cout de la vie', 'intent' => 'informational'],
            ['title' => 'trouver un logement a {ville} : quartiers, prix et conseils', 'cluster' => 'Logement', 'intent' => 'informational'],
            ['title' => 'travailler a {ville} : emploi, salaires et demarches', 'cluster' => 'Emploi', 'intent' => 'informational'],
            ['title' => 'meilleurs quartiers pour vivre a {ville} pour expatries', 'cluster' => 'Logement', 'intent' => 'commercial_investigation'],
            ['title' => 'ecoles internationales a {ville} : tarifs, classement et inscriptions', 'cluster' => 'Education', 'intent' => 'commercial_investigation'],
            ['title' => 'securite a {ville} : quartiers surs et zones a eviter', 'cluster' => 'Securite', 'intent' => 'informational'],
            ['title' => 'transport a {ville} : metro, bus, taxi et location voiture', 'cluster' => 'Transport', 'intent' => 'informational'],
            ['title' => 'sortir a {ville} : restaurants, bars et vie nocturne pour expatries', 'cluster' => 'Lifestyle', 'intent' => 'informational'],
            ['title' => 'communaute expatriee a {ville} : groupes, associations et evenements', 'cluster' => 'Communaute', 'intent' => 'informational'],
            ['title' => 'coworking a {ville} : meilleurs espaces pour digital nomads', 'cluster' => 'Digital Nomad', 'intent' => 'commercial_investigation'],
            ['title' => 'hopitaux et cliniques a {ville} : soins medicaux pour expatries', 'cluster' => 'Sante', 'intent' => 'local'],
            ['title' => 'demenager a {ville} : checklist complete pour expatries', 'cluster' => 'Demenagement', 'intent' => 'informational'],
            ['title' => 'visiter {ville} en vacances : itineraire, budget et conseils', 'cluster' => 'Vacances', 'intent' => 'informational'],
            ['title' => 'investir dans l\'immobilier a {ville} : prix, rendement et procedure', 'cluster' => 'Immobilier', 'intent' => 'commercial_investigation'],
            // VACANCIERS / TOURISTES
            ['title' => 'que faire a {ville} : top activites et visites incontournables', 'cluster' => 'Tourisme', 'intent' => 'informational'],
            ['title' => 'ou dormir a {ville} : meilleurs quartiers et hotels pour touristes', 'cluster' => 'Hebergement', 'intent' => 'commercial_investigation'],
            ['title' => 'budget vacances a {ville} : cout par jour, hotels, restaurants, transports', 'cluster' => 'Budget Vacances', 'intent' => 'informational'],
            ['title' => 'securite a {ville} pour touristes : arnaques, zones et conseils', 'cluster' => 'Securite Touriste', 'intent' => 'informational'],
            ['title' => 'se deplacer a {ville} en vacances : transports, taxi, location', 'cluster' => 'Transport Touriste', 'intent' => 'informational'],
        ];

        $now = now();

        foreach ($templates as $i => $tpl) {
            DB::table('content_templates')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'name' => "Ville — {$tpl['cluster']}",
                'preset_type' => 'villes',
                'content_type' => 'guide_city',
                'title_template' => $tpl['title'],
                'variables' => json_encode(['ville', 'pays']),
                'expansion_mode' => 'custom_list',
                'expansion_values' => json_encode(['source' => 'content_cities']),
                'generation_instructions' => "Article sur {$tpl['cluster']} specifique a la VILLE (pas le pays). Intention: {$tpl['intent']}. Donnees locales: quartiers, prix, adresses, transports. S'adresse a TOUTE nationalite.",
                'tone' => 'professional',
                'article_length' => 'long',
                'faq_count' => 5,
                'generate_faq' => true,
                'research_sources' => json_encode(['tavily', 'perplexity']),
                'auto_internal_links' => true,
                'auto_affiliate_links' => false,
                'auto_translate' => true,
                'image_source' => 'unsplash',
                'total_items' => 0,
                'generated_items' => 0,
                'published_items' => 0,
                'failed_items' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info("Seeded " . count($templates) . " city templates (× 991 cities = ~" . (count($templates) * 991) . " articles potentiels).");
    }
}
