<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `backlink_synced_at` aux 3 tables contacts qui n'en avaient pas,
 * pour aligner sur le pattern des Observers Influenceur/PressContact.
 *
 * Idempotent : on skippe la colonne si elle existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lawyers', 'content_businesses', 'content_contacts'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'backlink_synced_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('backlink_synced_at')->nullable();
                $t->index('backlink_synced_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['lawyers', 'content_businesses', 'content_contacts'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'backlink_synced_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('backlink_synced_at');
                });
            }
        }
    }
};
