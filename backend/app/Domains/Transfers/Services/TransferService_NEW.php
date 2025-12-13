<?php

namespace App\Domains\Transfers\Services;

use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Exception;

class TransferService
{
    /**
     * Create a transfer request
     */
    public function createTransferRequest(array $data): Transfer
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $user_id = auth()->guard('sanctum')->id();

        $transfer = Transfer::create([
            'tenant_id' => $tenant_id,
            'user_id' => $user_id,
            'from_warehouse_id' => $data['from_warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'reference' => $this->generateReference($tenant_id),
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        AuditLog::log('create', 'transfer', $transfer->id, $data, 'Transfer request created');

        return $transfer;
    }

    /**
     * Add item to transfer
     */
    public function addItem(int $transfer_id, int $product_id, int $quantity): TransferItem
    {
        $transfer = Transfer::findOrFail($transfer_id);

        if ($transfer->status !== 'pending') {
            throw new Exception('Cannot add items to approved or completed transfer');
        }

        // Verify stock availability
        $stock = Stock::where('tenant_id', $transfer->tenant_id)
            ->where('product_id', $product_id)
            ->where('warehouse_id', $transfer->from_warehouse_id)
            ->first();

        if (!$stock || $stock->available < $quantity) {
            throw new Exception('Insufficient stock in source warehouse');
        }

        $item = TransferItem::create([
            'tenant_id' => $transfer->tenant_id,
            'transfer_id' => $transfer->id,
            'product_id' => $product_id,
            'quantity_requested' => $quantity,
            'quantity_transferred' => 0,
            'unit_cost' => $stock->cost_average ?? 0,
        ]);

        return $item;
    }

    /**
     * Approve transfer request
     */
    public function approvTransfer(int $transfer_id): Transfer
    {
        $transfer = Transfer::findOrFail($transfer_id);

        if ($transfer->status !== 'pending') {
            throw new Exception('Transfer must be in pending status to approve');
        }

        $transfer->status = 'approved';
        $transfer->approved_by_id = auth()->id();
        $transfer->approved_at = now();
        $transfer->save();

        AuditLog::log('update', 'transfer', $transfer->id, ['status' => 'approved'], 'Transfer approved');

        return $transfer;
    }

    /**
     * Execute transfer (move stock)
     */
    public function executeTransfer(int $transfer_id): Transfer
    {
        $transfer = Transfer::findOrFail($transfer_id);

        if ($transfer->status !== 'approved') {
            throw new Exception('Transfer must be approved before execution');
        }

        DB::beginTransaction();
        try {
            foreach ($transfer->items as $item) {
                // Verify final stock availability
                $source_stock = Stock::where('tenant_id', $transfer->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->first();

                if (!$source_stock || $source_stock->available < $item->quantity_requested) {
                    throw new Exception("Insufficient stock for product {$item->product_id}");
                }

                // Deduct from source
                $source_stock->quantity -= $item->quantity_requested;
                $source_stock->available -= $item->quantity_requested;
                $source_stock->save();

                // Add to destination
                $dest_stock = Stock::firstOrCreate(
                    [
                        'tenant_id' => $transfer->tenant_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                    ],
                    [
                        'quantity' => 0,
                        'cost_average' => $item->unit_cost,
                        'reserved' => 0,
                        'available' => 0,
                    ]
                );

                $dest_stock->quantity += $item->quantity_requested;
                $dest_stock->cost_average = $item->unit_cost; // Maintain CMP
                $dest_stock->available += $item->quantity_requested;
                $dest_stock->save();

                // Record movements (both source and destination)
                StockMovement::create([
                    'tenant_id' => $transfer->tenant_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'type' => 'transfer',
                    'quantity' => -$item->quantity_requested,
                    'unit_cost' => $item->unit_cost,
                    'reference' => 'TRF-' . $transfer->id,
                    'reference_id' => $transfer->id,
                    'user_id' => auth()->id(),
                    'metadata' => ['direction' => 'out', 'to_warehouse' => $transfer->to_warehouse_id],
                ]);

                StockMovement::create([
                    'tenant_id' => $transfer->tenant_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'type' => 'transfer',
                    'quantity' => $item->quantity_requested,
                    'unit_cost' => $item->unit_cost,
                    'reference' => 'TRF-' . $transfer->id,
                    'reference_id' => $transfer->id,
                    'user_id' => auth()->id(),
                    'metadata' => ['direction' => 'in', 'from_warehouse' => $transfer->from_warehouse_id],
                ]);

                $item->quantity_transferred = $item->quantity_requested;
                $item->save();
            }

            $transfer->status = 'completed';
            $transfer->executed_by_id = auth()->id();
            $transfer->executed_at = now();
            $transfer->save();

            AuditLog::log('update', 'transfer', $transfer->id, ['status' => 'completed'], 'Transfer executed');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $transfer;
    }

    /**
     * Cancel transfer
     */
    public function cancelTransfer(int $transfer_id): Transfer
    {
        $transfer = Transfer::findOrFail($transfer_id);

        if (!in_array($transfer->status, ['pending', 'approved'])) {
            throw new Exception("Cannot cancel transfer in {$transfer->status} status");
        }

        $transfer->status = 'cancelled';
        $transfer->save();

        AuditLog::log('update', 'transfer', $transfer->id, ['status' => 'cancelled'], 'Transfer cancelled');

        return $transfer;
    }

    /**
     * List pending approvals
     */
    public function getPendingApprovals(int $tenant_id): array
    {
        return Transfer::where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->with('items', 'fromWarehouse', 'toWarehouse')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    private function generateReference(int $tenant_id): string
    {
        $today = now()->format('Ymd');
        $count = Transfer::where('tenant_id', $tenant_id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "TRF-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
