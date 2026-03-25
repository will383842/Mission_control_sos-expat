<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Email verification results
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_id')->constrained()->onDelete('cascade');
            $table->string('email')->index();
            $table->boolean('mx_valid')->nullable();
            $table->string('mx_domain')->nullable();
            $table->boolean('smtp_valid')->nullable();
            $table->text('smtp_response')->nullable();
            $table->string('status', 20)->default('pending'); // pending, verified, invalid, risky, catch_all, unknown
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique('influenceur_id');
            $table->index('status');
        });

        // Duplicate detection flags
        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_a_id')->constrained('influenceurs')->onDelete('cascade');
            $table->foreignId('influenceur_b_id')->constrained('influenceurs')->onDelete('cascade');
            $table->string('match_type', 30); // same_url, same_email, same_name_country, cross_type
            $table->unsignedTinyInteger('confidence')->default(50); // 0-100
            $table->string('status', 20)->default('pending'); // pending, merged, dismissed, kept_both
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->unique(['influenceur_a_id', 'influenceur_b_id']);
        });

        // Type verification flags (misclassified contacts)
        Schema::create('type_verification_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_id')->constrained()->onDelete('cascade');
            $table->string('current_type', 50);
            $table->string('suggested_type', 50)->nullable();
            $table->string('reason'); // gov_email_on_non_gov, directory_url, name_mismatch, wrong_country
            $table->json('details')->nullable();
            $table->string('status', 20)->default('pending'); // pending, fixed, dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        // Add quality fields to influenceurs
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->string('email_verified_status', 20)->default('unverified')->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('email_verified_status');
            $table->string('phone_normalized', 30)->nullable()->after('phone');
            $table->unsignedSmallInteger('quality_score')->default(0)->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropColumn(['email_verified_status', 'email_verified_at', 'phone_normalized', 'quality_score']);
        });
        Schema::dropIfExists('type_verification_flags');
        Schema::dropIfExists('duplicate_flags');
        Schema::dropIfExists('email_verifications');
    }
};
