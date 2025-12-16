<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Domains\Transfers\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * GET /api/transfers - Lister les transferts
     */
    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 20), 100);
        
        $query = Transfer::where('tenant_id', $tenant_id)
            ->select(['id', 'reference', 'from_warehouse_id', 'to_warehouse_id', 'status', 'requested_by', 'created_at'])
            ->with([
                'fromWarehouse:id,name,code',
                'toWarehouse:id,name,code',
                'items:id,transfer_id,product_id,quantity',
                'items.product:id,name,code'
            ]);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->query('from_warehouse_id'));
        }

        $transfers = $query->latest()->paginate($perPage);

        return response()->json($transfers);
    }

    /**
     * POST /api/transfers - Créer une demande de transfert
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $transfer = $this->transferService->requestTransfer($validated);
            
            DB::commit();

            return response()->json(
                $transfer->load(['items', 'items.product', 'fromWarehouse', 'toWarehouse']),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/transfers/{id} - Détail transfert
     */
    public function show(Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        return response()->json(
            $transfer->load(['items', 'items.product', 'fromWarehouse', 'toWarehouse', 'requestedByUser', 'approvedByUser'])
        );
    }

    /**
     * POST /api/transfers/{id}/approve - Approuver et exécuter
     */
    public function approve(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        if ($transfer->status !== 'pending') {
            return response()->json(
                ['error' => "Cannot approve transfer in {$transfer->status} status"],
                422
            );
        }

        try {
            DB::beginTransaction();

            $transfer = $this->transferService->approveAndExecuteTransfer($transfer);

            DB::commit();

            return response()->json(
                $transfer->load(['items', 'items.product', 'fromWarehouse', 'toWarehouse']),
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/transfers/{id}/cancel - Annuler transfert
     */
    public function cancel(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        try {
            $transfer = $this->transferService->cancelTransfer($transfer);

            return response()->json(
                $transfer->load(['items', 'items.product']),
                200
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/transfers/pending - Lister transferts en attente d'approbation
     */
    public function pending(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        
        $transfers = Transfer::where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->select(['id', 'reference', 'from_warehouse_id', 'to_warehouse_id', 'status', 'requested_by', 'created_at'])
            ->with([
                'fromWarehouse:id,name,code',
                'toWarehouse:id,name,code',
                'items:id,transfer_id,product_id,quantity',
                'items.product:id,name,code',
                'requestedByUser:id,name'
            ])
            ->latest()
            ->paginate(20);

        return response()->json($transfers);
    }

    /**
     * GET /api/transfers/statistics - Statistiques des transferts
     */
    public function statistics(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $stats = [
            'total_pending' => Transfer::where('tenant_id', $tenant_id)
                ->where('status', 'pending')
                ->count(),
            'total_approved' => Transfer::where('tenant_id', $tenant_id)
                ->where('status', 'approved')
                ->count(),
            'total_completed' => Transfer::where('tenant_id', $tenant_id)
                ->where('status', 'completed')
                ->count(),
            'total_cancelled' => Transfer::where('tenant_id', $tenant_id)
                ->where('status', 'cancelled')
                ->count(),
        ];

        return response()->json($stats);
    }
}
