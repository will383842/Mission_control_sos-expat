<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION CONTACTS — Toutes les sources vers la table unifiée `influenceurs`
 *
 * Fusionne :
 *   - lawyers        → contact_type = 'avocat'
 *   - press_contacts → contact_type = 'presse'
 *
 * Les tables sources sont conservées intactes (lecture seule après migration).
 * Un champ `source` tracé la provenance : 'lawyers_import' / 'press_import'.
 * Les doublons sur email sont ignorés (ON CONFLICT DO NOTHING).
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. AVOCATS : lawyers → influenceurs (contact_type = 'avocat')
        // =====================================================================
        DB::statement("
            INSERT INTO influenceurs (
                contact_type, category, contact_kind,
                name, first_name, last_name, company, position,
                email, has_email, phone, has_phone,
                profile_url, profile_url_domain, website_url,
                country, language,
                notes, avatar_url,
                is_verified,
                tags,
                source,
                scraped_at, scraper_status,
                status,
                score, data_completeness,
                created_at, updated_at
            )
            SELECT
                'avocat'                                    AS contact_type,
                'services_b2b'                             AS category,
                'individual'                               AS contact_kind,

                COALESCE(NULLIF(TRIM(full_name), ''),
                         TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')),
                         firm_name,
                         'Avocat inconnu')                  AS name,
                NULLIF(TRIM(COALESCE(first_name, '')), '')  AS first_name,
                NULLIF(TRIM(COALESCE(last_name, '')),  '')  AS last_name,
                NULLIF(TRIM(COALESCE(firm_name, '')),  '')  AS company,
                NULLIF(TRIM(COALESCE(title, '')),      '')  AS position,

                NULLIF(TRIM(COALESCE(email, '')), '')       AS email,
                (email IS NOT NULL AND email != '')         AS has_email,
                NULLIF(TRIM(COALESCE(phone, '')), '')       AS phone,
                (phone IS NOT NULL AND phone != '')         AS has_phone,

                NULLIF(TRIM(COALESCE(source_url, '')), '')  AS profile_url,
                NULLIF(TRIM(COALESCE(source_url, '')), '')  AS profile_url_domain,
                NULLIF(TRIM(COALESCE(website, '')),    '')  AS website_url,

                NULLIF(TRIM(COALESCE(country, '')),    '')  AS country,
                CASE
                    WHEN is_francophone = true THEN 'fr'
                    WHEN language IS NOT NULL AND language != '' THEN LOWER(SUBSTRING(language FROM 1 FOR 2))
                    ELSE NULL
                END                                         AS language,

                NULLIF(TRIM(COALESCE(description, '')), '') AS notes,
                NULLIF(TRIM(COALESCE(photo_url, '')),   '') AS avatar_url,

                COALESCE(email_verified, false)             AS is_verified,

                CASE
                    WHEN is_immigration_lawyer = true AND is_francophone = true
                        THEN '[\"immigration\",\"francophone\"]'::jsonb
                    WHEN is_immigration_lawyer = true
                        THEN '[\"immigration\"]'::jsonb
                    WHEN is_francophone = true
                        THEN '[\"francophone\"]'::jsonb
                    ELSE NULL
                END                                         AS tags,

                'lawyers_import'                            AS source,

                scraped_at                                  AS scraped_at,
                CASE WHEN detail_scraped = true THEN 'completed' ELSE 'pending' END AS scraper_status,

                'prospect'                                  AS status,

                LEAST(100,
                    CASE WHEN email IS NOT NULL AND email != '' THEN 40 ELSE 0 END +
                    CASE WHEN phone IS NOT NULL AND phone != '' THEN 15 ELSE 0 END +
                    CASE WHEN country IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN website IS NOT NULL THEN 15 ELSE 0 END +
                    CASE WHEN description IS NOT NULL AND description != '' THEN 10 ELSE 0 END +
                    CASE WHEN full_name IS NOT NULL AND full_name != '' THEN 10 ELSE 0 END
                )                                           AS score,

                LEAST(100,
                    CASE WHEN full_name IS NOT NULL AND full_name != '' THEN 15 ELSE 0 END +
                    CASE WHEN email IS NOT NULL AND email != '' THEN 25 ELSE 0 END +
                    CASE WHEN phone IS NOT NULL AND phone != '' THEN 15 ELSE 0 END +
                    CASE WHEN country IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN website IS NOT NULL THEN 15 ELSE 0 END +
                    CASE WHEN description IS NOT NULL AND description != '' THEN 5 ELSE 0 END
                )                                           AS data_completeness,

                COALESCE(created_at, NOW())                 AS created_at,
                COALESCE(updated_at, NOW())                 AS updated_at

            FROM lawyers
            WHERE
                -- Uniquement ceux avec au moins un nom ou email
                (
                    (full_name IS NOT NULL AND full_name != '')
                    OR (first_name IS NOT NULL AND first_name != '')
                    OR (email IS NOT NULL AND email != '')
                )
                -- Éviter les doublons sur email (si email déjà dans influenceurs)
                AND (
                    email IS NULL
                    OR email = ''
                    OR email NOT IN (SELECT email FROM influenceurs WHERE email IS NOT NULL AND email != '')
                )
        ");

        $lawyersInserted = DB::selectOne('SELECT COUNT(*) as n FROM influenceurs WHERE source = ?', ['lawyers_import'])->n;

        // =====================================================================
        // 2. JOURNALISTES : press_contacts → influenceurs (contact_type = 'presse')
        // =====================================================================
        DB::statement("
            INSERT INTO influenceurs (
                contact_type, category, contact_kind,
                name, first_name, last_name, company, position,
                email, has_email, phone, has_phone,
                profile_url, profile_url_domain,
                linkedin_url, twitter_url,
                country, language,
                notes,
                is_verified,
                tags,
                source,
                scraped_at, scraper_status,
                status,
                score, data_completeness,
                created_at, updated_at
            )
            SELECT
                'presse'                                    AS contact_type,
                'medias_influence'                         AS category,
                'individual'                               AS contact_kind,

                COALESCE(NULLIF(TRIM(full_name), ''),
                         TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')),
                         'Journaliste inconnu')             AS name,
                NULLIF(TRIM(COALESCE(first_name, '')), '')  AS first_name,
                NULLIF(TRIM(COALESCE(last_name, '')),  '')  AS last_name,
                NULLIF(TRIM(COALESCE(publication, '')), '') AS company,
                NULLIF(TRIM(COALESCE(role, '')),        '')  AS position,

                NULLIF(TRIM(COALESCE(email, '')), '')       AS email,
                (email IS NOT NULL AND email != '')         AS has_email,
                NULLIF(TRIM(COALESCE(phone, '')), '')       AS phone,
                (phone IS NOT NULL AND phone != '')         AS has_phone,

                NULLIF(TRIM(COALESCE(profile_url, '')), '') AS profile_url,
                NULLIF(TRIM(COALESCE(profile_url, '')), '') AS profile_url_domain,

                NULLIF(TRIM(COALESCE(linkedin, '')), '')    AS linkedin_url,
                NULLIF(TRIM(COALESCE(twitter, '')),  '')    AS twitter_url,

                NULLIF(TRIM(COALESCE(country, '')),  '')    AS country,
                CASE
                    WHEN language IS NOT NULL AND language != ''
                        THEN LOWER(SUBSTRING(language FROM 1 FOR 2))
                    ELSE NULL
                END                                         AS language,

                NULLIF(TRIM(COALESCE(notes, '')),  '')      AS notes,

                COALESCE(email_smtp_valid, false)           AS is_verified,

                CASE
                    WHEN beat IS NOT NULL AND beat != ''
                        THEN jsonb_build_array(beat)
                    ELSE NULL
                END                                         AS tags,

                'press_import'                              AS source,

                scraped_at                                  AS scraped_at,
                'completed'                                 AS scraper_status,

                'prospect'                                  AS status,

                LEAST(100,
                    CASE WHEN email IS NOT NULL AND email != '' THEN 40 ELSE 0 END +
                    CASE WHEN phone IS NOT NULL AND phone != '' THEN 10 ELSE 0 END +
                    CASE WHEN country IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN profile_url IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN linkedin IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN full_name IS NOT NULL AND full_name != '' THEN 10 ELSE 0 END +
                    CASE WHEN publication IS NOT NULL AND publication != '' THEN 10 ELSE 0 END
                )                                           AS score,

                LEAST(100,
                    CASE WHEN full_name IS NOT NULL AND full_name != '' THEN 15 ELSE 0 END +
                    CASE WHEN email IS NOT NULL AND email != '' THEN 25 ELSE 0 END +
                    CASE WHEN phone IS NOT NULL AND phone != '' THEN 15 ELSE 0 END +
                    CASE WHEN country IS NOT NULL THEN 10 ELSE 0 END +
                    CASE WHEN profile_url IS NOT NULL THEN 15 ELSE 0 END +
                    CASE WHEN notes IS NOT NULL AND notes != '' THEN 5 ELSE 0 END +
                    CASE WHEN linkedin IS NOT NULL THEN 5 ELSE 0 END +
                    CASE WHEN publication IS NOT NULL AND publication != '' THEN 5 ELSE 0 END +
                    CASE WHEN beat IS NOT NULL AND beat != '' THEN 5 ELSE 0 END
                )                                           AS data_completeness,

                COALESCE(pc.created_at, NOW())              AS created_at,
                COALESCE(pc.updated_at, NOW())              AS updated_at

            FROM press_contacts pc
            WHERE
                (
                    (full_name IS NOT NULL AND full_name != '')
                    OR (first_name IS NOT NULL AND first_name != '')
                    OR (email IS NOT NULL AND email != '')
                )
                AND (
                    email IS NULL
                    OR email = ''
                    OR email NOT IN (SELECT email FROM influenceurs WHERE email IS NOT NULL AND email != '')
                )
        ");

        $pressInserted = DB::selectOne('SELECT COUNT(*) as n FROM influenceurs WHERE source = ?', ['press_import'])->n;

        // Log pour info
        \Illuminate\Support\Facades\Log::info("Fusion contacts : {$lawyersInserted} avocats + {$pressInserted} journalistes importés dans influenceurs.");
    }

    public function down(): void
    {
        // Supprime uniquement les contacts issus de la fusion (identifiés par source)
        DB::table('influenceurs')
            ->whereIn('source', ['lawyers_import', 'press_import'])
            ->delete();
    }
};
