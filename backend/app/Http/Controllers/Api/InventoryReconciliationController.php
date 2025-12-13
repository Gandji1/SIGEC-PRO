<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCount;
use App\Domains\Inventory\Services\InventoryReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryReconciliationController extends Controller
{
    private InventoryReconciliationService $reconciliationService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Start physical inventory count
     * POST /api/inventory-counts/start
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string|in:periodic,cycle,discrepancy,damage',
        ]);

        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        $count = $service->startInventoryCount(
            $request->warehouse_id,
            $request->reason
        );

        return response()->json([
            'id' => $count->id,
            'status' => 'started',
            'warehouse_id' => $count->warehouse_id,
            'started_at' => $count->started_at,
        ], 201);
    }

    /**
     * Record physical count item
     * POST /api/inventory-counts/{count}/items
     */
    public function recordItem(InventoryCount $count, Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'physical_quantity' => 'required|integer|min:0',
        ]);

        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        try {
            $item = $service->recordCountItem(
                $count,
                $request->product_id,
                $request->physical_quantity
            );

            return response()->json([
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'expected' => $item->expected_quantity,
                'physical' => $item->physical_quantity,
                'variance' => $item->variance,
                'variance_percentage' => $item->variance_percentage,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete inventory count
     * POST /api/inventory-counts/{count}/complete
     */
    public function complete(InventoryCount $count): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        try {
            $result = $service->completeInventoryCount($count);

            return response()->json([
                'status' => 'completed',
                'count_id' => $result['count_id'],
                'total_items' => $result['total_items'],
                'items_with_variance' => $result['items_with_variance'],
                'total_variance_value' => $result['total_variance_value'],
                'gl_entries_created' => $result['gl_entries_created'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get inventory count summary
     * GET /api/inventory-counts/{count}/summary
     */
    public function summary(InventoryCount $count): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        $summary = $service->getCountSummary($count);

        return response()->json($summary);
    }

    /**
     * Get variance analysis
     * GET /api/inventory-counts/{count}/variances
     */
    public function variances(InventoryCount $count): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        $analysis = $service->getVarianceAnalysis($count);

        return response()->json([
            'count_id' => $count->id,
            'total_variance_items' => count($analysis),
            'items' => $analysis,
        ]);
    }

    /**
     * Cancel inventory count
     * POST /api/inventory-counts/{count}/cancel
     */
    public function cancel(InventoryCount $count): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        try {
            $service->cancelInventoryCount($count);

            return response()->json(['status' => 'cancelled']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Generate variance report
     * GET /api/inventory-counts/{count}/report
     */
    public function report(InventoryCount $count): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $service = new InventoryReconciliationService($tenant);

        $report = $service->generateVarianceReport($count);

        return response()->json($report);
    }
}
