<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise les catégories dans la table influenceurs.
 *
 * Problème: certains contacts importés avaient category='presse' ou category='influenceurs'
 * alors que la source de vérité (constants.ts + CONTACT_CATEGORIES) utilise 'medias_influence'
 * comme catégorie unique pour tous les types médias (presse, blog, podcast_radio, influenceur, youtubeur).
 *
 * Ce fix:
 *  1. Remplace category='presse'      → 'medias_influence'
 *  2. Remplace category='influenceurs'→ 'medias_influence'
 *  3. Remplace category=NULL pour les types médias connus → 'medias_influence'
 *  4. Corrige les NULL pour tous les autres types connus
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Corriger category='presse' ou 'influenceurs' → 'medias_influence'
        DB::statement("
            UPDATE influenceurs
            SET category = 'medias_influence', updated_at = NOW()
            WHERE category IN ('presse', 'influenceurs')
              AND deleted_at IS NULL
        ");

        // 2. Corriger category NULL pour les types connus
        DB::statement("
            UPDATE influenceurs
            SET category = CASE
                WHEN contact_type IN ('consulat','association','ecole','institut_culturel','chambre_commerce')
                    THEN 'institutionnel'
                WHEN contact_type IN ('presse','blog','podcast_radio','influenceur','youtubeur')
                    THEN 'medias_influence'
                WHEN contact_type IN ('avocat','immobilier','assurance','banque_fintech','traducteur','agence_voyage','emploi')
                    THEN 'services_b2b'
                WHEN contact_type IN ('communaute_expat','groupe_whatsapp_telegram','coworking_coliving','logement','lieu_communautaire')
                    THEN 'communautes'
                WHEN contact_type IN ('backlink','annuaire','plateforme_nomad','partenaire')
                    THEN 'digital'
                ELSE category
            END,
            updated_at = NOW()
            WHERE (category IS NULL OR category = '')
              AND deleted_at IS NULL
        ");

        // 3. Log des résultats
        $counts = DB::selectOne("
            SELECT
                COUNT(*) FILTER (WHERE category = 'medias_influence') as medias,
                COUNT(*) FILTER (WHERE category = 'institutionnel')   as institutionnel,
                COUNT(*) FILTER (WHERE category = 'services_b2b')     as services_b2b,
                COUNT(*) FILTER (WHERE category = 'communautes')      as communautes,
                COUNT(*) FILTER (WHERE category = 'digital')          as digital,
                COUNT(*) FILTER (WHERE category IS NULL OR category = '') as sans_categorie
            FROM influenceurs WHERE deleted_at IS NULL
        ");
        \Illuminate\Support\Facades\Log::info('fix_category_medias_influence migration', (array) $counts);
    }

    public function down(): void
    {
        // Non réversible sans sauvegarde des anciennes valeurs
        // (les anciennes valeurs 'presse'/'influenceurs' étaient incorrectes de toute façon)
    }
};
