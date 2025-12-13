<?php

namespace App\Domains\Accounting\Services;

use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class AutoPostingService
{
    private int $tenant_id;

    public function __construct(int $tenant_id = null)
    {
        $this->tenant_id = $tenant_id ?? auth()->guard('sanctum')->user()->tenant_id;
    }

    /**
     * Post Purchase Receipt (CMP-based COGS)
     * 
     * Debit: Inventory (Asset)
     * Credit: Payable (Liability)
     */
    public function postPurchaseReceived(Purchase $purchase): void
    {
        DB::beginTransaction();
        try {
            $total_amount = $purchase->total;
            $reference = 'PUR-RCV-' . $purchase->id;

            // Get account codes from chart
            $inventory_account = $this->getAccountCode('Inventory', 'Asset');
            $payable_account = $this->getAccountCode('Payable', 'Liability');

            // Debit: Inventory
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $inventory_account,
                'description' => "Purchase Receipt - {$purchase->supplier_name}",
                'type' => 'debit',
                'amount' => $total_amount,
                'category' => 'purchases',
                'source' => 'purchase',
                'source_id' => $purchase->id,
                'source_type' => 'App\Models\Purchase',
                'entry_date' => $purchase->received_date ?? now()->toDateString(),
                'status' => 'posted',
                'metadata' => [
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier_name,
                    'items_count' => $purchase->items->count(),
                ],
            ]);

            // Credit: Payable
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $payable_account,
                'description' => "Payable - {$purchase->supplier_name}",
                'type' => 'credit',
                'amount' => $total_amount,
                'category' => 'payable',
                'source' => 'purchase',
                'source_id' => $purchase->id,
                'source_type' => 'App\Models\Purchase',
                'entry_date' => $purchase->received_date ?? now()->toDateString(),
                'status' => 'posted',
                'metadata' => [
                    'supplier_id' => $purchase->supplier_id,
                ],
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Post Sale Completion (with COGS)
     * 
     * Debit: Cash / AR (Asset)
     * Debit: COGS (Expense)
     * Credit: Sales Revenue (Revenue)
     * Credit: Inventory (Asset - reduction)
     */
    public function postSaleCompleted(Sale $sale): void
    {
        DB::beginTransaction();
        try {
            $reference = 'SAL-CPL-' . $sale->id;
            $revenue_amount = $sale->total - $sale->tax_amount;
            $tax_amount = $sale->tax_amount;
            
            // Calculate COGS from items
            $cogs_total = 0;
            foreach ($sale->items as $item) {
                // Get cost_average from stock at time of sale
                $stock = Stock::where('tenant_id', $this->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                
                $cogs_total += ($item->quantity * ($stock->cost_average ?? 0));
            }

            // Get account codes
            $cash_account = $this->getPaymentAccount($sale->payment_method);
            $cogs_account = $this->getAccountCode('COGS', 'Expense');
            $revenue_account = $this->getAccountCode('Sales Revenue', 'Revenue');
            $inventory_account = $this->getAccountCode('Inventory', 'Asset');
            $tax_account = $this->getAccountCode('Sales Tax', 'Liability');

            // Debit: Cash / Receivable
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $cash_account,
                'description' => "Cash Sale - {$sale->customer_name}",
                'type' => 'debit',
                'amount' => $sale->amount_paid,
                'category' => 'sales',
                'source' => 'sale',
                'source_id' => $sale->id,
                'source_type' => 'App\Models\Sale',
                'entry_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                'status' => 'posted',
            ]);

            // Debit: COGS
            if ($cogs_total > 0) {
                AccountingEntry::create([
                    'tenant_id' => $this->tenant_id,
                    'user_id' => auth()->id(),
                    'reference' => $reference,
                    'account_code' => $cogs_account,
                    'description' => "COGS - Sale {$sale->reference}",
                    'type' => 'debit',
                    'amount' => $cogs_total,
                    'category' => 'cogs',
                    'source' => 'sale',
                    'source_id' => $sale->id,
                    'entry_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                    'status' => 'posted',
                ]);
            }

            // Credit: Revenue
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $revenue_account,
                'description' => "Sales Revenue - {$sale->customer_name}",
                'type' => 'credit',
                'amount' => $revenue_amount,
                'category' => 'sales',
                'source' => 'sale',
                'source_id' => $sale->id,
                'entry_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                'status' => 'posted',
            ]);

            // Credit: Tax Payable
            if ($tax_amount > 0) {
                AccountingEntry::create([
                    'tenant_id' => $this->tenant_id,
                    'user_id' => auth()->id(),
                    'reference' => $reference,
                    'account_code' => $tax_account,
                    'description' => "Sales Tax - {$sale->reference}",
                    'type' => 'credit',
                    'amount' => $tax_amount,
                    'category' => 'tax',
                    'source' => 'sale',
                    'source_id' => $sale->id,
                    'entry_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                    'status' => 'posted',
                ]);
            }

            // Credit: Inventory (reduction)
            if ($cogs_total > 0) {
                AccountingEntry::create([
                    'tenant_id' => $this->tenant_id,
                    'user_id' => auth()->id(),
                    'reference' => $reference,
                    'account_code' => $inventory_account,
                    'description' => "Inventory Reduction - Sale {$sale->reference}",
                    'type' => 'credit',
                    'amount' => $cogs_total,
                    'category' => 'inventory',
                    'source' => 'sale',
                    'source_id' => $sale->id,
                    'entry_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                    'status' => 'posted',
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Post Expense Entry
     * 
     * Debit: Operating Expense
     * Credit: Cash
     */
    public function postExpense(string $category, float $amount, string $description, string $payment_method = 'cash'): void
    {
        DB::beginTransaction();
        try {
            $reference = 'EXP-' . now()->format('YmdHis');

            $expense_account = $this->getExpenseAccount($category);
            $payment_account = $this->getPaymentAccount($payment_method);

            // Debit: Expense
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $expense_account,
                'description' => $description,
                'type' => 'debit',
                'amount' => $amount,
                'category' => 'expenses',
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'metadata' => ['expense_category' => $category],
            ]);

            // Credit: Payment Method
            AccountingEntry::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => auth()->id(),
                'reference' => $reference,
                'account_code' => $payment_account,
                'description' => "Payment - $description",
                'type' => 'credit',
                'amount' => $amount,
                'category' => 'payments',
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'metadata' => ['payment_method' => $payment_method],
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get account code from chart (with fallback)
     */
    private function getAccountCode(string $account_name, string $type): string
    {
        $account = ChartOfAccounts::where('tenant_id', $this->tenant_id)
            ->where('name', $account_name)
            ->where('type', $type)
            ->first();

        if (!$account) {
            // Fallback to standard codes
            $defaults = [
                'Inventory' => '1300',
                'Payable' => '2100',
                'Cash' => '1100',
                'Sales Revenue' => '4000',
                'COGS' => '5000',
                'Sales Tax' => '2200',
            ];
            return $defaults[$account_name] ?? '9999';
        }

        return $account->code;
    }

    /**
     * Get payment account based on method
     */
    private function getPaymentAccount(string $payment_method): string
    {
        return match ($payment_method) {
            'cash' => $this->getAccountCode('Cash', 'Asset'),
            'card' => $this->getAccountCode('Card Receivable', 'Asset') ?? '1200',
            'mobile_money' => $this->getAccountCode('Mobile Money', 'Asset') ?? '1250',
            'transfer' => $this->getAccountCode('Bank', 'Asset') ?? '1110',
            default => $this->getAccountCode('Cash', 'Asset'),
        };
    }

    /**
     * Get expense account by category
     */
    private function getExpenseAccount(string $category): string
    {
        $accounts = [
            'personnel' => '6100',  // Wages & Salaries
            'transport' => '6200',  // Transport & Logistics
            'utilities' => '6300',  // Utilities
            'maintenance' => '6400', // Maintenance
            'rent' => '6500',       // Rent
            'insurance' => '6600',  // Insurance
            'other' => '6900',      // Other Expenses
        ];

        return $accounts[$category] ?? '6900';
    }

    /**
     * Generate Trial Balance
     */
    public function getTrialBalance(string $as_of_date = null): array
    {
        $as_of = $as_of_date ? Carbon::parse($as_of_date) : now();

        $entries = AccountingEntry::where('tenant_id', $this->tenant_id)
            ->where('status', 'posted')
            ->where('entry_date', '<=', $as_of->toDateString())
            ->get();

        $trial_balance = [];

        foreach ($entries as $entry) {
            if (!isset($trial_balance[$entry->account_code])) {
                $trial_balance[$entry->account_code] = [
                    'debit' => 0,
                    'credit' => 0,
                    'description' => $entry->description,
                ];
            }

            if ($entry->type === 'debit') {
                $trial_balance[$entry->account_code]['debit'] += $entry->amount;
            } else {
                $trial_balance[$entry->account_code]['credit'] += $entry->amount;
            }
        }

        return [
            'as_of_date' => $as_of->toDateString(),
            'accounts' => $trial_balance,
            'totals' => [
                'total_debit' => collect($trial_balance)->sum('debit'),
                'total_credit' => collect($trial_balance)->sum('credit'),
            ],
        ];
    }

    /**
     * Verify GL balance
     */
    public function verifyBalance(): bool
    {
        $trial = $this->getTrialBalance();
        return abs($trial['totals']['total_debit'] - $trial['totals']['total_credit']) < 0.01;
    }
}
