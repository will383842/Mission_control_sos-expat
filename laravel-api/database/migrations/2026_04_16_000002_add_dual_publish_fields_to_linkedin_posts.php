<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * account=both strategy:
     *   - personal published first (at scheduled_at)
     *   - page published 4h30 later (at page_publish_after)
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE linkedin_posts
            ADD COLUMN page_publish_after   TIMESTAMP NULL AFTER auto_scheduled,
            ADD COLUMN page_published_at    TIMESTAMP NULL AFTER page_publish_after,
            ADD COLUMN publish_error_page   TEXT NULL AFTER page_published_at
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE linkedin_posts
            DROP COLUMN IF EXISTS publish_error_page,
            DROP COLUMN IF EXISTS page_published_at,
            DROP COLUMN IF EXISTS page_publish_after
        ");
    }
};
