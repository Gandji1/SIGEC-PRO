<?php

namespace App\Http\Controllers\Api;

use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $perPage = min($request->query('per_page', 20), 50);

        $query = Promotion::where('tenant_id', $tenantId)
            ->with('creator:id,name');

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now());
        }

        $promotions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($promotions);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('promotions', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed,buy_x_get_y',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'applicable_products' => 'nullable|array',
            'applicable_products.*' => 'integer|exists:products,id',
            'applicable_categories' => 'nullable|array',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['tenant_id'] = $user->tenant_id;
        $validated['created_by'] = $user->id;
        $validated['code'] = strtoupper($validated['code']);

        $promotion = Promotion::create($validated);

        return response()->json($promotion, 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        if ($promotion->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($promotion->load('creator:id,name'));
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed,buy_x_get_y',
            'value' => 'sometimes|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $promotion->update($validated);

        return response()->json($promotion);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        if ($promotion->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($promotion->usage_count > 0) {
            // Désactiver au lieu de supprimer si déjà utilisée
            $promotion->update(['is_active' => false]);
            return response()->json(['message' => 'Promotion désactivée (déjà utilisée)']);
        }

        $promotion->delete();

        return response()->json(['message' => 'Promotion supprimée']);
    }

    public function validate(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'product_ids' => 'nullable|array',
        ]);

        $promotion = Promotion::findByCode($tenantId, $validated['code']);

        if (!$promotion) {
            return response()->json([
                'valid' => false,
                'message' => 'Code promo invalide ou expiré',
            ], 404);
        }

        if (!$promotion->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Cette promotion n\'est plus valide',
            ], 422);
        }

        $discount = $promotion->calculateDiscount(
            $validated['subtotal'],
            $validated['product_ids'] ?? []
        );

        if ($discount <= 0) {
            return response()->json([
                'valid' => false,
                'message' => 'Cette promotion ne s\'applique pas à votre panier',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'promotion' => $promotion,
            'discount' => $discount,
            'new_total' => $validated['subtotal'] - $discount,
        ]);
    }

    public function toggleActive(Promotion $promotion): JsonResponse
    {
        if ($promotion->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $promotion->update(['is_active' => !$promotion->is_active]);

        return response()->json([
            'message' => $promotion->is_active ? 'Promotion activée' : 'Promotion désactivée',
            'is_active' => $promotion->is_active,
        ]);
    }
}
