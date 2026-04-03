<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('type', ['utm_campaign', 'promo_text'])->default('utm_campaign');
            $table->enum('role', ['all', 'influencer', 'blogger'])->default('all');
            $table->text('content'); // utm_campaign value OR text content
            $table->string('language', 5)->default('fr');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'role', 'language']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_templates');
    }
};
