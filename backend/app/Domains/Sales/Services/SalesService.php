<?php

namespace App\Domains\Sales\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Str;
use DB;
use Exception;

class SalesService
{
    /**
     * Create a new sale (shopping cart)
     */
    public function createSale(array $data)
    {
        $sale = Sale::create([
            'tenant_id' => $data['tenant_id'],
            'user_id' => $data['user_id'],
            'warehouse_id' => $data['warehouse_id'],
            'reference' => $this->generateReference(),
            'customer_name' => $data['customer_name'] ?? 'Walk-in',
            'customer_phone' => $data['customer_phone'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'notes' => $data['notes'] ?? null,
        ]);

        return $sale;
    }

    /**
     * Add item to sale
     */
    public function addItem($sale, $productId, $quantity, $tenantId)
    {
        // Get current stock with CMP
        $stock = Stock::where('product_id', $productId)
            ->where('warehouse_id', $sale->warehouse_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if ($stock->quantity < $quantity) {
            throw new Exception("Insufficient stock for product {$productId}");
        }

        // Get product price (use CMP as unit price)
        $unitPrice = $stock->cost_average ?? 0;

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
            'cost_average' => $stock->cost_average,
        ]);

        return $item;
    }

    /**
     * Complete sale (deduct stock + create payments + accounting entries)
     */
    public function completeSale($sale, $tenantId)
    {
        return DB::transaction(function () use ($sale, $tenantId) {
            $totalAmount = 0;
            $totalCost = 0;

            // Process each sale item (deduct stock)
            foreach ($sale->items as $item) {
                // Deduct from warehouse stock
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $sale->warehouse_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Check again (double-check for concurrency)
                if ($stock->quantity < $item->quantity) {
                    throw new Exception("Insufficient stock for product {$item->product_id}");
                }

                // Deduct quantity
                $oldQty = $stock->quantity;
                $stock->quantity -= $item->quantity;
                $stock->save();

                // Create StockMovement for audit trail
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'stock_id' => $stock->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'quantity_change' => -$item->quantity,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'old_qty' => $oldQty,
                    'new_qty' => $stock->quantity,
                    'cost_average' => $item->cost_average,
                ]);

                $totalAmount += $item->total;
                $totalCost += $item->quantity * ($item->cost_average ?? 0);
            }

            // Calculate tax (18% VAT)
            $subtotal = $totalAmount;
            $tax = $subtotal * 0.18;
            $totalWithTax = $subtotal + $tax;

            // Update sale totals
            $sale->subtotal = $subtotal;
            $sale->tax_amount = $tax;
            $sale->total_amount = $totalWithTax;
            $sale->status = 'completed';
            $sale->completed_at = now();
            $sale->save();

            // Create payment record
            $payment = SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $sale->payment_method,
                'amount' => $totalWithTax,
                'status' => 'completed',
                'reference' => $this->generatePaymentReference($sale->payment_method),
            ]);

            $sale->total_paid = $totalWithTax;
            $sale->payment_status = 'paid';
            $sale->save();

            // TODO: Create accounting entries
            // - Revenue entry (DR: Cash, CR: Revenue)
            // - COGS entry (DR: COGS, CR: Inventory)

            return $sale->load(['items', 'items.product']);
        });
    }

    /**
     * Cancel sale (restore stock if not yet completed)
     */
    public function cancelSale($sale, $tenantId)
    {
        if ($sale->status === 'completed') {
            throw new Exception('Cannot cancel completed sale');
        }

        $sale->status = 'cancelled';
        $sale->save();

        return $sale;
    }

    /**
     * Get sales by date range
     */
    public function getSalesReport($tenantId, $fromDate, $toDate)
    {
        return Sale::where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->where('status', 'completed')
            ->with(['items', 'items.product', 'warehouse'])
            ->get()
            ->groupBy(function ($sale) {
                return $sale->created_at->format('Y-m-d');
            })
            ->map(function ($dailySales) {
                return [
                    'count' => $dailySales->count(),
                    'total_amount' => $dailySales->sum('total_amount'),
                    'total_paid' => $dailySales->sum('total_paid'),
                    'sales' => $dailySales,
                ];
            });
    }

    /**
     * Auto-transfer from gros to detail to pos when stock low
     */
    public function autoTransferIfNeeded($warehouseId, $tenantId)
    {
        // Get all products in warehouse with low stock
        $lowStockProducts = Stock::where('warehouse_id', $warehouseId)
            ->where('tenant_id', $tenantId)
            ->where('quantity', '<=', 10)
            ->get();

        foreach ($lowStockProducts as $stock) {
            // TODO: Create automatic transfer from parent warehouse
        }
    }

    /**
     * Process payment (cash/momo/bank)
     */
    public function processPayment($sale, $amount, $method)
    {
        if ($amount > $sale->total_amount - $sale->total_paid) {
            throw new Exception('Payment amount exceeds remaining balance');
        }

        $payment = SalePayment::create([
            'sale_id' => $sale->id,
            'method' => $method,
            'amount' => $amount,
            'status' => 'completed',
            'reference' => $this->generatePaymentReference($method),
        ]);

        // Update sale payment status
        $sale->total_paid += $amount;

        if ($sale->total_paid >= $sale->total_amount) {
            $sale->payment_status = 'paid';
        } else {
            $sale->payment_status = 'partial';
        }

        $sale->save();

        return $payment;
    }

    private function generateReference(): string
    {
        return 'SALE-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    private function generatePaymentReference($method): string
    {
        return strtoupper($method) . '-' . date('YmdHis') . '-' . Str::random(4);
    }
}
