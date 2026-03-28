<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_publications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->string('team_url')->nullable();    // /equipe, /redaction, etc.
            $table->string('contact_url')->nullable();
            $table->string('media_type')->default('web');
            // media_type: presse_ecrite | web | tv | radio
            $table->json('topics');
            // topics: ['entrepreneuriat', 'voyage', 'expatriation', 'international', 'business', 'tech']
            $table->string('language')->default('fr');
            $table->string('country')->default('France');
            $table->integer('contacts_count')->default(0);
            $table->string('status')->default('pending');
            // status: pending | scraped | failed | skipped
            $table->text('last_error')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('press_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('publication_id')->nullable();
            $table->foreign('publication_id')->references('id')->on('press_publications')->onDelete('set null');

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name');

            $table->string('email')->nullable()->index();
            $table->boolean('email_verified')->default(false);
            $table->string('phone')->nullable();

            $table->string('publication');      // denormalized for easy query
            $table->string('role')->nullable(); // "Rédacteur en chef", "Journaliste", "Correspondant"
            $table->string('beat')->nullable(); // "Entrepreneuriat", "Voyage", "International"
            $table->string('media_type')->default('web');

            $table->string('source_url')->nullable();   // page where found
            $table->string('profile_url')->nullable();  // journalist's own page
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();

            $table->string('country')->default('France');
            $table->string('city')->nullable();
            $table->string('language')->default('fr');

            $table->json('topics')->nullable();
            // ['entrepreneuriat', 'voyage', 'expatriation', 'international']

            $table->string('contact_status')->default('new');
            // new | contacted | replied | won | lost
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();

            $table->string('scraped_from')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'publication'], 'press_contacts_email_pub_unique');
            $table->index(['media_type', 'contact_status']);
            $table->index('beat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_contacts');
        Schema::dropIfExists('press_publications');
    }
};
