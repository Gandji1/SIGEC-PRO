<?php

namespace App\Http\Controllers\Api;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Domains\Purchases\Services\PurchaseService;
use App\Http\Resources\PurchaseResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    private PurchaseService $purchaseService;

    public function __construct()
    {
        $this->purchaseService = new PurchaseService();
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    /**
     * Lister les bons d'achat
     */
    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $status = $request->query('status');
        $supplier_id = $request->query('supplier_id');
        $perPage = $request->query('per_page', 20);

        $query = Purchase::where('tenant_id', $tenant_id);

        if ($status) {
            $query->where('status', $status);
        }
        if ($supplier_id) {
            $query->where('supplier_id', $supplier_id);
        }

        $purchases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => PurchaseResource::collection($purchases),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
            ],
        ]);
    }

    /**
     * Créer un bon d'achat
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'supplier_phone' => 'nullable|string',
            'supplier_email' => 'nullable|email',
            'payment_method' => 'required|in:cash,transfer,check',
            'expected_date' => 'nullable|date',
            'items' => 'nullable|array',
        ]);

        $purchase = $this->purchaseService->createPurchase($validated);

        if (isset($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $this->purchaseService->addItem(
                    $purchase->id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'] ?? null
                );
            }
        }

        return response()->json(new PurchaseResource($purchase->fresh()), 201);
    }

    /**
     * Afficher un bon d'achat
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        return response()->json(new PurchaseResource($purchase->load('items', 'supplier')));
    }

    /**
     * Ajouter un article au bon d'achat
     */
    public function addItem(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $item = $this->purchaseService->addItem(
            $purchase->id,
            $validated['product_id'],
            $validated['quantity'],
            $validated['unit_price'] ?? null
        );

        return response()->json(['item' => $item], 201);
    }

    /**
     * Confirmer un bon d'achat
     */
    public function confirm(Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== 'pending') {
            return response()->json(['error' => 'Purchase must be in pending status'], 400);
        }

        $purchase = $this->purchaseService->confirmPurchase($purchase->id);

        return response()->json(new PurchaseResource($purchase));
    }

    /**
     * Réceptionner un bon d'achat (update stock + CMP)
     */
    public function receive(Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== 'confirmed') {
            return response()->json([
                'error' => 'Purchase must be in confirmed status to receive',
            ], 400);
        }

        try {
            $purchase = $this->purchaseService->receivePurchase($purchase->id);

            return response()->json([
                'message' => 'Purchase received successfully',
                'purchase' => new PurchaseResource($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Annuler un bon d'achat
     */
    public function cancel(Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        if (!in_array($purchase->status, ['pending', 'confirmed'])) {
            return response()->json([
                'error' => "Cannot cancel purchase in {$purchase->status} status",
            ], 400);
        }

        $purchase = $this->purchaseService->cancelPurchase($purchase->id);

        return response()->json(new PurchaseResource($purchase));
    }

    /**
     * Rapport d'achats
     */
    public function report(Request $request): JsonResponse
    {
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        $report = $this->purchaseService->getPurchasesReport($start_date, $end_date);

        return response()->json([
            'period' => [
                'start' => $start_date,
                'end' => $end_date,
            ],
            'report' => $report,
        ]);
    }
}
