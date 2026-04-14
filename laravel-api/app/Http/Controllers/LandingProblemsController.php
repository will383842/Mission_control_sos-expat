<?php

namespace App\Http\Controllers;

use App\Models\LandingProblem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingProblemsController extends Controller
{
    /**
     * GET /api/landing-problems
     * Params: category, business_value, min_urgency, product_route, search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = LandingProblem::active();

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('business_value')) {
            $query->where('business_value', $request->business_value);
        }

        if ($request->filled('min_urgency')) {
            $query->minUrgency((int) $request->min_urgency);
        }

        if ($request->filled('product_route')) {
            $query->where('product_route', $request->product_route);
        }

        if ($request->boolean('needs_lawyer')) {
            $query->forLawyers();
        }

        if ($request->boolean('needs_helper')) {
            $query->forHelpers();
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', $search)
                  ->orWhere('category', 'ILIKE', $search)
                  ->orWhere('slug', 'ILIKE', $search);
            });
        }

        $query->ordered();

        $perPage = (int) $request->input('per_page', 50);
        $data    = $query->paginate(min($perPage, 200));

        return response()->json($data);
    }

    /**
     * GET /api/landing-problems/categories
     * Retourne les catégories distinctes avec leur nombre de problèmes
     */
    public function categories(): JsonResponse
    {
        $categories = LandingProblem::active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['value' => $r->category, 'label' => ucfirst($r->category), 'count' => $r->count]);

        return response()->json($categories);
    }
}
