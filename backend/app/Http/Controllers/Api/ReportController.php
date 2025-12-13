<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Stock;
use App\Models\AccountingEntry;
use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Requête de base pour les ventes comptabilisables
     * Inclut: completed, paid, ou draft avec paiement effectué
     */
    protected function salesBaseQuery($tenantId)
    {
        return Sale::where('tenant_id', $tenantId)
            ->where(function($q) {
                $q->whereIn('status', ['completed', 'paid'])
                  ->orWhere(function($q2) {
                      $q2->where('status', 'draft')
                         ->where('amount_paid', '>', 0);
                  });
            });
    }

    /**
     * Requête de base pour les achats comptabilisables
     */
    protected function purchasesBaseQuery($tenantId)
    {
        return Purchase::where('tenant_id', $tenantId)
            ->whereIn('status', ['received', 'completed', 'partial']);
    }

    /**
     * Requête de base pour les dépenses
     */
    protected function expensesBaseQuery($tenantId, $startDate, $endDate)
    {
        return Expense::where('tenant_id', $tenantId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->where(function($q2) use ($startDate, $endDate) {
                    $q2->whereNotNull('expense_date')
                       ->whereDate('expense_date', '>=', $startDate)
                       ->whereDate('expense_date', '<=', $endDate);
                })->orWhere(function($q2) use ($startDate, $endDate) {
                    $q2->whereNull('expense_date')
                       ->whereDate('created_at', '>=', $startDate)
                       ->whereDate('created_at', '<=', $endDate);
                });
            });
    }

    /**
     * Sales Journal (sync small export) - Données réelles POS
     */
    public function salesJournal(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        $sales = $this->salesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->with('items')
            ->orderBy('created_at')
            ->get();

        $journal = $sales->map(fn($s) => [
            'date' => ($s->completed_at ?? $s->created_at)->format('Y-m-d'),
            'reference' => $s->reference,
            'customer' => $s->customer_name ?? 'Client comptoir',
            'total_ht' => (float) ($s->subtotal ?? ($s->total - $s->tax_amount)),
            'tax' => (float) $s->tax_amount,
            'total_ttc' => (float) $s->total,
            'amount_paid' => (float) $s->amount_paid,
            'payment_method' => $s->payment_method,
            'status' => $s->status,
            'items_count' => $s->items->count(),
            'cost_of_goods_sold' => (float) $s->cost_of_goods_sold,
        ]);

        return response()->json([
            'period' => ['start' => $start_date, 'end' => $end_date],
            'entries' => $journal,
            'summary' => [
                'total_sales' => (float) $sales->sum('total'),
                'total_paid' => (float) $sales->sum('amount_paid'),
                'total_tax' => (float) $sales->sum('tax_amount'),
                'total_cogs' => (float) $sales->sum('cost_of_goods_sold'),
                'gross_profit' => (float) ($sales->sum('total') - $sales->sum('cost_of_goods_sold')),
                'transaction_count' => $sales->count(),
            ],
        ]);
    }

    /**
     * Purchases Journal - Achats fournisseurs
     */
    public function purchasesJournal(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        $purchases = $this->purchasesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->with(['items', 'supplier'])
            ->orderBy('created_at')
            ->get();

        $journal = $purchases->map(fn($p) => [
            'date' => ($p->received_date ?? $p->created_at)->format('Y-m-d'),
            'reference' => $p->reference,
            'supplier' => $p->supplier_name ?? $p->supplier?->name ?? 'Fournisseur',
            'total_ht' => (float) ($p->total - ($p->tax_amount ?? 0)),
            'tax' => (float) ($p->tax_amount ?? 0),
            'total_ttc' => (float) $p->total,
            'status' => $p->status,
            'items_count' => $p->items->count(),
        ]);

        return response()->json([
            'period' => ['start' => $start_date, 'end' => $end_date],
            'entries' => $journal,
            'summary' => [
                'total_purchases' => (float) $purchases->sum('total'),
                'total_tax' => (float) $purchases->sum('tax_amount'),
                'transaction_count' => $purchases->count(),
            ],
        ]);
    }

    /**
     * P&L Statement (Income Statement) - Données réelles
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        // Ventes réelles (POS)
        $salesQuery = $this->salesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date);
        
        $sales = (clone $salesQuery)->sum('total') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;

        // Dépenses réelles (table expenses)
        $expenses = $this->expensesBaseQuery($tenant_id, $start_date, $end_date)
            ->sum('amount') ?? 0;

        // Dépenses par catégorie
        $expensesByCategory = $this->expensesBaseQuery($tenant_id, $start_date, $end_date)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->toArray();

        $gross_profit = $sales - $cogs;
        $net_income = $gross_profit - $expenses;
        $margin_percent = $sales > 0 ? ($net_income / $sales) * 100 : 0;
        $gross_margin = $sales > 0 ? ($gross_profit / $sales) * 100 : 0;

        return response()->json([
            'period' => ['start' => $start_date, 'end' => $end_date],
            'revenue' => round($sales, 2),
            'cost_of_goods_sold' => round($cogs, 2),
            'gross_profit' => round($gross_profit, 2),
            'gross_margin_percent' => round($gross_margin, 2),
            'expenses' => round($expenses, 2),
            'expenses_by_category' => $expensesByCategory,
            'net_income' => round($net_income, 2),
            'margin_percent' => round($margin_percent, 2),
        ]);
    }

    /**
     * Trial Balance - Données réelles
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $start_date = $request->query('start_date', now()->startOfMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        $accounts = [];

        // Ventes
        $salesQuery = $this->salesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date);
        
        $sales = (clone $salesQuery)->sum('subtotal') ?? 0;
        $salesTotal = (clone $salesQuery)->sum('total') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;
        $tax = (clone $salesQuery)->sum('tax_amount') ?? 0;
        $cashIn = (clone $salesQuery)->sum('amount_paid') ?? 0;

        if ($sales > 0) {
            $accounts['701 - Ventes'] = ['debit' => 0, 'credit' => $sales];
        }
        if ($cogs > 0) {
            $accounts['601 - Coût des marchandises'] = ['debit' => $cogs, 'credit' => 0];
        }
        if ($tax > 0) {
            $accounts['4457 - TVA collectée'] = ['debit' => 0, 'credit' => $tax];
        }
        if ($cashIn > 0) {
            $accounts['571 - Caisse (entrées)'] = ['debit' => $cashIn, 'credit' => 0];
        }

        // Achats
        $purchases = $this->purchasesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->sum('total') ?? 0;

        if ($purchases > 0) {
            $accounts['401 - Fournisseurs'] = ['debit' => 0, 'credit' => $purchases];
            $accounts['601 - Achats marchandises'] = ['debit' => $purchases, 'credit' => 0];
        }

        // Dépenses par catégorie
        $expenses = $this->expensesBaseQuery($tenant_id, $start_date, $end_date)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        $totalExpenses = 0;
        foreach ($expenses as $exp) {
            $accountName = '6xx - Charges ' . ucfirst($exp->category);
            $accounts[$accountName] = ['debit' => (float) $exp->total, 'credit' => 0];
            $totalExpenses += $exp->total;
        }

        if ($totalExpenses > 0) {
            $accounts['571 - Caisse (sorties)'] = ['debit' => 0, 'credit' => $totalExpenses];
        }

        return response()->json([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'accounts' => $accounts,
            'totals' => [
                'total_debit' => collect($accounts)->sum('debit'),
                'total_credit' => collect($accounts)->sum('credit'),
            ],
        ]);
    }

    /**
     * Export Sales Journal to XLSX (sync small)
     */
    public function exportSalesXlsx(Request $request)
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $start_date = $request->query('start_date', now()->subMonth()->toDateString());
        $end_date = $request->query('end_date', now()->toDateString());

        $sales = $this->salesBaseQuery($tenant_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->with('items')
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Journal');

        // Headers
        $headers = ['Date', 'Reference', 'Customer', 'Total HT', 'Tax', 'Total TTC', 'Items'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Data
        $row = 2;
        foreach ($sales as $sale) {
            $sheet->setCellValueByColumnAndRow(1, $row, ($sale->completed_at ?? $sale->created_at)->format('Y-m-d'));
            $sheet->setCellValueByColumnAndRow(2, $row, $sale->reference);
            $sheet->setCellValueByColumnAndRow(3, $row, $sale->customer_name ?? 'Client comptoir');
            $sheet->setCellValueByColumnAndRow(4, $row, $sale->subtotal ?? ($sale->total - $sale->tax_amount));
            $sheet->setCellValueByColumnAndRow(5, $row, $sale->tax_amount);
            $sheet->setCellValueByColumnAndRow(6, $row, $sale->total);
            $sheet->setCellValueByColumnAndRow(7, $row, $sale->items->count());
            $row++;
        }

        // Auto-fit columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'sales_' . $start_date . '_' . $end_date . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $writer->save('php://output');
        exit;
    }
}
