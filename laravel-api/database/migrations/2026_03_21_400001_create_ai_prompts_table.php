<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type', 50);
            $table->text('prompt_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('contact_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
