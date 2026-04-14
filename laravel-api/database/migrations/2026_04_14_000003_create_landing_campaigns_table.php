<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 4 lignes fixes dans cette table — une par audience_type
        // Le constraint UNIQUE sur audience_type garantit 1 campagne par type
        Schema::create('landing_campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('audience_type', 20)->unique();
            // 'clients' | 'lawyers' | 'helpers' | 'matching'

            $table->jsonb('country_queue')->default('[]');
            // Codes ISO ordonnés: ['TH', 'VN', 'SG', 'MY', ...]

            $table->string('current_country', 5)->nullable();
            // Code ISO du pays actuellement en cours

            $table->integer('pages_per_country')->default(10);
            // Nb max de LPs à générer par pays par lancement

            $table->jsonb('selected_templates')->default('[]');
            // Clients: ['urgent','seo','trust']
            // Lawyers: ['general','urgent']
            // Helpers: ['recruitment','opportunity','reassurance']
            // Matching: ['expert','lawyer','helper']

            $table->jsonb('problem_filters')->nullable();
            // Uniquement pour audience_type = 'clients'
            // {categories: ['immigration'], min_urgency: 5, business_values: ['high','mid']}

            $table->string('status', 20)->default('idle');
            // 'idle' | 'running' | 'paused' | 'completed'

            $table->integer('total_generated')->default(0);
            // Compteur total LPs générées (toutes langues confondues)

            $table->integer('total_cost_cents')->default(0);
            // Budget consommé en centimes

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_campaigns');
    }
};
