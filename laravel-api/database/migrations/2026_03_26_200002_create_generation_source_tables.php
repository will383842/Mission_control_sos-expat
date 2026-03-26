<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generation_source_categories')) {
            return; // Already created via direct SQL
        }

        Schema::create('generation_source_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('generation_source_items', function (Blueprint $table) {
            $table->id();
            $table->string('category_slug', 50)->index();
            $table->string('source_type', 20)->default('article')->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title', 500);
            $table->string('country', 100)->nullable();
            $table->string('country_slug', 100)->nullable()->index();
            $table->string('theme', 50)->nullable()->index();
            $table->string('sub_category', 100)->nullable()->index();
            $table->string('language', 10)->default('fr');
            $table->integer('word_count')->default(0);
            $table->integer('quality_score')->default(0)->index();
            $table->boolean('is_cleaned')->default(false)->index();
            $table->jsonb('data_json')->nullable();
            $table->string('processing_status', 20)->default('raw')->index();
            $table->integer('used_count')->default(0);
            $table->timestamps();

            $table->foreign('category_slug')->references('slug')->on('generation_source_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_source_items');
        Schema::dropIfExists('generation_source_categories');
    }
};
