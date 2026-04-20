<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * P1 refactor fusion tables contacts — Partie 2/2.
 *
 * Ajoute un UNIQUE INDEX sur LOWER(email) de `influenceurs` pour éviter
 * les doublons d'email (case-insensitive) lors de l'absorption des 4
 * tables legacy via la commande contacts:migrate-to-influenceurs.
 *
 * SAFETY :
 * - Postgres : CREATE INDEX CONCURRENTLY IF NOT EXISTS (pas de lock writes)
 * - Avant la création : LOG les doublons LOWER(email) existants (ne crash pas
 *   si doublons mais l'index ne sera pas créé → la commande les loggera)
 * - Autres drivers (SQLite dev) : index classique, moins strict
 *
 * En cas de doublon préexistant, la migration ne crash pas mais logge
 * un warning. L'admin devra nettoyer manuellement avant de rejouer.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('influenceurs')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // Audit préalable : compter les doublons email (LOWER) existants
        $duplicates = 0;
        try {
            $duplicates = DB::table('influenceurs')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->select(DB::raw('LOWER(email) as normalized'), DB::raw('COUNT(*) as c'))
                ->groupBy('normalized')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();
        } catch (\Throwable $e) {
            Log::warning('add_unique_email_lower: audit preflight failed', ['error' => $e->getMessage()]);
        }

        if ($duplicates > 0) {
            Log::warning("add_unique_email_lower_influenceurs: {$duplicates} doublons email (case-insensitive) detectes dans influenceurs. L'index UNIQUE ne sera PAS cree. Nettoyer puis rejouer.");
            return; // skip sans crash — admin doit nettoyer
        }

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_influenceurs_email_lower ON influenceurs (LOWER(email)) WHERE email IS NOT NULL AND email <> \'\'');
        } else {
            // SQLite/MySQL : index non partiel, case sensitive par defaut mais
            // proche du comportement voulu en dev/CI
            $existing = collect(Schema::getIndexes('influenceurs'))->pluck('name')->all();
            if (!in_array('idx_influenceurs_email_lower', $existing, true)) {
                Schema::table('influenceurs', function (Blueprint $t) {
                    $t->unique('email', 'idx_influenceurs_email_lower');
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('influenceurs')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_influenceurs_email_lower');
        } else {
            try {
                Schema::table('influenceurs', function (Blueprint $t) {
                    $t->dropUnique('idx_influenceurs_email_lower');
                });
            } catch (\Throwable $e) { /* idempotent */ }
        }
    }
};
