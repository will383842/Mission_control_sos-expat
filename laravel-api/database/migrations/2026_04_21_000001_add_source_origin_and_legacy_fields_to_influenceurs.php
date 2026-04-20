<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 refactor fusion tables contacts — Partie 1/2.
 *
 * Enrichit `influenceurs` avec les colonnes nécessaires pour absorber
 * les 4 tables legacy (lawyers, press_contacts, content_businesses, content_contacts) :
 *
 * - Traçabilité : source_origin, source_id_legacy
 * - Lawyer-specific : firm_name, bar_number, bar_association, specialty
 * - PressContact-specific : publication, role, beat, media_type
 * - ContentBusiness-specific : url_hash (UNIQUE nullable)
 *
 * SAFE :
 * - ADD COLUMN nullable = quasi instantané sur Postgres (pas de REWRITE)
 * - Idempotent (hasColumn check)
 * - Aucune modification des 4 tables legacy (non-destructive)
 * - Rollback via down() supprime uniquement les colonnes ajoutées
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('influenceurs')) {
            return;
        }

        Schema::table('influenceurs', function (Blueprint $t) {
            // Traçabilité fusion
            if (!Schema::hasColumn('influenceurs', 'source_origin')) {
                $t->string('source_origin', 32)->nullable();
                $t->index('source_origin', 'influenceurs_source_origin_index');
            }
            if (!Schema::hasColumn('influenceurs', 'source_id_legacy')) {
                $t->unsignedBigInteger('source_id_legacy')->nullable();
                $t->index(['source_origin', 'source_id_legacy'], 'influenceurs_source_composite_index');
            }

            // Lawyer-specific
            foreach (['firm_name', 'bar_number', 'bar_association', 'specialty'] as $col) {
                if (!Schema::hasColumn('influenceurs', $col)) {
                    $t->string($col, 255)->nullable();
                }
            }

            // PressContact-specific
            foreach (['publication', 'role', 'beat', 'media_type'] as $col) {
                if (!Schema::hasColumn('influenceurs', $col)) {
                    $t->string($col, 255)->nullable();
                }
            }
        });

        // url_hash + UNIQUE séparément (pour isoler en cas d'erreur sur cette partie)
        // Postgres : UNIQUE + nullable accepte plusieurs NULL (comportement standard)
        if (!Schema::hasColumn('influenceurs', 'url_hash')) {
            Schema::table('influenceurs', function (Blueprint $t) {
                $t->string('url_hash', 64)->nullable();
                $t->unique('url_hash', 'influenceurs_url_hash_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('influenceurs')) {
            return;
        }

        // Drop index avant colonnes (ordre important sur certains drivers)
        Schema::table('influenceurs', function (Blueprint $t) {
            foreach ([
                'influenceurs_source_origin_index',
                'influenceurs_source_composite_index',
            ] as $idx) {
                try { $t->dropIndex($idx); } catch (\Throwable $e) { /* idempotent */ }
            }
            try { $t->dropUnique('influenceurs_url_hash_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('influenceurs', function (Blueprint $t) {
            foreach ([
                'source_origin', 'source_id_legacy',
                'firm_name', 'bar_number', 'bar_association', 'specialty',
                'publication', 'role', 'beat', 'media_type',
                'url_hash',
            ] as $col) {
                if (Schema::hasColumn('influenceurs', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
