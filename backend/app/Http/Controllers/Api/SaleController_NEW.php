<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Domains\Sales\Services\SaleService;
use App\Http\Resources\SaleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    private SaleService $saleService;

    public function __construct()
    {
        $this->saleService = new SaleService();
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    /**
     * Lister les ventes
     */
    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $status = $request->query('status');
        $perPage = $request->query('per_page', 20);

        $query = Sale::where('tenant_id', $tenant_id);

        if ($status) {
            $query->where('status', $status);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => SaleResource::collection($sales),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
            ],
        ]);
    }

    /**
     * Créer une vente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'mode' => 'nullable|in:manual,pos',
            'payment_method' => 'nullable|in:cash,card,mobile_money,transfer',
            'items' => 'nullable|array',
        ]);

        $sale = $this->saleService->createSale($validated);

        if (isset($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $this->saleService->addItem(
                    $sale,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'] ?? null
                );
            }
        }

        return response()->json(new SaleResource($sale->fresh()), 201);
    }

    /**
     * Afficher une vente
     */
    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return response()->json(new SaleResource($sale->load('items')));
    }

    /**
     * Ajouter un article à la vente
     */
    public function addItem(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $item = $this->saleService->addItem(
                $sale,
                $validated['product_id'],
                $validated['quantity'],
                $validated['unit_price'] ?? null
            );

            return response()->json(['item' => $item], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Valider une vente (deductio stock + payment recording)
     */
    public function complete(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,mobile_money,transfer',
        ]);

        try {
            $sale = $this->saleService->completeSale(
                $sale,
                $validated['amount_paid'],
                $validated['payment_method']
            );

            return response()->json([
                'message' => 'Sale completed successfully',
                'sale' => new SaleResource($sale),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Annuler une vente
     */
    public function cancel(Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        try {
            $sale = $this->saleService->cancelSale($sale);

            return response()->json(new SaleResource($sale));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Rapport des ventes
     */
    public function report(Request $request): JsonResponse
    {
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());
        $group_by = $request->query('group_by', 'daily');

        $report = $this->saleService->getSalesReport($start_date, $end_date, $group_by);

        return response()->json([
            'period' => [
                'start' => $start_date,
                'end' => $end_date,
                'grouped_by' => $group_by,
            ],
            'report' => $report,
        ]);
    }
}
