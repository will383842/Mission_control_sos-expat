<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Audience & génération
            $table->string('audience_type', 20)->nullable()->after('country');
            // 'clients' | 'lawyers' | 'helpers' | 'matching'

            $table->string('template_id', 100)->nullable()->after('audience_type');
            // Clients: 'urgent'|'seo'|'trust'
            // Lawyers: 'general'|'urgent'|'freedom'|'income'|'premium'
            // Helpers: 'recruitment'|'opportunity'|'reassurance'
            // Matching: 'expert'|'lawyer'|'helper'

            $table->string('problem_id', 150)->nullable()->after('template_id');
            // Référence au slug du LandingProblem (VARCHAR, pas FK, re-seed safe)

            $table->string('country_code', 5)->nullable()->after('problem_id');
            // Code ISO 2 lettres (ex: 'TH', 'FR')

            $table->string('generation_source', 30)->default('manual')->after('country_code');
            // 'manual' | 'ai_generated'

            $table->jsonb('generation_params')->nullable()->after('generation_source');
            // Snapshot des params utilisés: {problem_slug, template_id, audience_type,
            //   language, country_code, urgency_score, business_value, lp_angle}

            // Index pour filtrage rapide
            $table->index('audience_type');
            $table->index('country_code');
            $table->index(['audience_type', 'country_code']);
            $table->index(['audience_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex(['audience_type']);
            $table->dropIndex(['country_code']);
            $table->dropIndex(['audience_type', 'country_code']);
            $table->dropIndex(['audience_type', 'status']);
            $table->dropColumn([
                'audience_type',
                'template_id',
                'problem_id',
                'country_code',
                'generation_source',
                'generation_params',
            ]);
        });
    }
};
