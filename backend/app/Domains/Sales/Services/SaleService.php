<?php

namespace App\Domains\Sales\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Domains\Stocks\Services\StockService;
use App\Domains\Accounting\Services\AutoPostingService;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class SaleService
{
    private StockService $stockService;

    public function __construct()
    {
        $this->stockService = new StockService();
    }

    public function createSale(array $data): Sale
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $user_id = auth()->guard('sanctum')->id();

        $sale = Sale::create([
            'tenant_id' => $tenant_id,
            'user_id' => $user_id,
            'reference' => $this->generateReference($tenant_id),
            'mode' => $data['mode'] ?? 'manual',
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'status' => 'draft',
        ]);

        AuditLog::log('create', 'sale', $sale->id, $data, 'Sale created');

        return $sale;
    }

    public function addItem(Sale $sale, int $product_id, int $quantity, float $unit_price = null): SaleItem
    {
        $product = Product::find($product_id);

        if (!$product) {
            throw new Exception("Product not found");
        }

        $unit_price = $unit_price ?? $product->selling_price;
        
        // Calculate totals before creation
        $line_subtotal = $quantity * $unit_price;
        $tax_percent = $product->tax_percent ?? 0;
        $tax_amount = $tax_percent > 0 ? ($line_subtotal * $tax_percent) / 100 : 0;
        $line_total = $line_subtotal + $tax_amount;

        $item = SaleItem::create([
            'tenant_id' => $sale->tenant_id,
            'sale_id' => $sale->id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'line_subtotal' => $line_subtotal,
            'tax_percent' => $tax_percent,
            'tax_amount' => $tax_amount,
            'line_total' => $line_total,
            'unit' => $product->unit,
        ]);

        $sale->calculateTotals();
        $sale->save();

        return $item;
    }

    public function completeSale(Sale $sale, float $amount_paid, string $payment_method = 'cash'): Sale
    {
        // Vérifier que la vente n'est pas déjà complétée
        if ($sale->status === 'completed') {
            throw new Exception("Cette vente est déjà complétée");
        }

        // Vérifier qu'il y a des articles
        if ($sale->items->isEmpty()) {
            throw new Exception("La vente ne contient aucun article");
        }

        // Valider le stock (sauf mode POS sans stock)
        $tenant = \App\Models\Tenant::find($sale->tenant_id);
        $skipStockCheck = $tenant && $tenant->mode_pos === 'A';

        if (!$skipStockCheck) {
            foreach ($sale->items as $item) {
                if (!$this->stockService->reserveStock($item->product_id, $item->quantity)) {
                    $productName = $item->product->name ?? "ID: {$item->product_id}";
                    throw new Exception("Stock insuffisant pour: {$productName}");
                }
            }
        }

        // Calculer le coût des marchandises vendues (COGS)
        $costOfGoodsSold = 0;
        foreach ($sale->items as $item) {
            // Récupérer le stock actuel avec CMP
            $stock = Stock::where('product_id', $item->product_id)
                ->where('tenant_id', $sale->tenant_id)
                ->first();

            if ($stock && $stock->cost_average > 0) {
                $costOfGoodsSold += $stock->cost_average * $item->quantity;
            } elseif ($item->product && $item->product->purchase_price > 0) {
                $costOfGoodsSold += $item->product->purchase_price * $item->quantity;
            }

            // Déduire le stock (sauf mode A)
            if (!$skipStockCheck) {
                $this->stockService->removeStock($item->product_id, $item->quantity);
            }
        }

        $sale->amount_paid = $amount_paid;
        $sale->payment_method = $payment_method;
        $sale->cost_of_goods_sold = $costOfGoodsSold;
        $sale->complete();

        // Auto-post to GL avec COGS
        try {
            $autoPostingService = new AutoPostingService($sale->tenant_id);
            $autoPostingService->postSaleCompleted($sale);
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas bloquer la vente
            \Log::warning("Auto-posting failed for sale {$sale->id}: " . $e->getMessage());
        }

        AuditLog::log('update', 'sale', $sale->id, 
            ['status' => 'completed', 'amount_paid' => $amount_paid, 'cogs' => $costOfGoodsSold],
            "Sale completed with $payment_method payment (COGS: {$costOfGoodsSold})"
        );

        // Enregistrer le mouvement de caisse
        $this->recordCashMovement($sale, $amount_paid, $payment_method);

        return $sale;
    }

    public function cancelSale(Sale $sale): Sale
    {
        $sale->status = 'cancelled';
        $sale->save();

        // Libérer les stocks réservés
        foreach ($sale->items as $item) {
            $this->stockService->releaseStock($item->product_id, $item->quantity);
        }

        AuditLog::log('update', 'sale', $sale->id, ['status' => 'cancelled'], 'Sale cancelled');

        return $sale;
    }

    public function getSalesReport(string $start_date, string $end_date, $group_by = 'daily'): array
    {
        $sales = Sale::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start_date, $end_date])
            ->get();

        $report = [];

        foreach ($sales as $sale) {
            $key = match ($group_by) {
                'daily' => $sale->completed_at->format('Y-m-d'),
                'weekly' => $sale->completed_at->format('Y-W'),
                'monthly' => $sale->completed_at->format('Y-m'),
                default => 'total',
            };

            if (!isset($report[$key])) {
                $report[$key] = [
                    'count' => 0,
                    'total' => 0,
                    'tax' => 0,
                ];
            }

            $report[$key]['count']++;
            $report[$key]['total'] += $sale->total;
            $report[$key]['tax'] += $sale->tax_amount;
        }

        return $report;
    }

    public function getDailySales(string $date): float
    {
        return Sale::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->whereDate('completed_at', $date)
            ->sum('total');
    }

    private function generateReference(int $tenant_id): string
    {
        $today = now()->format('Ymd');
        $count = Sale::where('tenant_id', $tenant_id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "SALE-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Enregistrer le mouvement de caisse pour une vente
     */
    private function recordCashMovement(Sale $sale, float $amount, string $paymentMethod): void
    {
        try {
            $userId = auth()->guard('sanctum')->id() ?? $sale->user_id;
            
            // Trouver la session de caisse ouverte
            $session = CashRegisterSession::getOpenSession($sale->tenant_id);
            
            CashMovement::record(
                $sale->tenant_id,
                $userId,
                'in',
                'sale',
                $amount,
                "Vente {$sale->reference}",
                $session?->id,
                null,
                $paymentMethod,
                $sale->id,
                'sale'
            );
        } catch (\Exception $e) {
            \Log::warning("Cash movement recording failed for sale {$sale->id}: " . $e->getMessage());
        }
    }
}
