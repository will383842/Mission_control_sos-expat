<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->timestamp('scraped_at')->nullable()->after('source');
            $table->string('scraper_status', 20)->nullable()->after('scraped_at');  // pending, completed, failed, skipped
            $table->jsonb('scraped_emails')->nullable()->after('scraper_status');
            $table->jsonb('scraped_phones')->nullable()->after('scraped_emails');
            $table->jsonb('scraped_social')->nullable()->after('scraped_phones');

            // Index for the batch query: find unscraped contacts with URLs
            $table->index(['scraped_at', 'contact_type', 'created_at'], 'idx_inf_scraper_batch');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropIndex('idx_inf_scraper_batch');
            $table->dropColumn([
                'scraped_at',
                'scraper_status',
                'scraped_emails',
                'scraped_phones',
                'scraped_social',
            ]);
        });
    }
};
