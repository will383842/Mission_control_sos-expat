<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE activity_logs ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activity_logs ALTER COLUMN user_id SET NOT NULL');
    }
};
