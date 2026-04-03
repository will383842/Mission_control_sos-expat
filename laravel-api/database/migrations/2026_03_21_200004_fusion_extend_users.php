<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert role from enum to varchar (defensive)
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'member'");

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'territories')) {
                $col = $table->json('territories')->nullable();
                if (Schema::hasColumn('users', 'contact_types')) {
                    $col->after('contact_types');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'territories')) {
                $table->dropColumn('territories');
            }
        });
    }
};
