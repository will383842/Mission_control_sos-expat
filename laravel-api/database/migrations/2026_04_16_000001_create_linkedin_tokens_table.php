<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE linkedin_tokens (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_type  ENUM('personal','page') NOT NULL,
            access_token  TEXT NOT NULL COMMENT 'Encrypted OAuth access token',
            refresh_token TEXT NULL     COMMENT 'Encrypted refresh token (365d TTL)',
            expires_at    TIMESTAMP NOT NULL,
            linkedin_id   VARCHAR(100) NOT NULL COMMENT 'person URN or org numeric ID',
            linkedin_name VARCHAR(255) NULL,
            scope         TEXT NULL,
            created_at    TIMESTAMP NULL,
            updated_at    TIMESTAMP NULL,
            UNIQUE KEY uq_account_type (account_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS linkedin_tokens");
    }
};
