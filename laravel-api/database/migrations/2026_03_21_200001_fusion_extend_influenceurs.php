<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION Mission Control + Influenceurs Tracker
 * Extend influenceurs table with Mission Control fields + new pipeline statuses.
 * Defensive: checks hasColumn before adding to prevent duplicate column errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Convert status from enum to varchar (allows flexible statuses)
        DB::statement("ALTER TABLE influenceurs MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'new'");

        // 2. Ensure contact_type column exists (may already exist from migration 100001)
        if (!Schema::hasColumn('influenceurs', 'contact_type')) {
            Schema::table('influenceurs', function (Blueprint $table) {
                $table->string('contact_type', 50)->default('influenceur')->after('id');
            });
        }

        // 3. Add new columns from Mission Control (defensive)
        Schema::table('influenceurs', function (Blueprint $table) {
            if (!Schema::hasColumn('influenceurs', 'company')) {
                $table->string('company', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('influenceurs', 'position')) {
                $table->string('position', 255)->nullable()->after(Schema::hasColumn('influenceurs', 'company') ? 'company' : 'name');
            }
            if (!Schema::hasColumn('influenceurs', 'website_url')) {
                $table->string('website_url', 500)->nullable()->after('profile_url');
            }
            if (!Schema::hasColumn('influenceurs', 'deal_value_cents')) {
                $table->integer('deal_value_cents')->default(0)->after('status');
            }
            if (!Schema::hasColumn('influenceurs', 'deal_probability')) {
                $table->unsignedTinyInteger('deal_probability')->default(0)->after(Schema::hasColumn('influenceurs', 'deal_value_cents') ? 'deal_value_cents' : 'status');
            }
            if (!Schema::hasColumn('influenceurs', 'expected_close_date')) {
                $table->date('expected_close_date')->nullable()->after(Schema::hasColumn('influenceurs', 'deal_probability') ? 'deal_probability' : 'status');
            }
            if (!Schema::hasColumn('influenceurs', 'score')) {
                $table->unsignedSmallInteger('score')->default(0)->after('tags');
            }
            if (!Schema::hasColumn('influenceurs', 'source')) {
                $table->string('source', 100)->nullable()->after(Schema::hasColumn('influenceurs', 'score') ? 'score' : 'tags');
            }
            if (!Schema::hasColumn('influenceurs', 'timezone')) {
                $table->string('timezone', 50)->nullable()->after('language');
            }
        });

        // 4. Add composite indexes (defensive — check if they already exist)
        $existingIndexes = collect(DB::select("SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'influenceurs' GROUP BY INDEX_NAME"))->pluck('indexname')->toArray();

        Schema::table('influenceurs', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_inf_type_country_status', $existingIndexes)) {
                $table->index(['contact_type', 'country', 'status'], 'idx_inf_type_country_status');
            }
            if (!in_array('idx_inf_score', $existingIndexes)) {
                $table->index(['score'], 'idx_inf_score');
            }
            if (!in_array('idx_inf_source', $existingIndexes)) {
                $table->index(['source'], 'idx_inf_source');
            }
            if (!in_array('idx_inf_deal_value', $existingIndexes)) {
                $table->index(['deal_value_cents'], 'idx_inf_deal_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropIndex('idx_inf_type_country_status');
            $table->dropIndex('idx_inf_score');
            $table->dropIndex('idx_inf_source');
            $table->dropIndex('idx_inf_deal_value');

            $table->dropColumn([
                'company', 'position', 'website_url',
                'deal_value_cents', 'deal_probability', 'expected_close_date',
                'score', 'source', 'timezone',
            ]);
        });
    }
};
