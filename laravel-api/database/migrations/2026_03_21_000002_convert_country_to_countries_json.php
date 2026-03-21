<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add new columns
        Schema::table('objectives', function (Blueprint $table) {
            $table->string('continent', 50)->nullable()->after('niche');
            $table->jsonb('countries')->nullable()->after('continent');
        });

        // Step 2: Migrate existing country data → countries JSON array
        $objectives = DB::table('objectives')->whereNotNull('country')->get();
        foreach ($objectives as $objective) {
            DB::table('objectives')
                ->where('id', $objective->id)
                ->update([
                    'countries' => json_encode([$objective->country]),
                ]);
        }

        // Step 3: Drop old country column
        Schema::table('objectives', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    public function down(): void
    {
        Schema::table('objectives', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('niche');
        });

        // Migrate back: take first country from JSON array
        $objectives = DB::table('objectives')->whereNotNull('countries')->get();
        foreach ($objectives as $objective) {
            $countries = json_decode($objective->countries, true);
            if (!empty($countries)) {
                DB::table('objectives')
                    ->where('id', $objective->id)
                    ->update(['country' => $countries[0]]);
            }
        }

        Schema::table('objectives', function (Blueprint $table) {
            $table->dropColumn(['continent', 'countries']);
        });
    }
};
