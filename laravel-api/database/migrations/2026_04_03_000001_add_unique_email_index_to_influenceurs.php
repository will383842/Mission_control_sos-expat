<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a partial unique index on LOWER(email) in influenceurs.
 *
 * Partial: only applies when email IS NOT NULL, NOT empty, AND deleted_at IS NULL.
 * Soft-deleted records are excluded so they don't block re-creation.
 *
 * Run AFTER cleaning existing duplicates (use the dedup tool first).
 */
return new class extends Migration
{
    public function up(): void
    {
        // First: auto-soft-delete duplicate emails keeping the oldest record
        DB::statement("
            UPDATE influenceurs SET deleted_at = NOW()
            WHERE id IN (
                SELECT id FROM (
                    SELECT id,
                        ROW_NUMBER() OVER (
                            PARTITION BY LOWER(email)
                            ORDER BY created_at ASC
                        ) AS rn
                    FROM influenceurs
                    WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                ) ranked
                WHERE rn > 1
            )
        ");

        // Then: create partial unique index
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS influenceurs_email_unique
            ON influenceurs (LOWER(email))
            WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS influenceurs_email_unique");
    }
};
