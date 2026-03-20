<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->string('profile_url_domain')->nullable()->after('profile_url');
            $table->index('profile_url_domain');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropIndex(['profile_url_domain']);
            $table->dropColumn('profile_url_domain');
        });
    }
};
