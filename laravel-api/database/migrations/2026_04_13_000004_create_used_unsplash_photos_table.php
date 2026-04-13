<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('used_unsplash_photos', function (Blueprint $table) {
            $table->id();
            // Unsplash photo identifier extracted from the URL, e.g.
            // "photo-1566830646346-908d87490bba". Used as the dedup key —
            // we never want the same photo twice across the blog.
            $table->string('photo_id', 80)->unique();
            $table->text('photo_url');
            $table->text('photographer_name')->nullable();
            $table->text('photographer_url')->nullable();
            // Where this photo was used. Nullable because we may backfill
            // legacy entries without knowing the originating article.
            $table->unsignedBigInteger('article_id')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('country', 8)->nullable();
            // The query that surfaced this photo (for analytics + future
            // exclusion strategies if we run out of fresh images).
            $table->string('source_query', 255)->nullable();
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();

            $table->index('article_id');
            $table->index('used_at');
            $table->index(['language', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_unsplash_photos');
    }
};
