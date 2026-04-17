<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-platform social_posts table.
 *
 * Supersedes linkedin_posts with a `platform` discriminator column.
 * Supported platforms: linkedin | facebook | threads | instagram.
 *
 * Data from linkedin_posts is backfilled via the command
 *   php artisan social:backfill-from-linkedin
 * once the LinkedIn driver (Phase 2) is deployed.
 *
 * PostgreSQL-compatible (VARCHAR + CHECK constraints, no native ENUM).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_posts')) return;

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();

            // Platform discriminator
            $table->string('platform', 20); // linkedin | facebook | threads | instagram

            // Source content (carried over from linkedin_posts)
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_title')->nullable();

            // Scheduling metadata
            $table->string('day_type', 15);                          // monday..saturday
            $table->string('lang', 10)->default('fr');               // fr | en | both
            $table->string('account_type', 20)->nullable();          // linkedin:personal|page, fb:page, threads:personal, ig:business

            // Content
            $table->text('hook');
            $table->text('body');
            $table->json('hashtags')->nullable();
            $table->text('first_comment')->nullable();
            $table->string('featured_image_url', 500)->nullable();

            // First comment tracking
            $table->timestamp('first_comment_posted_at')->nullable();
            $table->string('first_comment_status', 20)->nullable();  // pending | posted | failed | skipped
            $table->json('reply_variants')->nullable();

            // Lifecycle
            $table->string('status', 30)->default('draft');          // generating|draft|scheduled|pending_confirm|published|failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('auto_scheduled')->default(false);

            // Dual-publish (kept for LinkedIn personal+page strategy)
            $table->timestamp('page_publish_after')->nullable();
            $table->timestamp('page_published_at')->nullable();
            $table->text('publish_error_page')->nullable();

            // Platform-specific IDs
            $table->string('platform_post_id')->nullable();          // main id/urn on the platform
            $table->string('platform_post_id_secondary')->nullable(); // secondary (e.g. LinkedIn page when dual-publish)
            $table->json('platform_metadata')->nullable();           // permalinks, media ids, insights urls, ...

            // Telegram bridge
            $table->unsignedBigInteger('telegram_msg_id')->nullable();

            // Analytics (platform-agnostic)
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0);

            // Rollout phase & errors
            $table->unsignedTinyInteger('phase')->default(1);        // 1=FR dominant, 2=global
            $table->string('error_message')->nullable();

            $table->timestamps();

            $table->index(['platform', 'status', 'scheduled_at'], 'idx_social_posts_platform_status_sched');
            $table->index(['platform', 'day_type'], 'idx_social_posts_platform_day');
            $table->index(['platform', 'phase'], 'idx_social_posts_platform_phase');
            $table->index(['platform', 'source_type', 'source_id'], 'idx_social_posts_platform_source');
        });

        // CHECK constraints (Postgres) — mirror the final linkedin_posts constraints
        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");

        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_status_check CHECK (
            status::text = ANY (ARRAY[
                'generating','draft','scheduled','pending_confirm','published','failed'
            ]::text[])
        )");

        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_day_type_check CHECK (
            day_type::text = ANY (ARRAY[
                'monday','tuesday','wednesday','thursday','friday','saturday','sunday'
            ]::text[])
        )");

        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_source_type_check CHECK (
            source_type::text = ANY (ARRAY[
                'article','faq','sondage','hot_take','myth','poll','serie',
                'reactive','milestone','partner_story','counter_intuition',
                'tip','news','case_study'
            ]::text[])
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
