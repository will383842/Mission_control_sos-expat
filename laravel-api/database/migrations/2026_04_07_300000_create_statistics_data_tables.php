<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogue des indicateurs disponibles par source
        Schema::create('statistics_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);              // e.g. "SM.POP.TOTL" (World Bank) or "MIG" (OECD)
            $table->string('name');                    // Human-readable name
            $table->string('source', 50);              // world_bank, oecd, eurostat, un, perplexity
            $table->string('theme', 50);               // expatries, voyageurs, nomades, etudiants, investisseurs
            $table->string('unit', 50)->default('persons'); // persons, percent, usd, index
            $table->string('description')->nullable();
            $table->string('api_endpoint')->nullable(); // Full API URL template
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'source'], 'indicator_code_source_unique');
            $table->index('theme');
            $table->index('source');
        });

        // Data points individuels — 1 chiffre, 1 pays, 1 année, 1 source
        Schema::create('statistics_data_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('indicator_id');
            $table->string('indicator_code', 100);     // Dénormalisé pour perf
            $table->string('indicator_name');           // Dénormalisé pour perf
            $table->string('country_code', 3);          // ISO 3166-1 alpha-2 (ou alpha-3 pour certaines sources)
            $table->string('country_name', 100);
            $table->unsignedSmallInteger('year');
            $table->decimal('value', 20, 4);            // Valeur numérique
            $table->string('unit', 50)->default('persons');
            $table->string('source', 50);               // world_bank, oecd, eurostat, un, perplexity
            $table->string('source_dataset', 100)->nullable(); // e.g. "WDI", "OECD.MIG"
            $table->string('source_url', 1000)->nullable();    // URL vérifiable
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->foreign('indicator_id')->references('id')->on('statistics_indicators')->onDelete('cascade');
            $table->unique(['indicator_id', 'country_code', 'year'], 'datapoint_unique');
            $table->index('country_code');
            $table->index('year');
            $table->index('source');
            $table->index(['country_code', 'year']);
            $table->index(['indicator_code', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics_data_points');
        Schema::dropIfExists('statistics_indicators');
    }
};
