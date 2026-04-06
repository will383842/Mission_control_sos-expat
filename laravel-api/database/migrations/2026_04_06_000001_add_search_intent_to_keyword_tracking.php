<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_tracking', function (Blueprint $table) {
            $table->string('search_intent', 30)->nullable()->after('type')
                ->comment('informational, commercial_investigation, transactional, local, urgency, navigational');
            $table->index('search_intent');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_tracking', function (Blueprint $table) {
            $table->dropIndex(['search_intent']);
            $table->dropColumn('search_intent');
        });
    }
};
