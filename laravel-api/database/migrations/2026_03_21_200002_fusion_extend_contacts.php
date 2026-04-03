<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert channel and result from enum to varchar (defensive)
        DB::statement("ALTER TABLE contacts MODIFY COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'email'");
        DB::statement("ALTER TABLE contacts MODIFY COLUMN result VARCHAR(50) NULL");

        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'direction')) {
                $table->string('direction', 10)->default('outbound')->after('channel');
            }
            if (!Schema::hasColumn('contacts', 'subject')) {
                $table->string('subject', 500)->nullable()->after('result');
            }
            if (!Schema::hasColumn('contacts', 'email_opened_at')) {
                $table->timestamp('email_opened_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('contacts', 'email_clicked_at')) {
                $table->timestamp('email_clicked_at')->nullable()->after(Schema::hasColumn('contacts', 'email_opened_at') ? 'email_opened_at' : 'notes');
            }
            if (!Schema::hasColumn('contacts', 'template_used')) {
                $table->string('template_used', 100)->nullable()->after(Schema::hasColumn('contacts', 'email_clicked_at') ? 'email_clicked_at' : 'notes');
            }
        });

        $existingIndexes = collect(DB::select("SELECT INDEX_NAME as indexname FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' GROUP BY INDEX_NAME"))->pluck('indexname')->toArray();

        Schema::table('contacts', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_contacts_inf_date', $existingIndexes)) {
                $table->index(['influenceur_id', 'date'], 'idx_contacts_inf_date');
            }
            if (!in_array('idx_contacts_result', $existingIndexes)) {
                $table->index(['result'], 'idx_contacts_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_inf_date');
            $table->dropIndex('idx_contacts_result');
            $table->dropColumn(['direction', 'subject', 'email_opened_at', 'email_clicked_at', 'template_used']);
        });
    }
};
