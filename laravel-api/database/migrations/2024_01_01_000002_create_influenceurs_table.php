<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influenceurs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->json('platforms');
            $table->string('primary_platform', 50);
            $table->unsignedBigInteger('followers')->nullable();
            $table->json('followers_secondary')->nullable();
            $table->string('niche')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('profile_url', 500)->nullable();
            $table->enum('status', ['prospect', 'contacted', 'negotiating', 'active', 'refused', 'inactive'])
                ->default('prospect');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reminder_days')->default(7);
            $table->boolean('reminder_active')->default(true);
            $table->timestamp('last_contact_at')->nullable();
            $table->date('partnership_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influenceurs');
    }
};
