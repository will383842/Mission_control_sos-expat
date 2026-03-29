<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nationality_code + translations to country_directory.
 *
 * - nationality_code: whose embassy it is (NULL = resource universelle, non liée à une nationalité)
 * - nationality_name: nom lisible de la nationalité (ex. "Allemagne")
 * - translations: JSON multilingue {"en":{"title":"...","description":"..."}, "es":{...}, "ar":{...}}
 *
 * Exemples après migration :
 *   Ambassade de France en Allemagne  → nationality_code='FR', country_code='DE'
 *   Lien BAMF immigration Allemagne   → nationality_code=NULL, country_code='DE'
 *   Ambassade d'Allemagne en Thaïlande → nationality_code='DE', country_code='TH'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_directory', function (Blueprint $table) {
            // Supprimer l'ancien index unique (country_code, url) — on va le remplacer
            $table->dropUnique('country_directory_unique_link');

            // Nationalité de l'expatrié (quelle ambassade cherche-t-il ?)
            $table->char('nationality_code', 2)->nullable()->after('country_code')->index();
            $table->string('nationality_name', 100)->nullable()->after('nationality_code');

            // Traductions multilingues : {"en":{"title":"...","description":"..."},"es":{...}}
            $table->json('translations')->nullable()->after('description')
                ->comment('Traductions du titre et description par langue ISO 639-1');
        });

        // Nouvel index unique : (country_code, COALESCE(nationality_code,''), url)
        // COALESCE permet de traiter NULL comme '' → pas de doublons sur liens sans nationalité
        DB::statement(
            "CREATE UNIQUE INDEX country_directory_unique_link
             ON country_directory (country_code, COALESCE(nationality_code, ''), url)"
        );

        // Marquer les ambassades existantes (data.gouv.fr) comme françaises
        DB::statement(
            "UPDATE country_directory
             SET nationality_code = 'FR', nationality_name = 'France'
             WHERE category = 'ambassade' AND nationality_code IS NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS country_directory_unique_link');

        Schema::table('country_directory', function (Blueprint $table) {
            $table->dropColumn(['nationality_code', 'nationality_name', 'translations']);
        });

        // Restaurer l'ancien index
        Schema::table('country_directory', function (Blueprint $table) {
            $table->unique(['country_code', 'url'], 'country_directory_unique_link');
        });
    }
};
