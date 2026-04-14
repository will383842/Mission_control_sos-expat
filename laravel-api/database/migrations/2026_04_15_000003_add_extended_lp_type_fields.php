<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes nécessaires aux 4 nouveaux types de landing pages :
 *   - category_pillar : category_slug (ex: 'immigration', 'sante')
 *   - profile         : user_profile  (ex: 'digital_nomade', 'retraite')
 *   - nationality     : origin_nationality (ex: 'FR', 'GB')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Pour les piliers thématiques (category_pillar)
            $table->string('category_slug', 100)->nullable()->after('country_code');

            // Pour les pages profil expatrié (profile)
            $table->string('user_profile', 50)->nullable()->after('category_slug');

            // Pour les pages nationalité × pays (nationality) — ISO 3166-1 alpha-2
            $table->char('origin_nationality', 2)->nullable()->after('user_profile');

            // Index pour requêtes par type
            $table->index('category_slug');
            $table->index('user_profile');
            $table->index('origin_nationality');
            // Index composite utile pour les stats par audience+sous-type
            $table->index(['audience_type', 'category_slug']);
            $table->index(['audience_type', 'user_profile']);
            $table->index(['audience_type', 'origin_nationality']);
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex(['audience_type', 'origin_nationality']);
            $table->dropIndex(['audience_type', 'user_profile']);
            $table->dropIndex(['audience_type', 'category_slug']);
            $table->dropIndex(['origin_nationality']);
            $table->dropIndex(['user_profile']);
            $table->dropIndex(['category_slug']);
            $table->dropColumn(['category_slug', 'user_profile', 'origin_nationality']);
        });
    }
};
