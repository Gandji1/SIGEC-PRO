<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductReturn;
use App\Models\ProductReturnItem;
use App\Models\Sale;
use App\Models\PosOrder;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $perPage = min($request->query('per_page', 20), 50);

        $query = ProductReturn::where('tenant_id', $tenantId)
            ->with(['items.product:id,name,code', 'customer:id,name', 'createdByUser:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }

        $returns = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($returns);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'sale_id' => 'nullable|exists:sales,id',
            'pos_order_id' => 'nullable|exists:pos_orders,id',
            'customer_id' => 'nullable|exists:customers,id',
            'return_type' => 'required|in:refund,exchange,credit',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,defective',
            'items.*.restock' => 'boolean',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $return = ProductReturn::create([
                'tenant_id' => $user->tenant_id,
                'reference' => ProductReturn::generateReference($user->tenant_id),
                'sale_id' => $validated['sale_id'] ?? null,
                'pos_order_id' => $validated['pos_order_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'return_type' => $validated['return_type'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                ProductReturnItem::create([
                    'product_return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                    'restock' => $item['restock'] ?? true,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $return->calculateTotals();

            // Notifier les gérants
            Notification::notifyManagers(
                $user->tenant_id,
                Notification::TYPE_STOCK_REQUEST,
                'Nouveau retour produit',
                "Retour {$return->reference} créé - Montant: " . number_format($return->total_amount, 0, ',', ' ') . " XAF",
                ['return_id' => $return->id, 'reference' => $return->reference],
                'high'
            );

            DB::commit();

            return response()->json($return->load('items.product'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function show(ProductReturn $productReturn): JsonResponse
    {
        if ($productReturn->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($productReturn->load([
            'items.product',
            'customer',
            'sale',
            'createdByUser:id,name',
            'approvedByUser:id,name',
        ]));
    }

    public function approve(ProductReturn $productReturn): JsonResponse
    {
        $user = auth()->user();

        if ($productReturn->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json(['message' => 'Seul le gérant peut approuver les retours'], 403);
        }

        if ($productReturn->status !== 'pending') {
            return response()->json(['message' => 'Ce retour a déjà été traité'], 422);
        }

        $productReturn->approve($user->id);

        return response()->json([
            'message' => 'Retour approuvé',
            'data' => $productReturn->fresh(),
        ]);
    }

    public function reject(Request $request, ProductReturn $productReturn): JsonResponse
    {
        $user = auth()->user();

        if ($productReturn->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json(['message' => 'Seul le gérant peut rejeter les retours'], 403);
        }

        if ($productReturn->status !== 'pending') {
            return response()->json(['message' => 'Ce retour a déjà été traité'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $productReturn->update([
            'status' => 'rejected',
            'notes' => ($productReturn->notes ? $productReturn->notes . "\n" : '') . 
                       "Rejeté: " . ($validated['reason'] ?? 'Aucune raison spécifiée'),
        ]);

        return response()->json([
            'message' => 'Retour rejeté',
            'data' => $productReturn->fresh(),
        ]);
    }

    public function process(ProductReturn $productReturn): JsonResponse
    {
        $user = auth()->user();

        if ($productReturn->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json(['message' => 'Seul le gérant peut traiter les retours'], 403);
        }

        try {
            $productReturn->process();

            return response()->json([
                'message' => 'Retour traité - Stock mis à jour',
                'data' => $productReturn->fresh('items.product'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refund(ProductReturn $productReturn): JsonResponse
    {
        $user = auth()->user();

        if ($productReturn->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($productReturn->status !== 'processed') {
            return response()->json(['message' => 'Le retour doit être traité avant remboursement'], 422);
        }

        // Créer un mouvement de caisse négatif pour le remboursement
        \App\Models\CashMovement::create([
            'tenant_id' => $user->tenant_id,
            'type' => 'out',
            'category' => 'refund',
            'amount' => $productReturn->refund_amount,
            'reference' => $productReturn->reference,
            'description' => 'Remboursement retour: ' . $productReturn->reference,
            'user_id' => $user->id,
        ]);

        $productReturn->update(['status' => 'refunded']);

        return response()->json([
            'message' => 'Remboursement effectué',
            'data' => $productReturn->fresh(),
        ]);
    }

    public function statistics(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $stats = [
            'pending' => ProductReturn::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'approved' => ProductReturn::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
            'processed' => ProductReturn::where('tenant_id', $tenantId)->where('status', 'processed')->count(),
            'total_refunded' => ProductReturn::where('tenant_id', $tenantId)
                ->where('status', 'refunded')
                ->sum('refund_amount'),
            'this_month' => ProductReturn::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        return response()->json($stats);
    }
}
