<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Domains\Sales\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;

class SaleController extends Controller
{
    private SaleService $saleService;

    public function __construct()
    {
        $this->saleService = new SaleService();
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 20), 100);

        // Sélection optimisée des colonnes et eager loading sélectif
        $sales = Sale::where('tenant_id', $tenant_id)
            ->select(['id', 'reference', 'customer_name', 'total', 'status', 'payment_method', 'user_id', 'created_at', 'completed_at'])
            ->with([
                'items:id,sale_id,product_id,quantity,unit_price,line_total',
                'items.product:id,name,code',
                'user:id,name'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($sales);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();

        // Vérifier que l'utilisateur peut créer des ventes
        // Seuls les serveurs et caissiers peuvent créer des ventes
        if (!$user->canCreateSales()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à créer des ventes. Seuls les serveurs et caissiers peuvent effectuer des ventes.',
                'error' => 'ROLE_NOT_ALLOWED',
                'user_role' => $user->role
            ], 403);
        }

        $validated = $request->validate([
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'mode' => 'required|in:manual,facturette',
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric',
        ]);

        try {
            $sale = $this->saleService->createSale($validated);

            foreach ($validated['items'] as $item) {
                $this->saleService->addItem(
                    $sale,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'] ?? null
                );
            }

            \Log::info("Vente créée", [
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'role' => $user->role,
                'total' => $sale->total
            ]);

            return response()->json($sale->load('items.product'), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Sale $sale): JsonResponse
    {
        if ($sale->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($sale->load('items.product', 'user'));
    }

    public function complete(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        try {
            $completedSale = $this->saleService->completeSale($sale, $validated['amount_paid'], $validated['payment_method']);
            return response()->json($completedSale);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Sale $sale): JsonResponse
    {
        if ($sale->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cancelledSale = $this->saleService->cancelSale($sale);

        return response()->json($cancelledSale);
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'group_by' => 'nullable|in:daily,weekly,monthly',
        ]);

        $report = $this->saleService->getSalesReport(
            $validated['start_date'],
            $validated['end_date'],
            $validated['group_by'] ?? 'daily'
        );

        return response()->json($report);
    }
}
