<?php

namespace Database\Seeders;

use App\Models\RssFeed;
use Illuminate\Database\Seeder;

/**
 * Initial RSS feeds focused on expatriation, travel, and international news.
 * Run: php artisan db:seed --class=RssFeedSeeder
 *
 * Note: Le Petit Journal n'expose pas de flux RSS public (URL /feed → 404).
 * Remplacé par France 24 + RFI + Le Monde pour la couverture francophone.
 */
class RssFeedSeeder extends Seeder
{
    public function run(): void
    {
        $feeds = [
            // ─── France 24 ──────────────────────────────────────────────────
            [
                'name'                  => 'France 24 - Actualités françaises',
                'url'                   => 'https://www.france24.com/fr/rss',
                'language'              => 'fr',
                'country'               => null,
                'category'              => 'monde',
                'active'                => true,
                'fetch_interval_hours'  => 3,
                'relevance_threshold'   => 60,
                'notes'                 => 'Chaîne d\'info internationale FR. Excellente couverture internationale pour expatriés.',
            ],
            [
                'name'                  => 'France 24 - International (EN)',
                'url'                   => 'https://www.france24.com/en/rss',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'monde',
                'active'                => true,
                'fetch_interval_hours'  => 3,
                'relevance_threshold'   => 60,
                'notes'                 => 'France 24 English — international coverage relevant to expats worldwide.',
            ],

            // ─── RFI ────────────────────────────────────────────────────────
            [
                'name'                  => 'RFI - Actualités monde',
                'url'                   => 'https://www.rfi.fr/fr/rss',
                'language'              => 'fr',
                'country'               => null,
                'category'              => 'expatriation',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 58,
                'notes'                 => 'Radio France Internationale — forte couverture Afrique, Asie, Amériques. Idéal expatriés.',
            ],

            // ─── Le Monde ───────────────────────────────────────────────────
            [
                'name'                  => 'Le Monde - International',
                'url'                   => 'https://www.lemonde.fr/international/rss_full.xml',
                'language'              => 'fr',
                'country'               => null,
                'category'              => 'monde',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 65,
                'notes'                 => 'Le Monde International — analyse et actualité monde pour expatriés informés.',
            ],

            // ─── BBC ────────────────────────────────────────────────────────
            [
                'name'                  => 'BBC World News',
                'url'                   => 'https://feeds.bbci.co.uk/news/world/rss.xml',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'monde',
                'active'                => true,
                'fetch_interval_hours'  => 2,
                'relevance_threshold'   => 65,
                'notes'                 => 'Actualités mondiales BBC. Filtre IA à 65 pour ne garder que ce qui concerne expatriés/voyageurs.',
            ],
            [
                'name'                  => 'BBC News - General',
                'url'                   => 'https://feeds.bbci.co.uk/news/rss.xml',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'voyage',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 60,
                'notes'                 => 'BBC News général — inclut UK policy, immigration, travel advisories.',
            ],
            [
                'name'                  => 'BBC Business (expat finance)',
                'url'                   => 'https://feeds.bbci.co.uk/news/business/rss.xml',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'fiscalite',
                'active'                => true,
                'fetch_interval_hours'  => 6,
                'relevance_threshold'   => 70,
                'notes'                 => 'Finance & économie mondiale : changes, retraite à l\'étranger, fiscalité internationale.',
            ],

            // ─── CNN ────────────────────────────────────────────────────────
            [
                'name'                  => 'CNN World',
                'url'                   => 'http://rss.cnn.com/rss/edition_world.rss',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'monde',
                'active'                => true,
                'fetch_interval_hours'  => 2,
                'relevance_threshold'   => 65,
                'notes'                 => 'CNN Monde — filtré par IA pour ne garder que l\'actualité pertinente expats/voyageurs.',
            ],
            [
                'name'                  => 'CNN Travel',
                'url'                   => 'http://rss.cnn.com/rss/cnn_travel.rss',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'voyage',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 55,
                'notes'                 => 'CNN Travel — destinations, visas, sécurité voyage.',
            ],
            [
                'name'                  => 'CNN Business',
                'url'                   => 'http://rss.cnn.com/rss/money_news_international.rss',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'fiscalite',
                'active'                => true,
                'fetch_interval_hours'  => 6,
                'relevance_threshold'   => 70,
                'notes'                 => 'Économie internationale : monnaies, inflation, marchés — impact sur les expatriés.',
            ],
        ];

        foreach ($feeds as $feedData) {
            RssFeed::updateOrCreate(
                ['url' => $feedData['url']],
                $feedData
            );
        }

        $this->command->info('✅ ' . count($feeds) . ' flux RSS créés/mis à jour (France 24, RFI, Le Monde, BBC, CNN).');
    }
}
