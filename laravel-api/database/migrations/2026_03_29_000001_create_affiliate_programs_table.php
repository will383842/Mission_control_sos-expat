<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('category', [
                'insurance', 'finance', 'travel', 'vpn',
                'housing', 'employment', 'education', 'shopping',
                'telecom', 'community', 'legal', 'other'
            ])->default('other');
            $table->text('description')->nullable();
            $table->string('website_url');
            $table->string('affiliate_dashboard_url')->nullable();  // URL pour voir ses stats/gains
            $table->string('affiliate_signup_url')->nullable();     // URL pour s'inscrire au programme
            $table->string('my_affiliate_link')->nullable();        // Mon lien affilié personnel
            $table->enum('commission_type', [
                'percentage', 'fixed_per_lead', 'fixed_per_sale',
                'recurring', 'hybrid', 'cpc', 'unknown'
            ])->default('unknown');
            $table->string('commission_info')->nullable();          // Ex: "40% + 30% récurrent"
            $table->integer('cookie_duration_days')->nullable();
            $table->decimal('payout_threshold', 10, 2)->nullable(); // Seuil minimum retrait
            $table->string('payout_method')->nullable();            // paypal, bank, check
            $table->string('payout_frequency')->nullable();         // monthly, weekly, on_demand
            $table->decimal('current_balance', 10, 2)->default(0); // Solde actuel (mis à jour manuellement)
            $table->decimal('total_earned', 10, 2)->default(0);    // Total gagné depuis le début
            $table->decimal('last_payout_amount', 10, 2)->nullable();
            $table->date('last_payout_at')->nullable();
            $table->enum('status', [
                'active', 'pending_approval', 'applied', 'inactive', 'not_applied'
            ])->default('not_applied');
            $table->string('network')->nullable();                  // CJ, ShareASale, Impact, direct...
            $table->string('logo_url')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });

        Schema::create('affiliate_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_program_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('type', ['commission', 'payout', 'adjustment'])->default('commission');
            $table->string('description')->nullable();
            $table->string('reference')->nullable();    // Référence externe (ID transaction)
            $table->date('earned_at');
            $table->timestamps();

            $table->index(['affiliate_program_id', 'earned_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_earnings');
        Schema::dropIfExists('affiliate_programs');
    }
};
