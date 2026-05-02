<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds campaign_priority_window_size to content_orchestrator_config.
 *
 * The orchestrator now runs a ROUND-ROBIN on the first N countries of
 * campaign_country_queue (default N=12, the Asia/Pacific priority list).
 * The country with the fewest articles in that window is picked next, so
 * all priority countries progress in parallel rather than finishing one
 * before starting the next.
 *
 * Once all N priority countries reach campaign_articles_per_country, the
 * remaining ~185 countries are handled sequentially (legacy behaviour).
 *
 * Set to 0 to disable round-robin and revert to fully sequential mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->unsignedSmallInteger('campaign_priority_window_size')
                ->default(12)
                ->after('campaign_distribution_mode');
        });
    }

    public function down(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->dropColumn('campaign_priority_window_size');
        });
    }
};
