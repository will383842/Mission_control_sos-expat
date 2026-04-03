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
                // Use nullable after() only when the reference column exists
                $col = $table->unsignedBigInteger('duplicate_of_id')->nullable();
                if (Schema::hasColumn('content_articles', 'processing_status')) {
                    $col->after('processing_status');
                }
                $table->foreign('duplicate_of_id')->references('id')->on('content_articles')->onDelete('set null');
            });
        }

        // Fix processing_status default to 'new' (MySQL syntax)
        if (Schema::hasColumn('content_articles', 'processing_status')) {
            DB::statement("ALTER TABLE content_articles MODIFY COLUMN processing_status VARCHAR(50) NOT NULL DEFAULT 'new'");
        }

        // Content opportunities table
        if (!Schema::hasTable('content_opportunities')) {
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
        }

        // Monetizable themes table
        if (!Schema::hasTable('monetizable_themes')) {
            Schema::create('monetizable_themes', function (Blueprint $table) {
                $table->id();
                $table->string('theme', 100);
                $table->string('country', 100)->nullable();
                $table->string('country_slug', 100)->nullable();
                $table->text('affiliate_programs')->nullable();
                $table->integer('nb_affiliate_links')->default(0);
                $table->integer('nb_existing_articles')->default(0);
                $table->integer('nb_qa_questions')->default(0);
                $table->integer('qa_total_views')->default(0);
                $table->decimal('monetization_score', 12, 0)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Country profiles — MySQL VIEW (not MATERIALIZED VIEW, which is PostgreSQL-only)
        if (!Schema::hasTable('country_profiles')) {
            DB::statement("
                CREATE OR REPLACE VIEW country_profiles AS
                SELECT
                    cc.id AS country_id,
                    cc.name AS country_name,
                    cc.slug AS country_slug,
                    cc.continent,
                    COUNT(ca.id) AS total_articles,
                    COUNT(DISTINCT cs.id) AS nb_sources,
                    GROUP_CONCAT(DISTINCT cs.name ORDER BY cs.name SEPARATOR ', ') AS sources,
                    SUM(CASE WHEN ca.category = 'visa' OR ca.title REGEXP 'visa' THEN 1 ELSE 0 END) AS visa_articles,
                    SUM(CASE WHEN ca.category = 'emploi' OR ca.title REGEXP '(emploi|travaill|work|job)' THEN 1 ELSE 0 END) AS emploi_articles,
                    SUM(CASE WHEN ca.category = 'logement' OR ca.title REGEXP '(loger|logement|hous|rent|apartment)' THEN 1 ELSE 0 END) AS logement_articles,
                    SUM(CASE WHEN ca.category = 'sante' OR ca.title REGEXP '(sant|health|médecin|doctor|hospital)' THEN 1 ELSE 0 END) AS sante_articles,
                    ROUND(AVG(ca.word_count)) AS avg_word_count,
                    SUM(ca.word_count) AS total_words,
                    COALESCE(q.total_questions, 0) AS total_questions,
                    COALESCE(q.total_views, 0) AS total_qa_views,
                    CASE WHEN COUNT(ca.id) > 0 THEN ROUND(COALESCE(q.total_views, 0) / COUNT(ca.id)) ELSE COALESCE(q.total_views, 0) END AS priority_score
                FROM content_countries cc
                LEFT JOIN content_articles ca ON ca.country_id = cc.id
                LEFT JOIN content_sources cs ON ca.source_id = cs.id
                LEFT JOIN (
                    SELECT country_slug, COUNT(*) AS total_questions, SUM(views) AS total_views, SUM(replies) AS total_replies
                    FROM content_questions
                    WHERE country_slug IS NOT NULL
                    GROUP BY country_slug
                ) q ON q.country_slug = cc.slug
                GROUP BY cc.id, cc.name, cc.slug, cc.continent, q.total_questions, q.total_views, q.total_replies
            ");
        }
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS country_profiles");
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
