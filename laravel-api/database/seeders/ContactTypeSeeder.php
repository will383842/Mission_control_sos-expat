<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 25 contact types organized in 5 categories.
 * Uses updateOrInsert so it's safe to re-run.
 * Deactivates any old types not in the new list.
 */
class ContactTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Institutionnel
            ['value' => 'consulat',               'label' => 'Consulats & Ambassades',    'icon' => '🏛️', 'color' => '#6366F1', 'sort_order' => 1,  'scraper_enabled' => true],
            ['value' => 'association',            'label' => 'Associations',              'icon' => '🤝', 'color' => '#EC4899', 'sort_order' => 2,  'scraper_enabled' => true],
            ['value' => 'ecole',                  'label' => 'Écoles & Formation',        'icon' => '🏫', 'color' => '#10B981', 'sort_order' => 3,  'scraper_enabled' => true],
            ['value' => 'institut_culturel',      'label' => 'Instituts culturels',       'icon' => '🎭', 'color' => '#8B5CF6', 'sort_order' => 4,  'scraper_enabled' => true],
            ['value' => 'chambre_commerce',       'label' => 'Chambres de commerce',      'icon' => '🏢', 'color' => '#14B8A6', 'sort_order' => 5,  'scraper_enabled' => true],

            // Médias & Influence
            ['value' => 'presse',                 'label' => 'Presse & Médias',           'icon' => '📺', 'color' => '#E11D48', 'sort_order' => 10, 'scraper_enabled' => true],
            ['value' => 'blog',                   'label' => 'Blogs & Créateurs',         'icon' => '📝', 'color' => '#A855F7', 'sort_order' => 11, 'scraper_enabled' => true],
            ['value' => 'podcast_radio',          'label' => 'Podcasts & Radios',         'icon' => '🎙️', 'color' => '#F97316', 'sort_order' => 12, 'scraper_enabled' => true],
            ['value' => 'influenceur',            'label' => 'Influenceurs',              'icon' => '✨', 'color' => '#FFD60A', 'sort_order' => 13, 'scraper_enabled' => false],
            ['value' => 'youtubeur',              'label' => 'YouTubeurs',                'icon' => '▶️', 'color' => '#FF0000', 'sort_order' => 14, 'scraper_enabled' => false],
            ['value' => 'instagrammeur',          'label' => 'Instagrammeurs',            'icon' => '📸', 'color' => '#E1306C', 'sort_order' => 15, 'scraper_enabled' => false],

            // Services B2B
            ['value' => 'avocat',                 'label' => 'Avocats',                   'icon' => '⚖️', 'color' => '#8B5CF6', 'sort_order' => 20, 'scraper_enabled' => true],
            ['value' => 'immobilier',             'label' => 'Immobilier & Relocation',   'icon' => '🏠', 'color' => '#84CC16', 'sort_order' => 21, 'scraper_enabled' => true],
            ['value' => 'assurance',              'label' => 'Assurances',                'icon' => '🛡️', 'color' => '#3B82F6', 'sort_order' => 22, 'scraper_enabled' => true],
            ['value' => 'banque_fintech',         'label' => 'Banques & Fintechs',        'icon' => '🏦', 'color' => '#0EA5E9', 'sort_order' => 23, 'scraper_enabled' => true],
            ['value' => 'traducteur',             'label' => 'Traducteurs',               'icon' => '🌐', 'color' => '#06B6D4', 'sort_order' => 24, 'scraper_enabled' => true],
            ['value' => 'agence_voyage',          'label' => 'Agences de voyage',         'icon' => '✈️', 'color' => '#06B6D4', 'sort_order' => 25, 'scraper_enabled' => true],
            ['value' => 'emploi',                 'label' => 'Emploi & Remote',           'icon' => '💼', 'color' => '#78716C', 'sort_order' => 26, 'scraper_enabled' => true],

            // Communautés & Lieux
            ['value' => 'communaute_expat',       'label' => 'Communautés expat',         'icon' => '🌍', 'color' => '#F472B6', 'sort_order' => 30, 'scraper_enabled' => true],
            ['value' => 'groupe_whatsapp_telegram','label' => 'Groupes WhatsApp/Telegram', 'icon' => '💬', 'color' => '#22C55E', 'sort_order' => 31, 'scraper_enabled' => false],
            ['value' => 'coworking_coliving',     'label' => 'Coworkings & Colivings',    'icon' => '🏡', 'color' => '#D97706', 'sort_order' => 32, 'scraper_enabled' => true],
            ['value' => 'logement',               'label' => 'Logement international',    'icon' => '🔑', 'color' => '#EAB308', 'sort_order' => 33, 'scraper_enabled' => true],
            ['value' => 'lieu_communautaire',     'label' => 'Lieux communautaires',      'icon' => '☕', 'color' => '#FB923C', 'sort_order' => 34, 'scraper_enabled' => false],

            // Digital & Technique
            ['value' => 'backlink',               'label' => 'Backlinks & SEO',           'icon' => '🔗', 'color' => '#F59E0B', 'sort_order' => 40, 'scraper_enabled' => true],
            ['value' => 'annuaire',               'label' => 'Annuaires',                 'icon' => '📚', 'color' => '#A3A3A3', 'sort_order' => 41, 'scraper_enabled' => true],
            ['value' => 'plateforme_nomad',       'label' => 'Plateformes nomad',         'icon' => '🧭', 'color' => '#2DD4BF', 'sort_order' => 42, 'scraper_enabled' => true],
            ['value' => 'partenaire',             'label' => 'Partenaires B2B',           'icon' => '🤝', 'color' => '#D97706', 'sort_order' => 43, 'scraper_enabled' => true],
        ];

        $validValues = array_column($types, 'value');

        foreach ($types as $type) {
            DB::table('contact_types')->updateOrInsert(
                ['value' => $type['value']],
                array_merge($type, [
                    'is_active'  => true,
                    'updated_at' => now(),
                ])
            );
        }

        // Deactivate and remove old types not in the new list
        DB::table('contact_types')
            ->whereNotIn('value', $validValues)
            ->delete();

        // Clear cache
        \App\Models\ContactTypeModel::flushCache();
    }
}
