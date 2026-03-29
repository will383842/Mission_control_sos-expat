<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des imports de l'annuaire (Wikidata, OpenStreetMap, Claude AI).
 * Permet de lancer et monitorer les imports depuis la console d'administration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annuaire_import_jobs', function (Blueprint $table) {
            $table->id();

            // Source de données
            $table->string('source', 30)->index();
            // wikidata   = ambassades (toutes nationalités, 9 langues)
            // overpass   = institutions physiques via OpenStreetMap (hôpitaux, banques, gares…)
            // claude     = liens web officiels via Claude AI (immigration, fiscalité, emploi…)

            // Périmètre d'import
            $table->string('scope_type', 30)->nullable();
            // nationality = filtrer par nationalité (wikidata)
            // country     = filtrer par pays hôte (overpass/claude)
            // all         = tous

            $table->text('scope_value')->nullable();
            // CSV de codes ISO : "DE,FR,ES" — ou null pour "all"

            $table->json('categories')->nullable();
            // null = toutes, sinon : ["ambassade"] ou ["sante","hopitaux","banque"]

            // Progression
            $table->string('status', 20)->default('pending')->index();
            // pending | running | completed | failed | cancelled

            $table->integer('total_expected')->default(0);
            $table->integer('total_processed')->default(0);
            $table->integer('total_inserted')->default(0);
            $table->integer('total_updated')->default(0);
            $table->integer('total_errors')->default(0);

            // Logs temps réel (chaque ligne = une entrée de log)
            $table->longText('log')->nullable();

            // Message d'erreur si failed
            $table->text('error_message')->nullable();

            // Qui a lancé l'import
            $table->string('launched_by', 100)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annuaire_import_jobs');
    }
};
