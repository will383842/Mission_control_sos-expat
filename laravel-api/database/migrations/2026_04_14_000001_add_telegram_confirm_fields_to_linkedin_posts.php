<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Telegram 1-tap confirm support to linkedin_posts.
 *
 * li_telegram_msg_id: Telegram message_id of the confirm message
 *                     (used to edit message after user taps ✅ / ❌)
 *
 * status can now also be 'pending_confirm' (waiting for admin tap)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('li_telegram_msg_id')->nullable()->after('publish_error_page');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn('li_telegram_msg_id');
        });
    }
};
