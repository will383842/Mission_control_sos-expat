<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-platform social_post_comments table.
 *
 * Supersedes linkedin_post_comments. Comments are polled from each platform's
 * API (LinkedIn, Threads) or pushed via webhook (Facebook/Instagram Graph).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_post_comments')) return;

        Schema::create('social_post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('platform', 20);                      // denormalized for fast queries
            $table->string('platform_comment_id', 255);          // LinkedIn urn:li:comment:{id}, FB/IG numeric id, Threads id

            $table->string('author_name')->nullable();
            $table->string('author_platform_id')->nullable();    // author urn / user id
            $table->text('comment_text');
            $table->timestamp('commented_at')->nullable();

            // Telegram bridge
            $table->timestamp('telegram_notified_at')->nullable();
            $table->unsignedBigInteger('telegram_msg_id')->nullable();

            // Reply tracking
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('reply_source', 15)->nullable();      // variant | custom | manual

            $table->timestamps();

            $table->unique(['platform', 'platform_comment_id'], 'uniq_social_comment_platform_id');
            $table->index(['social_post_id', 'created_at']);
        });

        DB::statement("ALTER TABLE social_post_comments ADD CONSTRAINT social_post_comments_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");

        DB::statement("ALTER TABLE social_post_comments ADD CONSTRAINT social_post_comments_reply_source_check CHECK (
            reply_source IS NULL OR reply_source::text = ANY (ARRAY['variant','custom','manual']::text[])
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_comments');
    }
};
