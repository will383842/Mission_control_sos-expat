<?php

namespace Database\Seeders;

use App\Helpers\FrenchPreposition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Peuple generation_source_items pour les articles statistiques.
 * 5 thèmes × ~223 pays = ~1115 items.
 * Safe à re-run (updateOrInsert).
 */
class StatisticsSourceItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $year = date('Y');

        $themes = [
            'expatriates' => [
                'template' => "Statistiques expatriés {prep_pays} : migration, diaspora et transferts ({$year})",
                'sub'      => 'Migration & Diaspora',
            ],
            'tourism' => [
                'template' => "Statistiques tourisme {prep_pays} : arrivées, dépenses et tendances ({$year})",
                'sub'      => 'Tourisme',
            ],
            'cost_of_living' => [
                'template' => "Coût de la vie {prep_pays} : indices, loyers et comparaison mondiale ({$year})",
                'sub'      => 'Coût de la vie',
            ],
            'safety' => [
                'template' => "Sécurité {prep_pays} : indices de criminalité et risques pour les voyageurs ({$year})",
                'sub'      => 'Sécurité',
            ],
            'economy' => [
                'template' => "Économie {prep_pays} : PIB, emploi et salaires pour expatriés ({$year})",
                'sub'      => 'Économie & Emploi',
            ],
        ];

        $countries = DB::table('content_countries')
            ->select('name', 'slug')
            ->orderBy('name')
            ->get();

        $totalInserted = 0;

        foreach ($themes as $themeKey => $config) {
            $count = 0;
            foreach ($countries as $country) {
                $title = FrenchPreposition::replace($config['template'], $country->name);

                DB::table('generation_source_items')->updateOrInsert(
                    ['category_slug' => 'statistiques', 'title' => $title],
                    [
                        'source_type'       => 'statistics',
                        'country'           => $country->name,
                        'country_slug'      => $country->slug,
                        'theme'             => $themeKey,
                        'sub_category'      => $config['sub'],
                        'language'          => 'fr',
                        'processing_status' => 'ready',
                        'quality_score'     => 80,
                        'is_cleaned'        => true,
                        'input_quality'     => 'title_only',
                        'used_count'        => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]
                );
                $count++;
            }
            $totalInserted += $count;
            $this->command?->info("{$config['sub']}: {$count} items");
        }

        $this->command?->info("Total statistiques: {$totalInserted} items créés/mis à jour");
    }
}
