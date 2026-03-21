<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: AI Research sessions (from Mission Control's 3-parallel AI search).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_research_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('contact_type', 50);
            $table->string('country', 100);
            $table->string('language', 10)->default('fr');

            // Raw AI responses
            $table->text('claude_response')->nullable();
            $table->text('perplexity_response')->nullable();
            $table->text('tavily_response')->nullable();

            // Parsed results
            $table->json('parsed_contacts')->nullable();
            $table->json('excluded_domains')->nullable();
            $table->unsignedSmallInteger('contacts_found')->default(0);
            $table->unsignedSmallInteger('contacts_imported')->default(0);
            $table->unsignedSmallInteger('contacts_duplicates')->default(0);

            // Cost tracking
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);

            // Status
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_ai_research_user');
            $table->index(['contact_type', 'country'], 'idx_ai_research_type_country');
            $table->index('status', 'idx_ai_research_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_research_sessions');
    }
};
