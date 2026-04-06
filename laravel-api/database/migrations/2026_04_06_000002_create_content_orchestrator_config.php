<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_orchestrator_config', function (Blueprint $table) {
            $table->id();
            $table->integer('daily_target')->default(20)->comment('Total articles per day (excluding RSS)');
            $table->integer('rss_daily_target')->default(10)->comment('RSS news per day (independent quota)');
            $table->boolean('auto_pilot')->default(false)->comment('Auto-generate and publish');
            $table->boolean('rss_auto_pilot')->default(true)->comment('RSS auto-generate (independent toggle)');
            $table->jsonb('type_distribution')->default('{}')->comment('Percentages per content type (must total 100)');
            $table->jsonb('priority_countries')->default('[]')->comment('Ordered priority country codes');
            $table->string('status')->default('paused')->comment('running, paused, stopped');
            $table->timestamp('last_run_at')->nullable();
            $table->integer('today_generated')->default(0);
            $table->integer('today_rss_generated')->default(0);
            $table->integer('today_cost_cents')->default(0);
            $table->boolean('telegram_alerts')->default(true)->comment('Send Telegram on errors/completion');
            $table->timestamps();
        });

        // Insert default config
        \Illuminate\Support\Facades\DB::table('content_orchestrator_config')->insert([
            'daily_target' => 20,
            'rss_daily_target' => 10,
            'auto_pilot' => false,
            'rss_auto_pilot' => true,
            'type_distribution' => json_encode([
                'qa' => 12,
                'art_mots_cles' => 10,
                'art_longues_traines' => 10,
                'guide' => 6,
                'guide_expat' => 6,
                'guide_vacances' => 6,
                'guide_city' => 10,
                'comparative' => 8,
                'affiliation' => 5,
                'outreach_chatters' => 4,
                'outreach_influenceurs' => 4,
                'outreach_admin_groupes' => 3,
                'outreach_avocats' => 3,
                'outreach_expats' => 3,
                'testimonial' => 5,
                'brand_content' => 5,
            ]),
            'priority_countries' => json_encode(['FR','US','GB','ES','DE','TH','PT','CA','AU','IT','AE','JP','SG','MA']),
            'status' => 'paused',
            'telegram_alerts' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('content_orchestrator_config');
    }
};
