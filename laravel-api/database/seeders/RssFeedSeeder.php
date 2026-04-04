<?php

namespace Database\Seeders;

use App\Models\RssFeed;
use Illuminate\Database\Seeder;

/**
 * Initial RSS feeds focused on expatriation, travel, and international news.
 * Run: php artisan db:seed --class=RssFeedSeeder
 */
class RssFeedSeeder extends Seeder
{
    public function run(): void
    {
        $feeds = [
            // ─── Le Petit Journal ───────────────────────────────────────────
            [
                'name'                  => 'Le Petit Journal - À la une',
                'url'                   => 'https://lepetitjournal.com/feed',
                'language'              => 'fr',
                'country'               => null,
                'category'              => 'expatriation',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 60,
                'notes'                 => 'Média de référence pour les expatriés français dans le monde. Couvre vie pratique, actualités par pays, conseils.',
            ],
            [
                'name'                  => 'Le Petit Journal - Pratique',
                'url'                   => 'https://lepetitjournal.com/expatriation/feed',
                'language'              => 'fr',
                'country'               => null,
                'category'              => 'expatriation',
                'active'                => true,
                'fetch_interval_hours'  => 6,
                'relevance_threshold'   => 55,
                'notes'                 => 'Rubriques pratiques expatriation : visa, logement, fiscalité, santé.',
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
                'name'                  => 'BBC Travel',
                'url'                   => 'https://feeds.bbci.co.uk/travel/rss.xml',
                'language'              => 'en',
                'country'               => null,
                'category'              => 'voyage',
                'active'                => true,
                'fetch_interval_hours'  => 4,
                'relevance_threshold'   => 55,
                'notes'                 => 'BBC Travel — destinations, conseils voyage, reportages.',
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
            RssFeed::firstOrCreate(
                ['url' => $feedData['url']],
                $feedData
            );
        }

        $this->command->info('✅ ' . count($feeds) . ' flux RSS initiaux créés (Le Petit Journal, BBC, CNN).');
    }
}
