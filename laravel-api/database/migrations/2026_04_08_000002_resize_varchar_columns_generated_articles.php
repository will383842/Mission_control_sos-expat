<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('title', 500)->change();
            $table->string('featured_image_alt', 500)->nullable()->change();
            $table->string('featured_image_attribution', 500)->nullable()->change();
            $table->string('meta_title', 300)->nullable()->change();
            $table->string('meta_description', 500)->nullable()->change();
            $table->string('og_title', 300)->nullable()->change();
            $table->string('og_description', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('title', 300)->change();
            $table->string('featured_image_alt', 300)->nullable()->change();
            $table->string('featured_image_attribution', 300)->nullable()->change();
            $table->string('meta_title', 70)->nullable()->change();
            $table->string('meta_description', 170)->nullable()->change();
            $table->string('og_title', 100)->nullable()->change();
            $table->string('og_description', 200)->nullable()->change();
        });
    }
};
