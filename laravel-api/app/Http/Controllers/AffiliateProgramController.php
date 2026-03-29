<?php

namespace App\Http\Controllers;

use App\Models\AffiliateProgram;
use App\Models\AffiliateEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliateProgramController extends Controller
{
    // ── GET /affiliates ──────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = AffiliateProgram::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $programs = $query->orderBy('sort_order')->orderBy('name')->get();

        // Stats globales
        $stats = [
            'total'         => AffiliateProgram::count(),
            'active'        => AffiliateProgram::where('status', 'active')->count(),
            'not_applied'   => AffiliateProgram::where('status', 'not_applied')->count(),
            'needs_payout'  => AffiliateProgram::whereNotNull('payout_threshold')
                ->whereRaw('current_balance >= payout_threshold')
                ->count(),
            'total_balance' => AffiliateProgram::sum('current_balance'),
            'total_earned'  => AffiliateProgram::sum('total_earned'),
        ];

        return response()->json([
            'data'  => $programs,
            'stats' => $stats,
        ]);
    }

    // ── GET /affiliates/:id ──────────────────────────────────────────────────
    public function show(AffiliateProgram $affiliateProgram): JsonResponse
    {
        $affiliateProgram->load('earnings');

        // Earnings par mois (12 derniers mois)
        $monthlyEarnings = AffiliateEarning::where('affiliate_program_id', $affiliateProgram->id)
            ->where('type', 'commission')
            ->where('earned_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_TRUNC('month', earned_at) as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'data'            => $affiliateProgram,
            'monthly_earnings'=> $monthlyEarnings,
        ]);
    }

    // ── POST /affiliates ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'category'                => ['required', Rule::in(['insurance','finance','travel','vpn','housing','employment','education','shopping','telecom','community','legal','other'])],
            'description'             => 'nullable|string',
            'website_url'             => 'required|url',
            'affiliate_dashboard_url' => 'nullable|url',
            'affiliate_signup_url'    => 'nullable|url',
            'my_affiliate_link'       => 'nullable|string',
            'commission_type'         => ['required', Rule::in(['percentage','fixed_per_lead','fixed_per_sale','recurring','hybrid','cpc','unknown'])],
            'commission_info'         => 'nullable|string|max:255',
            'cookie_duration_days'    => 'nullable|integer|min:0',
            'payout_threshold'        => 'nullable|numeric|min:0',
            'payout_method'           => 'nullable|string|max:100',
            'payout_frequency'        => 'nullable|string|max:100',
            'status'                  => ['required', Rule::in(['active','pending_approval','applied','inactive','not_applied'])],
            'network'                 => 'nullable|string|max:255',
            'logo_url'                => 'nullable|url',
            'notes'                   => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $program = AffiliateProgram::create($validated);

        return response()->json(['data' => $program], 201);
    }

    // ── PUT /affiliates/:id ──────────────────────────────────────────────────
    public function update(Request $request, AffiliateProgram $affiliateProgram): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => 'sometimes|string|max:255',
            'category'                => ['sometimes', Rule::in(['insurance','finance','travel','vpn','housing','employment','education','shopping','telecom','community','legal','other'])],
            'description'             => 'nullable|string',
            'website_url'             => 'sometimes|url',
            'affiliate_dashboard_url' => 'nullable|url',
            'affiliate_signup_url'    => 'nullable|url',
            'my_affiliate_link'       => 'nullable|string',
            'commission_type'         => ['sometimes', Rule::in(['percentage','fixed_per_lead','fixed_per_sale','recurring','hybrid','cpc','unknown'])],
            'commission_info'         => 'nullable|string|max:255',
            'cookie_duration_days'    => 'nullable|integer|min:0',
            'payout_threshold'        => 'nullable|numeric|min:0',
            'payout_method'           => 'nullable|string|max:100',
            'payout_frequency'        => 'nullable|string|max:100',
            'current_balance'         => 'nullable|numeric|min:0',
            'total_earned'            => 'nullable|numeric|min:0',
            'last_payout_amount'      => 'nullable|numeric|min:0',
            'last_payout_at'          => 'nullable|date',
            'status'                  => ['sometimes', Rule::in(['active','pending_approval','applied','inactive','not_applied'])],
            'network'                 => 'nullable|string|max:255',
            'logo_url'                => 'nullable|url',
            'notes'                   => 'nullable|string',
            'sort_order'              => 'nullable|integer',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $affiliateProgram->update($validated);

        return response()->json(['data' => $affiliateProgram->fresh()]);
    }

    // ── DELETE /affiliates/:id ───────────────────────────────────────────────
    public function destroy(AffiliateProgram $affiliateProgram): JsonResponse
    {
        $affiliateProgram->delete();
        return response()->json(['message' => 'Programme supprimé']);
    }

    // ── POST /affiliates/:id/earnings ────────────────────────────────────────
    public function addEarning(Request $request, AffiliateProgram $affiliateProgram): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0',
            'currency'    => 'nullable|string|size:3',
            'type'        => ['required', Rule::in(['commission','payout','adjustment'])],
            'description' => 'nullable|string|max:500',
            'reference'   => 'nullable|string|max:255',
            'earned_at'   => 'required|date',
        ]);

        $earning = $affiliateProgram->earnings()->create($validated);

        // Mettre à jour le solde et le total
        if ($validated['type'] === 'commission' || $validated['type'] === 'adjustment') {
            $affiliateProgram->increment('current_balance', $validated['amount']);
            $affiliateProgram->increment('total_earned', $validated['amount']);
        } elseif ($validated['type'] === 'payout') {
            // Un payout réduit le solde courant
            $affiliateProgram->decrement('current_balance', $validated['amount']);
            $affiliateProgram->update([
                'last_payout_amount' => $validated['amount'],
                'last_payout_at'     => $validated['earned_at'],
            ]);
        }

        return response()->json(['data' => $earning], 201);
    }

    // ── GET /affiliates/:id/earnings ─────────────────────────────────────────
    public function getEarnings(AffiliateProgram $affiliateProgram): JsonResponse
    {
        $earnings = $affiliateProgram->earnings()
            ->orderByDesc('earned_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $earnings]);
    }

    // ── GET /affiliates/stats ────────────────────────────────────────────────
    public function globalStats(): JsonResponse
    {
        $byCategory = AffiliateProgram::selectRaw('category, COUNT(*) as total, SUM(current_balance) as balance, SUM(total_earned) as earned')
            ->groupBy('category')
            ->get();

        $byStatus = AffiliateProgram::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        // Earnings des 12 derniers mois
        $monthlyTotal = AffiliateEarning::where('type', 'commission')
            ->where('earned_at', '>=', now()->subMonths(12))
            ->selectRaw("TO_CHAR(earned_at, 'YYYY-MM') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'by_category'   => $byCategory,
            'by_status'     => $byStatus,
            'monthly_total' => $monthlyTotal,
        ]);
    }
}
