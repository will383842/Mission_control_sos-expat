<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add duplicate_of_id to content_articles if not exists
        if (!Schema::hasColumn('content_articles', 'duplicate_of_id')) {
            Schema::table('content_articles', function (Blueprint $table) {
                $table->unsignedBigInteger('duplicate_of_id')->nullable()->after('processing_status');
                $table->foreign('duplicate_of_id')->references('id')->on('content_articles')->onDelete('set null');
            });
        }

        // Fix processing_status default to 'new'
        if (Schema::hasColumn('content_articles', 'processing_status')) {
            DB::statement("ALTER TABLE content_articles ALTER COLUMN processing_status SET DEFAULT 'new'");
        }

        // Content opportunities table
        if (Schema::hasTable('content_opportunities')) {
            return; // Tables already created via direct SQL
        }
        Schema::create('content_opportunities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('country_slug', 100)->nullable()->index();
            $table->string('theme', 50)->nullable()->index();
            $table->string('question_title', 500);
            $table->integer('views')->default(0);
            $table->integer('replies')->default(0);
            $table->integer('matching_articles')->default(0);
            $table->decimal('priority_score', 12, 0)->default(0)->index();
            $table->string('status', 20)->default('opportunity')->index();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('content_questions')->onDelete('cascade');
        });

        // Monetizable themes table
        Schema::create('monetizable_themes', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 100);
            $table->string('country', 100)->nullable();
            $table->string('country_slug', 100)->nullable();
            $table->text('affiliate_programs')->nullable(); // JSON array stored as text
            $table->integer('nb_affiliate_links')->default(0);
            $table->integer('nb_existing_articles')->default(0);
            $table->integer('nb_qa_questions')->default(0);
            $table->integer('qa_total_views')->default(0);
            $table->decimal('monetization_score', 12, 0)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Country profiles materialized view
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS country_profiles AS
            WITH article_stats AS (
                SELECT
                    cc.id as country_id, cc.name as country_name, cc.slug as country_slug, cc.continent,
                    COUNT(ca.id) FILTER (WHERE ca.processing_status NOT IN ('duplicate', 'low_quality')) as total_articles,
                    COUNT(DISTINCT cs.id) as nb_sources,
                    STRING_AGG(DISTINCT cs.name, ', ' ORDER BY cs.name) as sources,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'visa' OR ca.title ~* 'visa') as visa_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'emploi' OR ca.title ~* '(emploi|travaill|work|job)') as emploi_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'logement' OR ca.title ~* '(loger|logement|hous|rent|apartment)') as logement_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'sante' OR ca.title ~* '(sant|health|médecin|doctor|hospital)') as sante_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'banque' OR ca.title ~* '(banque|bank|financ)') as banque_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'education' OR ca.title ~* '(education|école|school|universit)') as education_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'transport' OR ca.title ~* '(transport|conduire|driv)') as transport_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'telecom' OR ca.title ~* '(telecom|internet|phone|mobile)') as telecom_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'culture' OR ca.title ~* '(cultur|tradition|coutume|custom)') as culture_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'demarches' OR ca.title ~* '(démarche|administrat|paperwork)') as demarches_articles,
                    ROUND(AVG(ca.word_count)) as avg_word_count,
                    SUM(ca.word_count) as total_words
                FROM content_countries cc
                LEFT JOIN content_articles ca ON ca.country_id = cc.id
                LEFT JOIN content_sources cs ON ca.source_id = cs.id
                GROUP BY cc.id, cc.name, cc.slug, cc.continent
            ),
            qa_stats AS (
                SELECT country_slug, COUNT(*) as total_questions, SUM(views) as total_views, SUM(replies) as total_replies
                FROM content_questions WHERE country_slug IS NOT NULL GROUP BY country_slug
            )
            SELECT
                a.*,
                COALESCE(q.total_questions, 0) as total_questions,
                COALESCE(q.total_views, 0) as total_qa_views,
                COALESCE(q.total_replies, 0) as total_qa_replies,
                CASE WHEN a.total_articles > 0 THEN ROUND(COALESCE(q.total_views, 0)::numeric / a.total_articles) ELSE COALESCE(q.total_views, 0) END as priority_score,
                (CASE WHEN a.visa_articles > 0 THEN 1 ELSE 0 END + CASE WHEN a.emploi_articles > 0 THEN 1 ELSE 0 END +
                 CASE WHEN a.logement_articles > 0 THEN 1 ELSE 0 END + CASE WHEN a.sante_articles > 0 THEN 1 ELSE 0 END +
                 CASE WHEN a.banque_articles > 0 THEN 1 ELSE 0 END + CASE WHEN a.education_articles > 0 THEN 1 ELSE 0 END +
                 CASE WHEN a.transport_articles > 0 THEN 1 ELSE 0 END + CASE WHEN a.telecom_articles > 0 THEN 1 ELSE 0 END +
                 CASE WHEN a.culture_articles > 0 THEN 1 ELSE 0 END + CASE WHEN a.demarches_articles > 0 THEN 1 ELSE 0 END
                ) as thematic_coverage
            FROM article_stats a
            LEFT JOIN qa_stats q ON q.country_slug = a.country_slug
        ");

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS country_profiles_country_id_idx ON country_profiles(country_id)");
    }

    public function down(): void
    {
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS country_profiles");
        Schema::dropIfExists('monetizable_themes');
        Schema::dropIfExists('content_opportunities');

        if (Schema::hasColumn('content_articles', 'duplicate_of_id')) {
            Schema::table('content_articles', function (Blueprint $table) {
                $table->dropForeign(['duplicate_of_id']);
                $table->dropColumn('duplicate_of_id');
            });
        }
    }
};
