<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparatives', function (Blueprint $table) {
            $table->string('external_url', 1000)->nullable()->after('published_at');
            $table->string('external_id', 255)->nullable()->after('external_url');
        });

        Schema::table('qa_entries', function (Blueprint $table) {
            $table->string('external_url', 1000)->nullable()->after('published_at');
            $table->string('external_id', 255)->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('comparatives', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'external_id']);
        });
        Schema::table('qa_entries', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'external_id']);
        });
    }
};
