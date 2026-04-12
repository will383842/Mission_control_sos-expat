<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->jsonb('campaign_country_queue')->default('[]')->after('telegram_alerts')
                ->comment('Ordered array of ISO country codes for campaign');
            $table->integer('campaign_articles_per_country')->default(100)->after('campaign_country_queue')
                ->comment('Number of articles to generate per country before advancing');
        });

        // Seed initial queue order
        DB::table('content_orchestrator_config')->update([
            'campaign_country_queue' => json_encode([
                'TH', 'US', 'VN', 'SG', 'PT', 'ES', 'ID', 'MX', 'MA', 'AE',
                'JP', 'DE', 'GB', 'CA', 'AU', 'BR', 'CO', 'CR', 'GR', 'HR',
                'IT', 'NL', 'BE', 'CH', 'TR', 'PH', 'MY', 'KH', 'IN', 'PL',
            ]),
            'campaign_articles_per_country' => 100,
        ]);
    }

    public function down(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->dropColumn(['campaign_country_queue', 'campaign_articles_per_country']);
        });
    }
};
