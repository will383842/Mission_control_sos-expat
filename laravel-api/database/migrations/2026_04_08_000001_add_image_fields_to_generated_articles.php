<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('featured_image_srcset', 1000)->nullable()->after('featured_image_attribution');
            $table->string('photographer_name', 300)->nullable()->after('featured_image_srcset');
            $table->string('photographer_url', 1000)->nullable()->after('photographer_name');
            $table->string('unsplash_photographer_name', 300)->nullable()->after('photographer_url');
            $table->string('unsplash_photographer_url', 1000)->nullable()->after('unsplash_photographer_name');
            $table->integer('image_width')->nullable()->after('unsplash_photographer_url');
            $table->integer('image_height')->nullable()->after('image_width');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn([
                'featured_image_srcset',
                'photographer_name',
                'photographer_url',
                'unsplash_photographer_name',
                'unsplash_photographer_url',
                'image_width',
                'image_height',
            ]);
        });
    }
};
