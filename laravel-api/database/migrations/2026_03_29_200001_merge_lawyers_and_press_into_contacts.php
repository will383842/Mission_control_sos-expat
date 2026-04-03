<?php

use Illuminate\Database\Migrations\Migration;
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
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. AVOCATS : lawyers → influenceurs (contact_type = 'avocat')
        // =====================================================================
        if (Schema::hasTable('lawyers')) {
            try {
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
                        platforms, primary_platform, created_by,
                        created_at, updated_at
                    )
                    SELECT
                        'avocat'                                    AS contact_type,
                        'services_b2b'                             AS category,
                        'individual'                               AS contact_kind,

                        COALESCE(NULLIF(TRIM(full_name), ''),
                                 NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ' '),
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
                            WHEN is_francophone = 1 THEN 'fr'
                            WHEN language IS NOT NULL AND language != '' THEN LOWER(SUBSTRING(language, 1, 2))
                            ELSE NULL
                        END                                         AS language,

                        NULLIF(TRIM(COALESCE(description, '')), '') AS notes,
                        NULLIF(TRIM(COALESCE(photo_url, '')),   '') AS avatar_url,

                        COALESCE(email_verified, 0)                 AS is_verified,

                        CASE
                            WHEN is_immigration_lawyer = 1 AND is_francophone = 1
                                THEN '[\"immigration\",\"francophone\"]'
                            WHEN is_immigration_lawyer = 1
                                THEN '[\"immigration\"]'
                            WHEN is_francophone = 1
                                THEN '[\"francophone\"]'
                            ELSE NULL
                        END                                         AS tags,

                        'lawyers_import'                            AS source,

                        scraped_at                                  AS scraped_at,
                        CASE WHEN detail_scraped = 1 THEN 'completed' ELSE 'pending' END AS scraper_status,

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

                        '[]'                                        AS platforms,
                        'website'                                   AS primary_platform,
                        1                                           AS created_by,

                        COALESCE(created_at, NOW())                 AS created_at,
                        COALESCE(updated_at, NOW())                 AS updated_at

                    FROM lawyers
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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('lawyers → influenceurs import skipped: ' . $e->getMessage());
            }
        }

        $lawyersInserted = DB::selectOne('SELECT COUNT(*) as n FROM influenceurs WHERE source = ?', ['lawyers_import'])->n ?? 0;

        // =====================================================================
        // 2. JOURNALISTES : press_contacts → influenceurs (contact_type = 'presse')
        // =====================================================================
        if (Schema::hasTable('press_contacts')) {
            try {
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
                        platforms, primary_platform, created_by,
                        created_at, updated_at
                    )
                    SELECT
                        'presse'                                    AS contact_type,
                        'medias_influence'                         AS category,
                        'individual'                               AS contact_kind,

                        COALESCE(NULLIF(TRIM(full_name), ''),
                                 NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ' '),
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
                                THEN LOWER(SUBSTRING(language, 1, 2))
                            ELSE NULL
                        END                                         AS language,

                        NULLIF(TRIM(COALESCE(notes, '')),  '')      AS notes,

                        COALESCE(email_smtp_valid, 0)               AS is_verified,

                        CASE
                            WHEN beat IS NOT NULL AND beat != ''
                                THEN JSON_ARRAY(beat)
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

                        '[]'                                        AS platforms,
                        'website'                                   AS primary_platform,
                        1                                           AS created_by,

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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('press_contacts → influenceurs import skipped: ' . $e->getMessage());
            }
        }

        $pressInserted = DB::selectOne('SELECT COUNT(*) as n FROM influenceurs WHERE source = ?', ['press_import'])->n ?? 0;

        \Illuminate\Support\Facades\Log::info("Fusion contacts : {$lawyersInserted} avocats + {$pressInserted} journalistes importés dans influenceurs.");
    }

    public function down(): void
    {
        DB::table('influenceurs')
            ->whereIn('source', ['lawyers_import', 'press_import'])
            ->delete();
    }
};
