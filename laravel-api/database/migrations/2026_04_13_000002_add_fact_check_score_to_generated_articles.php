<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->unsignedTinyInteger('fact_check_score')->nullable()->after('quality_score')
                ->comment('0-100, verified by FactCheckGuardService against DB stats');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn('fact_check_score');
        });
    }
};
