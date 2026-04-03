<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only create the view if the required tables exist
        if (!Schema::hasTable('content_cities')) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE VIEW city_profiles AS
            SELECT
                cc.id          AS city_id,
                cc.name        AS city_name,
                cc.slug        AS city_slug,
                cc.continent,
                cco.id         AS country_id,
                cco.name       AS country_name,
                cco.slug       AS country_slug,
                COUNT(ca.id)   AS total_articles,
                SUM(ca.word_count) AS total_words,
                COUNT(DISTINCT cs.id) AS nb_sources,
                SUM(CASE WHEN ca.category = 'visa'      OR ca.title REGEXP 'visa'                           THEN 1 ELSE 0 END) AS visa_articles,
                SUM(CASE WHEN ca.category = 'emploi'    OR ca.title REGEXP '(emploi|travaill|work|job)'     THEN 1 ELSE 0 END) AS emploi_articles,
                SUM(CASE WHEN ca.category = 'logement'  OR ca.title REGEXP '(loger|logement|hous|rent|apartment)' THEN 1 ELSE 0 END) AS logement_articles,
                SUM(CASE WHEN ca.category = 'sante'     OR ca.title REGEXP '(sant|health|médecin|doctor|hospital)' THEN 1 ELSE 0 END) AS sante_articles,
                SUM(CASE WHEN ca.category = 'banque'    OR ca.title REGEXP '(banque|bank|financ)'           THEN 1 ELSE 0 END) AS banque_articles,
                SUM(CASE WHEN ca.category = 'transport' OR ca.title REGEXP '(transport|conduire|driv)'      THEN 1 ELSE 0 END) AS transport_articles,
                SUM(CASE WHEN ca.category = 'culture'   OR ca.title REGEXP '(cultur|tradition|coutume|custom)' THEN 1 ELSE 0 END) AS culture_articles,
                ROUND(AVG(ca.word_count)) AS avg_word_count,
                (
                    CASE WHEN SUM(CASE WHEN ca.category = 'visa'      OR ca.title REGEXP 'visa'                        THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'emploi'    OR ca.title REGEXP '(emploi|travaill|work|job)'  THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'logement'  OR ca.title REGEXP '(loger|logement|hous|rent)'  THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'sante'     OR ca.title REGEXP '(sant|health|médecin|doctor)' THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'banque'    OR ca.title REGEXP '(banque|bank|financ)'        THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'transport' OR ca.title REGEXP '(transport|conduire|driv)'   THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END +
                    CASE WHEN SUM(CASE WHEN ca.category = 'culture'   OR ca.title REGEXP '(cultur|tradition|coutume)'  THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END
                ) AS thematic_coverage,
                (COUNT(ca.id) * 10 + COALESCE(SUM(ca.word_count), 0) / 100) AS priority_score
            FROM content_cities cc
            LEFT JOIN content_countries cco ON cco.id = cc.country_id
            LEFT JOIN content_articles ca   ON ca.city_id = cc.id
            LEFT JOIN content_sources cs    ON ca.source_id = cs.id
            GROUP BY cc.id, cc.name, cc.slug, cc.continent, cco.id, cco.name, cco.slug
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS city_profiles");
    }
};
