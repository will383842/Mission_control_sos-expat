<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Track when the first comment was actually posted to LinkedIn
        DB::statement("ALTER TABLE linkedin_posts
            ADD COLUMN first_comment_posted_at TIMESTAMP NULL AFTER featured_image_url,
            ADD COLUMN first_comment_status ENUM('pending','posted','failed') NULL AFTER first_comment_posted_at,
            ADD COLUMN reply_variants JSON NULL AFTER first_comment_status,
            ADD COLUMN auto_scheduled TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_variants
        ");

        // Index for the auto-publish cron (frequently queried)
        DB::statement("CREATE INDEX idx_linkedin_auto_publish ON linkedin_posts (status, scheduled_at)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_linkedin_auto_publish ON linkedin_posts");
        DB::statement("ALTER TABLE linkedin_posts
            DROP COLUMN IF EXISTS auto_scheduled,
            DROP COLUMN IF EXISTS reply_variants,
            DROP COLUMN IF EXISTS first_comment_status,
            DROP COLUMN IF EXISTS first_comment_posted_at
        ");
    }
};
