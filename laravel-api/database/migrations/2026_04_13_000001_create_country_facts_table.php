<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_facts', function (Blueprint $table) {
            $table->string('country_code', 2)->primary();
            $table->string('country_name_fr');
            $table->string('country_name_en');

            // Visa & Immigration
            $table->jsonb('visa_types')->nullable()->comment('Array of {type, cost_eur, cost_usd, duration_days, processing_days}');
            $table->string('immigration_office_url')->nullable();

            // Cost of Living
            $table->decimal('cost_of_living_index', 6, 2)->nullable()->comment('Normalized 0-100 vs NYC=100');
            $table->integer('avg_rent_1bed_center_usd')->nullable()->comment('Monthly rent 1-bed city center');
            $table->integer('avg_rent_1bed_outside_usd')->nullable()->comment('Monthly rent 1-bed outside center');
            $table->decimal('coffee_usd', 6, 2)->nullable()->comment('Cappuccino price USD');
            $table->decimal('meal_restaurant_usd', 6, 2)->nullable()->comment('Meal inexpensive restaurant USD');

            // Salary & Employment
            $table->integer('min_wage_monthly_usd')->nullable();
            $table->integer('avg_salary_monthly_usd')->nullable();
            $table->decimal('unemployment_rate', 5, 2)->nullable();

            // Safety & Health
            $table->decimal('safety_index', 5, 2)->nullable()->comment('0-100, higher=safer');
            $table->string('healthcare_type', 20)->nullable()->comment('public, private, mixed, universal');
            $table->decimal('health_expenditure_pct_gdp', 5, 2)->nullable();
            $table->string('emergency_number', 20)->nullable();

            // Economy
            $table->decimal('gdp_per_capita_usd', 12, 2)->nullable();
            $table->decimal('inflation_rate', 6, 2)->nullable();
            $table->decimal('ppp_factor', 8, 4)->nullable()->comment('PPP conversion factor vs USD');

            // Tax
            $table->decimal('tax_rate_non_resident', 5, 2)->nullable();
            $table->decimal('corporate_tax_rate', 5, 2)->nullable();
            $table->boolean('has_tax_treaty_france')->default(false);

            // Digital & Infrastructure
            $table->decimal('internet_speed_mbps', 8, 2)->nullable();
            $table->boolean('has_digital_nomad_visa')->default(false);
            $table->integer('nomad_visa_cost_usd')->nullable();

            // Demographics
            $table->jsonb('official_languages')->nullable()->comment('Array of language codes');
            $table->string('currency_code', 3)->nullable();
            $table->string('currency_name', 50)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->integer('expat_population')->nullable()->comment('International migrant stock');
            $table->decimal('expat_pct_population', 5, 2)->nullable();
            $table->bigInteger('total_population')->nullable();

            // Tourism
            $table->bigInteger('tourism_arrivals')->nullable();
            $table->decimal('tourism_receipts_usd', 14, 2)->nullable();

            // Traceability
            $table->jsonb('source_urls')->nullable()->comment('{"gdp": "https://...", "safety": "https://..."}');
            $table->jsonb('data_years')->nullable()->comment('{"gdp": 2024, "safety": 2023} — year of each data point');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_status', 20)->default('unverified')->comment('verified, unverified, outdated');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_facts');
    }
};
