<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_problems', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 150)->unique();
            // Ex: 'visa-refuse-dossier-incomplet' — identifiant stable

            $table->string('title', 300);
            // Ex: 'Visa refusé pour dossier incomplet'

            $table->string('category', 100);
            // Ex: 'immigration', 'legal', 'finance', 'housing', 'medical'

            $table->string('subcategory', 100)->nullable();

            $table->string('intent', 50)->default('information');
            // 'urgence' | 'information' | 'rassurance' | 'action' | 'comparaison'

            $table->unsignedSmallInteger('urgency_score')->default(0);
            // 1-10 (de sos_expat_fichier.json)

            $table->string('business_value', 10)->default('mid');
            // 'low' | 'mid' | 'high'

            $table->string('product_route', 20)->default('mixed');
            // 'lawyer' | 'helper' | 'content' | 'mixed'

            $table->boolean('needs_lawyer')->default(false);
            $table->boolean('needs_helper')->default(false);
            $table->boolean('monetizable')->default(true);

            $table->string('lp_angle', 500)->nullable();
            // Angle narratif pour orienter la génération IA

            $table->text('faq_seed')->nullable();
            // Questions de base pour la FAQ (string, pas JSON)

            $table->jsonb('search_queries_seed')->nullable();
            // ['query1', 'query2'] — requêtes Google cibles

            $table->jsonb('user_profiles')->nullable();
            // ['expatrie', 'digital_nomade', ...]

            $table->jsonb('tags')->nullable();

            $table->string('status', 20)->default('active');
            // 'active' | 'draft' | 'archived'

            $table->timestamps();

            // Index pour filtrage UI
            $table->index('category');
            $table->index('intent');
            $table->index('business_value');
            $table->index('product_route');
            $table->index('status');
            $table->index('urgency_score');
            $table->index(['needs_lawyer', 'status']);
            $table->index(['needs_helper', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_problems');
    }
};
