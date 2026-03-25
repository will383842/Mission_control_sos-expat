<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand outreach_configs with Calendly + custom prompt
        Schema::table('outreach_configs', function (Blueprint $table) {
            $table->string('calendly_url')->nullable()->after('daily_limit');
            $table->unsignedTinyInteger('calendly_step')->nullable()->after('calendly_url');
            $table->text('custom_prompt')->nullable()->after('calendly_step');
            $table->string('from_name', 100)->default('Williams')->after('custom_prompt');
        });

        // Email events for detailed tracking
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outreach_email_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 30); // sent, delivered, clicked, bounced, complained, unsubscribed
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['outreach_email_id', 'event_type']);
            $table->index('occurred_at');
        });

        // Domain health monitoring
        Schema::create('domain_health', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 100)->unique();
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_delivered')->default(0);
            $table->unsignedInteger('total_bounced')->default(0);
            $table->unsignedInteger('total_complained')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('complaint_rate', 5, 2)->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_health');
        Schema::dropIfExists('email_events');
        Schema::table('outreach_configs', function (Blueprint $table) {
            $table->dropColumn(['calendly_url', 'calendly_step', 'custom_prompt', 'from_name']);
        });
    }
};
