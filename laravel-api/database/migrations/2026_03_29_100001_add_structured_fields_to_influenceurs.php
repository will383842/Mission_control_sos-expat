<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Restructuration — Champs structurés pour une gestion parfaite des contacts.
 *
 * Ajoute :
 *   - category        : groupe de types (institutionnel, medias_influence, services_b2b, communautes, digital)
 *   - contact_kind    : individual | organization
 *   - first_name / last_name : pour les contacts individuels
 *   - linkedin/twitter/facebook/instagram/tiktok/youtube_url : URLs sociales dédiées
 *   - is_verified     : données vérifiées manuellement
 *   - unsubscribed    : désabonné des emails
 *   - bounce_count    : nombre de bounces email
 *   - data_completeness (0–100) : score de complétude calculé automatiquement
 *   - has_email / has_phone : booléens indexés pour filtrage rapide
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            // Classification
            $table->string('category', 50)->nullable()->after('contact_type');
            $table->string('contact_kind', 20)->default('organization')->after('category');

            // Identité individuelle
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');

            // Helper booléens (indexés pour filtrage ultra-rapide)
            $table->boolean('has_email')->default(false)->after('email');
            $table->boolean('has_phone')->default(false)->after('phone');

            // URLs sociales dédiées (complément au scraped_social JSON)
            $table->string('linkedin_url', 500)->nullable()->after('website_url');
            $table->string('twitter_url', 500)->nullable()->after('linkedin_url');
            $table->string('facebook_url', 500)->nullable()->after('twitter_url');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('tiktok_url', 500)->nullable()->after('instagram_url');
            $table->string('youtube_url', 500)->nullable()->after('tiktok_url');

            // Qualité CRM
            $table->boolean('is_verified')->default(false)->after('quality_score');
            $table->boolean('unsubscribed')->default(false)->after('is_verified');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribed');
            $table->smallInteger('bounce_count')->default(0)->after('unsubscribed_at');
            $table->smallInteger('data_completeness')->default(0)->after('bounce_count');
        });

        // Indexes pour les filtres les plus fréquents
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->index('category');
            $table->index('contact_kind');
            $table->index(['category', 'status']);
            $table->index(['has_email', 'category']);
            $table->index(['has_phone', 'category']);
            $table->index('is_verified');
            $table->index('data_completeness');
            $table->index('unsubscribed');
        });

        // Backfill : calcul category, contact_kind, has_email, has_phone, data_completeness sur l'existant
        DB::statement("
            UPDATE influenceurs SET
                category = CASE contact_type
                    WHEN 'consulat'                   THEN 'institutionnel'
                    WHEN 'association'                THEN 'institutionnel'
                    WHEN 'ecole'                      THEN 'institutionnel'
                    WHEN 'institut_culturel'          THEN 'institutionnel'
                    WHEN 'chambre_commerce'           THEN 'institutionnel'
                    WHEN 'presse'                     THEN 'medias_influence'
                    WHEN 'blog'                       THEN 'medias_influence'
                    WHEN 'podcast_radio'              THEN 'medias_influence'
                    WHEN 'influenceur'                THEN 'medias_influence'
                    WHEN 'avocat'                     THEN 'services_b2b'
                    WHEN 'immobilier'                 THEN 'services_b2b'
                    WHEN 'assurance'                  THEN 'services_b2b'
                    WHEN 'banque_fintech'             THEN 'services_b2b'
                    WHEN 'traducteur'                 THEN 'services_b2b'
                    WHEN 'agence_voyage'              THEN 'services_b2b'
                    WHEN 'emploi'                     THEN 'services_b2b'
                    WHEN 'communaute_expat'           THEN 'communautes'
                    WHEN 'groupe_whatsapp_telegram'   THEN 'communautes'
                    WHEN 'coworking_coliving'         THEN 'communautes'
                    WHEN 'logement'                   THEN 'communautes'
                    WHEN 'lieu_communautaire'         THEN 'communautes'
                    WHEN 'backlink'                   THEN 'digital'
                    WHEN 'annuaire'                   THEN 'digital'
                    WHEN 'plateforme_nomad'           THEN 'digital'
                    WHEN 'partenaire'                 THEN 'digital'
                    ELSE 'autre'
                END,
                contact_kind = CASE contact_type
                    WHEN 'influenceur'  THEN 'individual'
                    WHEN 'avocat'       THEN 'individual'
                    WHEN 'traducteur'   THEN 'individual'
                    ELSE 'organization'
                END,
                has_email = CASE WHEN email IS NOT NULL AND email != '' THEN TRUE ELSE FALSE END,
                has_phone = CASE WHEN phone IS NOT NULL AND phone != '' THEN TRUE ELSE FALSE END,
                data_completeness = LEAST(100, (
                    CASE WHEN name IS NOT NULL AND name != ''           THEN 15 ELSE 0 END +
                    CASE WHEN email IS NOT NULL AND email != ''         THEN 25 ELSE 0 END +
                    CASE WHEN phone IS NOT NULL AND phone != ''         THEN 15 ELSE 0 END +
                    CASE WHEN country IS NOT NULL AND country != ''     THEN 10 ELSE 0 END +
                    CASE WHEN language IS NOT NULL AND language != ''   THEN  5 ELSE 0 END +
                    CASE WHEN (profile_url IS NOT NULL AND profile_url != '')
                              OR (website_url IS NOT NULL AND website_url != '') THEN 15 ELSE 0 END +
                    CASE WHEN notes IS NOT NULL AND notes != ''         THEN  5 ELSE 0 END +
                    CASE WHEN score > 0                                 THEN  5 ELSE 0 END +
                    CASE WHEN tags IS NOT NULL AND tags::text != '[]'   THEN  5 ELSE 0 END
                ))
        ");
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'contact_kind',
                'first_name', 'last_name',
                'has_email', 'has_phone',
                'linkedin_url', 'twitter_url', 'facebook_url',
                'instagram_url', 'tiktok_url', 'youtube_url',
                'is_verified', 'unsubscribed', 'unsubscribed_at',
                'bounce_count', 'data_completeness',
            ]);
        });
    }
};
