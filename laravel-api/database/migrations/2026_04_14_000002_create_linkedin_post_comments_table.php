<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks comments received on published LinkedIn posts.
 * Used by CheckLinkedInCommentsCommand to avoid sending duplicate Telegram notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linkedin_post_id')->constrained('linkedin_posts')->cascadeOnDelete();
            $table->string('comment_urn')->unique();   // urn:li:comment:{id}
            $table->string('author_name')->nullable();
            $table->string('author_urn')->nullable();  // urn:li:person:{id}
            $table->text('comment_text');
            $table->timestamp('commented_at')->nullable();

            // Telegram notification
            $table->timestamp('telegram_notified_at')->nullable();
            $table->unsignedBigInteger('telegram_msg_id')->nullable();

            // Reply tracking
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->enum('reply_source', ['variant', 'custom', 'manual'])->nullable();

            $table->timestamps();

            $table->index(['linkedin_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_post_comments');
    }
};
