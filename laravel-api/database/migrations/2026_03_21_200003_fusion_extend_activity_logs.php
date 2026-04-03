<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'is_manual')) {
                $table->boolean('is_manual')->default(false)->after('details');
            }
            if (!Schema::hasColumn('activity_logs', 'manual_note')) {
                $table->text('manual_note')->nullable()->after('is_manual');
            }
            if (!Schema::hasColumn('activity_logs', 'contact_type')) {
                $table->string('contact_type', 50)->nullable()->after('manual_note');
            }
        });

        $existingIndexes = collect(DB::select("SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' GROUP BY INDEX_NAME"))->pluck('indexname')->toArray();

        if (!in_array('idx_activity_journal', $existingIndexes)) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index(['user_id', 'is_manual', 'created_at'], 'idx_activity_journal');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_journal');
            $table->dropColumn(['is_manual', 'manual_note', 'contact_type']);
        });
    }
};
