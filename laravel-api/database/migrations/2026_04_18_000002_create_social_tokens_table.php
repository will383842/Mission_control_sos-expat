<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-platform social_tokens table.
 *
 * Supersedes linkedin_tokens. One row per (platform, account_type) pair.
 * access_token / refresh_token are AES-encrypted by the SocialToken model.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_tokens')) return;

        Schema::create('social_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);                 // linkedin | facebook | threads | instagram
            $table->string('account_type', 20);             // personal | page | business
            $table->text('access_token');                   // encrypted
            $table->text('refresh_token')->nullable();      // encrypted (some platforms don't issue one)
            $table->timestamp('expires_at')->nullable();    // facebook long-lived tokens have no hard expiry
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('platform_user_id', 150);        // LinkedIn URN, FB page id, IG business id, Threads user id
            $table->string('platform_user_name', 255)->nullable();
            $table->text('scope')->nullable();
            $table->json('metadata')->nullable();           // arbitrary per-platform data (page access_token for FB, app_id, ...)
            $table->timestamps();

            $table->unique(['platform', 'account_type'], 'uniq_social_tokens_platform_account');
        });

        DB::statement("ALTER TABLE social_tokens ADD CONSTRAINT social_tokens_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('social_tokens');
    }
};
