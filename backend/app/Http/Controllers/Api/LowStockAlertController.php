<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LowStockAlert;
use App\Services\StockAlertService;
use Illuminate\Http\Request;

class LowStockAlertController extends Controller
{
    protected $alertService;

    public function __construct(StockAlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    public function index()
    {
        $tenantId = request()->header('X-Tenant-ID');
        return LowStockAlert::where('tenant_id', $tenantId)
            ->with('product', 'warehouse', 'resolvedBy')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'threshold_quantity' => 'required|numeric|min:0',
            'reorder_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $tenantId = request()->header('X-Tenant-ID');
        $product = \App\Models\Product::find($validated['product_id']);
        if ($product->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated['tenant_id'] = $tenantId;
        $validated['current_quantity'] = $product->quantity ?? 0;
        $validated['status'] = 'active';

        $alert = LowStockAlert::create($validated);
        return response()->json($alert, 201);
    }

    public function show(LowStockAlert $lowStockAlert)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($lowStockAlert->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        return $lowStockAlert->load('product', 'warehouse', 'resolvedBy');
    }

    public function update(Request $request, LowStockAlert $lowStockAlert)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($lowStockAlert->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'threshold_quantity' => 'nullable|numeric|min:0',
            'reorder_quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $lowStockAlert->update($validated);
        return response()->json($lowStockAlert);
    }

    public function destroy(LowStockAlert $lowStockAlert)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($lowStockAlert->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $lowStockAlert->delete();
        return response()->json(null, 204);
    }

    public function resolve(Request $request, LowStockAlert $lowStockAlert)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($lowStockAlert->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['notes' => 'nullable|string']);
        $lowStockAlert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'notes' => $validated['notes'] ?? $lowStockAlert->notes,
        ]);
        return response()->json($lowStockAlert);
    }

    public function ignore(Request $request, LowStockAlert $lowStockAlert)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($lowStockAlert->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['notes' => 'nullable|string']);
        $lowStockAlert->update([
            'status' => 'ignored',
            'notes' => $validated['notes'] ?? $lowStockAlert->notes,
        ]);
        return response()->json($lowStockAlert);
    }

    public function summary()
    {
        $tenantId = request()->header('X-Tenant-ID');
        return response()->json($this->alertService->getSummary($tenantId));
    }

    public function checkAlerts()
    {
        $tenantId = request()->header('X-Tenant-ID');
        $alertsCreated = $this->alertService->checkAndCreateAlerts($tenantId);
        return response()->json([
            'message' => 'Alerts checked',
            'alerts_created' => $alertsCreated,
            'summary' => $this->alertService->getSummary($tenantId),
        ]);
    }
}
