<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('external_url', 1000)->nullable()->after('canonical_url');
            $table->string('external_id', 255)->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'external_id']);
        });
    }
};
