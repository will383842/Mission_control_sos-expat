<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lawyers', function (Blueprint $table) {
            $table->id();
            $table->string('source_slug', 100);
            $table->string('source_url', 1000);
            $table->string('url_hash', 64)->unique();

            $table->string('full_name', 300);
            $table->string('first_name', 150)->nullable();
            $table->string('last_name', 150)->nullable();
            $table->string('firm_name', 500)->nullable();
            $table->string('title', 200)->nullable();

            $table->string('email', 300);
            $table->string('phone', 100)->nullable();
            $table->string('website', 1000)->nullable();

            $table->string('country', 100);
            $table->string('country_code', 5)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('region', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('specialty', 300)->nullable();
            $table->json('specialties')->nullable();
            $table->string('bar_association', 300)->nullable();
            $table->string('bar_number', 100)->nullable();
            $table->string('language', 50)->nullable();
            $table->json('languages')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();

            $table->string('directory_category', 100)->nullable();
            $table->boolean('is_immigration_lawyer')->default(false);
            $table->boolean('is_francophone')->default(false);

            $table->boolean('email_verified')->default(false);
            $table->boolean('detail_scraped')->default(false);
            $table->string('enrichment_status', 30)->default('pending');
            $table->text('description')->nullable();
            $table->string('photo_url', 1000)->nullable();
            $table->json('social_links')->nullable();

            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index('source_slug');
            $table->index('email');
            $table->index('country');
            $table->index('country_code');
            $table->index('city');
            $table->index('specialty');
            $table->index('language');
            $table->index('is_immigration_lawyer');
            $table->index('is_francophone');
            $table->index('enrichment_status');
            $table->index('detail_scraped');
            $table->index(['country_code', 'specialty']);
            $table->index(['country_code', 'city']);
            $table->index(['source_slug', 'country_code']);
        });

        Schema::create('lawyer_directory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 200);
            $table->string('base_url', 1000);
            $table->string('country_scope', 100)->nullable();
            $table->string('language_scope', 50)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('total_lawyers')->default(0);
            $table->unsignedInteger('total_with_email')->default(0);
            $table->boolean('robots_txt_ok')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_directory_sources');
        Schema::dropIfExists('lawyers');
    }
};
