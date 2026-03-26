<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->string('title', 500);
            $table->string('url', 1000);
            $table->string('url_hash', 64)->unique();
            $table->string('country', 100)->nullable();
            $table->string('country_slug', 100)->nullable();
            $table->string('continent', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedInteger('replies')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->boolean('is_sticky')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('last_post_date', 50)->nullable();
            $table->string('last_post_author', 100)->nullable();
            $table->string('language', 10)->default('fr');

            // Content creation workflow
            $table->string('article_status', 20)->default('new'); // new, planned, writing, published, skipped
            $table->text('article_notes')->nullable();

            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index('source_id');
            $table->index('country_slug');
            $table->index('continent');
            $table->index('article_status');
            $table->index('views');
            $table->index('replies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_questions');
    }
};
