<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Template type / preset
            $table->string('preset_type', 50)->default('custom');
            // preset_type values: mots-cles, longues-traines, rec-avocats, rec-expats, visa-pays, cout-vie, custom

            $table->string('content_type', 50)->default('article');
            // article, guide, tutorial, news, qa, comparative

            // Title template with variables: "Comment obtenir un visa {pays}"
            $table->string('title_template', 500);

            // Variables definition: [{name: "pays", type: "country", required: true}]
            $table->jsonb('variables')->default('[]');

            // Expansion mode: how variables are expanded
            $table->string('expansion_mode', 30)->default('manual');
            // manual, all_countries, selected_countries, custom_list

            // Selected values for expansion (country codes, city names, etc.)
            $table->jsonb('expansion_values')->default('[]');

            // Generation config
            $table->string('language', 5)->default('fr');
            $table->string('tone', 30)->default('professional');
            $table->string('article_length', 20)->default('medium');
            $table->text('generation_instructions')->nullable();
            $table->boolean('generate_faq')->default(true);
            $table->integer('faq_count')->default(6);
            $table->boolean('research_sources')->default(true);
            $table->boolean('auto_internal_links')->default(true);
            $table->boolean('auto_affiliate_links')->default(true);
            $table->boolean('auto_translate')->default(true);
            $table->string('image_source', 20)->default('unsplash');

            // Stats
            $table->integer('total_items')->default(0);
            $table->integer('generated_items')->default(0);
            $table->integer('published_items')->default(0);
            $table->integer('failed_items')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['preset_type', 'is_active']);
            $table->index('content_type');
        });

        // Template items — one row per expanded variable combination
        Schema::create('content_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('content_templates')->cascadeOnDelete();

            // Expanded title: "Comment obtenir un visa Allemagne"
            $table->string('expanded_title', 500);

            // Variable values: {"pays": "Allemagne", "pays_code": "DE"}
            $table->jsonb('variable_values')->default('{}');

            // Status tracking
            $table->string('status', 20)->default('pending');
            // pending, optimizing, generating, published, failed, skipped

            // Link to generated article (after generation)
            $table->unsignedBigInteger('generated_article_id')->nullable();
            $table->string('optimized_title', 500)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->integer('generation_cost_cents')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('generated_article_id')->references('id')->on('generated_articles')->nullOnDelete();
            $table->index(['template_id', 'status']);
            $table->unique(['template_id', 'expanded_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_template_items');
        Schema::dropIfExists('content_templates');
    }
};
