<?php

namespace App\Http\Controllers;

use App\Models\PromoTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PromoTemplate::query()->orderBy('sort_order')->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }
        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:utm_campaign,promo_text',
            'role'       => 'required|in:all,influencer,blogger',
            'content'    => 'required|string|max:5000',
            'language'   => 'required|string|max:5',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);

        $template = PromoTemplate::create($data);

        return response()->json($template, 201);
    }

    public function show(PromoTemplate $promoTemplate): JsonResponse
    {
        return response()->json($promoTemplate);
    }

    public function update(Request $request, PromoTemplate $promoTemplate): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'type'       => 'sometimes|required|in:utm_campaign,promo_text',
            'role'       => 'sometimes|required|in:all,influencer,blogger',
            'content'    => 'sometimes|required|string|max:5000',
            'language'   => 'sometimes|required|string|max:5',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);

        $promoTemplate->update($data);

        return response()->json($promoTemplate);
    }

    public function destroy(PromoTemplate $promoTemplate): JsonResponse
    {
        $promoTemplate->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:promo_templates,id',
        ]);

        foreach ($request->order as $position => $id) {
            PromoTemplate::where('id', $id)->update(['sort_order' => $position]);
        }

        return response()->json(['message' => 'Ordre mis à jour']);
    }
}
