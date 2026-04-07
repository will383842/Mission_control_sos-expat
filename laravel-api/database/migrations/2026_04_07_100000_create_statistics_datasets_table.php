<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistics_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('topic');                          // e.g. "expatriates", "digital_nomads"
            $table->string('theme');                          // e.g. "expatries", "voyageurs", "nomades", "etudiants", "investisseurs"
            $table->string('country_code', 2)->nullable();    // ISO 3166-1 alpha-2 (null = global)
            $table->string('country_name')->nullable();
            $table->string('title');                          // Generated or manual title
            $table->text('summary')->nullable();              // AI-generated summary/analysis
            $table->json('stats');                            // [{label, value, year, source_name, source_url}]
            $table->json('sources');                          // [{name, url, accessed_at}]
            $table->json('analysis')->nullable();             // AI cross-source analysis
            $table->unsignedTinyInteger('confidence_score')->default(0); // 0-100
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->enum('status', ['draft', 'validated', 'generating', 'published', 'failed'])->default('draft');
            $table->string('language', 5)->default('fr');
            $table->unsignedInteger('generated_article_id')->nullable();
            $table->timestamp('last_researched_at')->nullable();
            $table->timestamps();

            $table->index('theme');
            $table->index('country_code');
            $table->index('status');
            $table->index(['theme', 'country_code']);
            $table->unique(['topic', 'country_code', 'language'], 'stats_topic_country_lang_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics_datasets');
    }
};
