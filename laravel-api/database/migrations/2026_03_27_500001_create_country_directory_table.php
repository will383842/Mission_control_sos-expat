<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country Directory — Single source of truth for all expat resources by country.
 *
 * Used by:
 * 1. Article generation pipeline (inject external links + contact info)
 * 2. Blog external_links table (synced via API or seeder)
 * 3. Blog article display (embassy info, emergency numbers, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_directory', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();           // ISO 3166-1 alpha-2
            $table->string('country_name', 100);
            $table->string('country_slug', 100)->index();
            $table->string('continent', 50)->index();

            // Classification
            $table->string('category', 50)->index();            // ambassade, immigration, sante, banque, logement, emploi, education, transport, telecom, urgences, communaute, fiscalite, juridique
            $table->string('sub_category', 100)->nullable();    // e.g. "assurance-maladie", "colocation", "train"

            // Link info
            $table->string('title', 300);                       // "Ambassade de France en Allemagne"
            $table->string('url', 1000);                        // https://de.ambafrance.org/
            $table->string('domain', 255);                      // de.ambafrance.org
            $table->text('description')->nullable();            // Description utile pour la generation IA
            $table->string('language', 10)->default('fr');      // Langue du site

            // Contact details
            $table->string('address', 500)->nullable();         // "Pariser Platz 5, 10117 Berlin"
            $table->string('city', 100)->nullable();            // "Berlin"
            $table->string('phone', 100)->nullable();           // "+49 30 91588060"
            $table->string('phone_emergency', 100)->nullable(); // "+49 1608806313"
            $table->string('email', 255)->nullable();           // "consulat.berlin-amba@diplomatie.gouv.fr"
            $table->string('opening_hours', 500)->nullable();   // "Lundi-Vendredi 8h30-12h30 14h-17h"
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Emergency info
            $table->string('emergency_number', 50)->nullable(); // "112" (numero unique du pays)

            // Trust & quality
            $table->unsignedSmallInteger('trust_score')->default(80);  // 0-100
            $table->boolean('is_official')->default(true);      // site gouvernemental/officiel
            $table->boolean('is_active')->default(true);

            // SEO linking
            $table->string('anchor_text', 300)->nullable();     // Texte d'ancre suggere pour les articles
            $table->string('rel_attribute', 50)->default('noopener'); // noopener, nofollow, sponsored

            $table->timestamps();

            // Prevent exact duplicates
            $table->unique(['country_code', 'url'], 'country_directory_unique_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_directory');
    }
};
