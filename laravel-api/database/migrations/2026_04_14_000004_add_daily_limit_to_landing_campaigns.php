<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_campaigns', function (Blueprint $table) {
            $table->integer('daily_limit')->default(0)->after('pages_per_country');
            // 0 = illimité. Si > 0, bloque le lancement dès que landing_pages.created_at::date = TODAY
            // atteint ce seuil pour cette audience_type.
        });
    }

    public function down(): void
    {
        Schema::table('landing_campaigns', function (Blueprint $table) {
            $table->dropColumn('daily_limit');
        });
    }
};
