<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 000004 — Champs rich snippets & design template.
 *
 * Ajoute les champs manquants pour la perfection SEO 2026 :
 * - OG complet : og_title, og_description, og_image (étoiles sociales)
 * - Twitter card complet : twitter_title, twitter_description, twitter_image
 * - robots : contrôle indexation par page
 * - design_template : pilier de la stratégie design (urgency/informational/trust/recruitment/conversion/pillar/profile/emergency)
 * - keywords_primary / keywords_secondary : stockage des mots-clés générés par l'IA
 * - date_published_at / date_modified_at : freshness signals dans JSON-LD et SERP
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Keywords (SEO primaryKeyword + secondary)
            $table->string('keywords_primary', 200)->nullable()->after('meta_description');
            $table->json('keywords_secondary')->nullable()->after('keywords_primary');

            // OG complet (social sharing rich preview)
            $table->string('og_title', 110)->nullable()->after('og_site_name');
            $table->string('og_description', 300)->nullable()->after('og_title');
            $table->string('og_image', 500)->nullable()->after('og_description');

            // Twitter card complet
            $table->string('twitter_title', 110)->nullable()->after('twitter_card');
            $table->string('twitter_description', 300)->nullable()->after('twitter_title');
            $table->string('twitter_image', 500)->nullable()->after('twitter_description');

            // Robots (contrôle indexation fine)
            $table->string('robots', 50)->nullable()->default('index,follow')->after('twitter_image');

            // Design template (pilier visuel pour le rendu blog)
            $table->string('design_template', 50)->nullable()->after('robots');

            // Freshness signals (JSON-LD datePublished / dateModified)
            $table->timestamp('date_published_at')->nullable()->after('design_template');
            $table->timestamp('date_modified_at')->nullable()->after('date_published_at');

            // Index sur design_template pour filtrage dashboard
            $table->index('design_template');
            $table->index('date_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex(['design_template']);
            $table->dropIndex(['date_published_at']);
            $table->dropColumn([
                'keywords_primary', 'keywords_secondary',
                'og_title', 'og_description', 'og_image',
                'twitter_title', 'twitter_description', 'twitter_image',
                'robots', 'design_template',
                'date_published_at', 'date_modified_at',
            ]);
        });
    }
};
