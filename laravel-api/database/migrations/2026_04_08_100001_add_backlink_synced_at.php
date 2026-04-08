<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->timestamp('backlink_synced_at')->nullable()->after('quality_score');
        });

        Schema::table('press_contacts', function (Blueprint $table) {
            $table->timestamp('backlink_synced_at')->nullable()->after('email_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropColumn('backlink_synced_at');
        });

        Schema::table('press_contacts', function (Blueprint $table) {
            $table->dropColumn('backlink_synced_at');
        });
    }
};
