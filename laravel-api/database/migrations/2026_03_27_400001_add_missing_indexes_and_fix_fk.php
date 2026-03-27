<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // generated_articles: index composite pour listing par status + date
        if (Schema::hasTable('generated_articles')) {
            Schema::table('generated_articles', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_gen_articles_status_created');
                $table->index(['language', 'country'], 'idx_gen_articles_lang_country');
            });
        }

        // outreach_emails: changer cascadeOnDelete -> restrictOnDelete
        if (Schema::hasTable('outreach_emails')) {
            Schema::table('outreach_emails', function (Blueprint $table) {
                // Drop l'ancienne FK et recréer avec RESTRICT
                $table->dropForeign(['influenceur_id']);
                $table->foreign('influenceur_id')
                    ->references('id')
                    ->on('influenceurs')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('generated_articles')) {
            Schema::table('generated_articles', function (Blueprint $table) {
                $table->dropIndex('idx_gen_articles_status_created');
                $table->dropIndex('idx_gen_articles_lang_country');
            });
        }

        if (Schema::hasTable('outreach_emails')) {
            Schema::table('outreach_emails', function (Blueprint $table) {
                $table->dropForeign(['influenceur_id']);
                $table->foreign('influenceur_id')
                    ->references('id')
                    ->on('influenceurs')
                    ->cascadeOnDelete();
            });
        }
    }
};
