<?php

namespace App\Http\Controllers\Api;

use App\Models\Transfer;
use App\Domains\Transfers\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransferController_NEW extends Controller
{
    private TransferService $transferService;

    public function __construct()
    {
        $this->transferService = new TransferService();
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    /**
     * List transfers
     */
    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $status = $request->query('status');
        $perPage = $request->query('per_page', 20);

        $query = Transfer::where('tenant_id', $tenant_id);

        if ($status) {
            $query->where('status', $status);
        }

        $transfers = $query->with('items', 'fromWarehouse', 'toWarehouse')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $transfers,
            'pagination' => [
                'current_page' => $transfers->currentPage(),
                'total' => $transfers->total(),
                'per_page' => $transfers->perPage(),
            ],
        ]);
    }

    /**
     * Create transfer request
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'description' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $transfer = $this->transferService->createTransferRequest($validated);

            foreach ($validated['items'] as $item) {
                $this->transferService->addItem(
                    $transfer->id,
                    $item['product_id'],
                    $item['quantity']
                );
            }

            return response()->json($transfer->fresh()->load('items'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get transfer details
     */
    public function show(Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        return response()->json($transfer->load('items', 'fromWarehouse', 'toWarehouse'));
    }

    /**
     * Approve transfer
     */
    public function approve(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'error' => 'Transfer must be in pending status to approve',
            ], 400);
        }

        try {
            $transfer = $this->transferService->approvTransfer($transfer->id);

            return response()->json([
                'message' => 'Transfer approved',
                'transfer' => $transfer,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Execute transfer (move stock)
     */
    public function execute(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        if ($transfer->status !== 'approved') {
            return response()->json([
                'error' => 'Transfer must be approved before execution',
            ], 400);
        }

        try {
            $transfer = $this->transferService->executeTransfer($transfer->id);

            return response()->json([
                'message' => 'Transfer executed successfully',
                'transfer' => $transfer->fresh()->load('items'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel transfer
     */
    public function cancel(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        try {
            $transfer = $this->transferService->cancelTransfer($transfer->id);

            return response()->json($transfer);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Pending approvals
     */
    public function pending(): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $pending = $this->transferService->getPendingApprovals($tenant_id);

        return response()->json([
            'count' => count($pending),
            'transfers' => $pending,
        ]);
    }
}
