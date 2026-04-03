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
    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select(
            "SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? GROUP BY INDEX_NAME",
            [$table]
        ))->pluck('indexname')->contains($indexName);
    }

    public function up(): void
    {
        // Drop old unique index if it exists (safely)
        if ($this->indexExists('country_directory', 'country_directory_unique_link')) {
            DB::statement('ALTER TABLE country_directory DROP INDEX country_directory_unique_link');
        }

        Schema::table('country_directory', function (Blueprint $table) {
            // Nationalité de l'expatrié (quelle ambassade cherche-t-il ?)
            if (!Schema::hasColumn('country_directory', 'nationality_code')) {
                $table->char('nationality_code', 2)->nullable()->after('country_code')->index();
            }
            if (!Schema::hasColumn('country_directory', 'nationality_name')) {
                $table->string('nationality_name', 100)->nullable()->after('nationality_code');
            }

            // Traductions multilingues : {"en":{"title":"...","description":"..."},"es":{...}}
            if (!Schema::hasColumn('country_directory', 'translations')) {
                $table->json('translations')->nullable()->after('description');
            }
        });

        // MySQL/MariaDB ne supporte pas les index fonctionnels (COALESCE dans index).
        // On crée un index standard sur (country_code, nationality_code, url).
        $existingIndexes = collect(DB::select(
            "SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'country_directory'
             GROUP BY INDEX_NAME"
        ))->pluck('indexname')->toArray();

        if (!$this->indexExists('country_directory', 'country_directory_unique_link')) {
            // MySQL doesn't support functional indexes (COALESCE); use a prefix on url to stay within key length limit
            DB::statement(
                "ALTER TABLE country_directory ADD INDEX country_directory_unique_link (country_code, nationality_code, url(100))"
            );
        }

        // Marquer les ambassades existantes (data.gouv.fr) comme françaises
        DB::statement(
            "UPDATE country_directory
             SET nationality_code = 'FR', nationality_name = 'France'
             WHERE category = 'ambassade' AND nationality_code IS NULL"
        );
    }

    public function down(): void
    {
        try {
            Schema::table('country_directory', function (Blueprint $table) {
                $table->dropIndex('country_directory_unique_link');
            });
        } catch (\Throwable $e) {}

        Schema::table('country_directory', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('country_directory', 'nationality_code')) $cols[] = 'nationality_code';
            if (Schema::hasColumn('country_directory', 'nationality_name'))  $cols[] = 'nationality_name';
            if (Schema::hasColumn('country_directory', 'translations'))      $cols[] = 'translations';
            if (!empty($cols)) $table->dropColumn($cols);
        });

        // Restaurer l'ancien index
        $existingIndexes = collect(DB::select(
            "SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'country_directory'
             GROUP BY INDEX_NAME"
        ))->pluck('indexname')->toArray();

        if (!in_array('country_directory_unique_link', $existingIndexes)) {
            Schema::table('country_directory', function (Blueprint $table) {
                $table->unique(['country_code', 'url'], 'country_directory_unique_link');
            });
        }
    }
};
