<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_id')->constrained('influenceurs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('date');
            $table->enum('channel', ['email', 'instagram', 'linkedin', 'whatsapp', 'phone', 'other']);
            $table->enum('result', ['sent', 'replied', 'refused', 'registered', 'no_answer']);
            $table->string('sender')->nullable();
            $table->text('message')->nullable();
            $table->text('reply')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
