<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\PosOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingController extends Controller
{
    /**
     * Statistiques globales de tous les tenants
     */
    public function globalStats(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $query = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Revenus (ventes + POS)
        $salesRevenue = (clone $query(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('total');

        $posRevenue = (clone $query(PosOrder::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        $totalRevenue = $salesRevenue + $posRevenue;

        // Achats
        $totalPurchases = (clone $query(Purchase::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['received', 'completed'])
            ->sum('total');

        // Charges
        $totalExpenses = (clone $query(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // Marge brute
        $grossMargin = $totalRevenue - $totalPurchases;

        // Résultat net
        $netResult = $grossMargin - $totalExpenses;

        // Tenants actifs
        $activeTenants = Tenant::where('status', 'active')->count();

        // Tendance (comparaison avec période précédente)
        $periodDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevFrom = Carbon::parse($from)->subDays($periodDays)->toDateString();
        $prevTo = Carbon::parse($from)->subDay()->toDateString();

        $prevRevenue = Sale::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('sale_date', [$prevFrom, $prevTo])
            ->where('status', 'completed')
            ->sum('total');
        $prevRevenue += PosOrder::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        $revenueTrend = $prevRevenue > 0 
            ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 
            : 0;

        // Répartition des revenus par catégorie
        $revenueBreakdown = $this->getRevenueBreakdown($from, $to, $tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_purchases' => $totalPurchases,
                'total_expenses' => $totalExpenses,
                'gross_margin' => $grossMargin,
                'net_result' => $netResult,
                'active_tenants' => $activeTenants,
                'revenue_trend' => round($revenueTrend, 2),
                'revenue_breakdown' => $revenueBreakdown,
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
            ],
        ]);
    }

    /**
     * Statistiques par tenant
     */
    public function byTenant(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $tenants = Tenant::where('status', 'active')
            ->select('id', 'name', 'business_type')
            ->get();

        $stats = $tenants->map(function ($tenant) use ($from, $to) {
            // Revenus
            $salesRevenue = Sale::where('tenant_id', $tenant->id)
                ->whereBetween('sale_date', [$from, $to])
                ->where('status', 'completed')
                ->sum('total');

            $posRevenue = PosOrder::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['paid', 'validated', 'served'])
                ->sum('total');

            $revenue = $salesRevenue + $posRevenue;

            // Achats
            $purchases = Purchase::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['received', 'completed'])
                ->sum('total');

            // Charges
            $expenses = Expense::where('tenant_id', $tenant->id)
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

            // Calculs
            $grossMargin = $revenue - $purchases;
            $netResult = $grossMargin - $expenses;

            // Tendance (simplifié)
            $periodDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $prevFrom = Carbon::parse($from)->subDays($periodDays)->toDateString();
            $prevTo = Carbon::parse($from)->subDay()->toDateString();

            $prevRevenue = Sale::where('tenant_id', $tenant->id)
                ->whereBetween('sale_date', [$prevFrom, $prevTo])
                ->where('status', 'completed')
                ->sum('total');
            $prevRevenue += PosOrder::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$prevFrom, $prevTo])
                ->whereIn('status', ['paid', 'validated', 'served'])
                ->sum('total');

            $trend = $prevRevenue > 0 
                ? (($revenue - $prevRevenue) / $prevRevenue) * 100 
                : 0;

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'business_type' => $tenant->business_type,
                'revenue' => $revenue,
                'purchases' => $purchases,
                'expenses' => $expenses,
                'gross_margin' => $grossMargin,
                'net_result' => $netResult,
                'trend' => round($trend, 2),
            ];
        });

        // Trier par revenus décroissants
        $stats = $stats->sortByDesc('revenue')->values();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Répartition des revenus par catégorie
     */
    private function getRevenueBreakdown($from, $to, $tenantId = null): array
    {
        // Ventes gros
        $salesGros = Sale::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->where('sale_type', 'gros')
            ->sum('total');

        // Ventes détail
        $salesDetail = Sale::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->where('sale_type', 'detail')
            ->sum('total');

        // POS
        $posRevenue = PosOrder::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        $total = $salesGros + $salesDetail + $posRevenue;

        $breakdown = [];
        
        if ($salesGros > 0) {
            $breakdown[] = [
                'category' => 'Ventes Gros',
                'amount' => $salesGros,
                'percentage' => $total > 0 ? round(($salesGros / $total) * 100, 1) : 0,
            ];
        }
        
        if ($salesDetail > 0) {
            $breakdown[] = [
                'category' => 'Ventes Détail',
                'amount' => $salesDetail,
                'percentage' => $total > 0 ? round(($salesDetail / $total) * 100, 1) : 0,
            ];
        }
        
        if ($posRevenue > 0) {
            $breakdown[] = [
                'category' => 'Point de Vente (POS)',
                'amount' => $posRevenue,
                'percentage' => $total > 0 ? round(($posRevenue / $total) * 100, 1) : 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Export des données comptables
     */
    public function export(Request $request): JsonResponse
    {
        // TODO: Implémenter l'export CSV/Excel
        return response()->json([
            'success' => true,
            'message' => 'Export en cours de développement',
        ]);
    }

    /**
     * Résumé comptable agrégé (comme tenant mais multi-tenant)
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $baseQuery = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Ventes
        $totalSales = (clone $baseQuery(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('total');

        $totalSales += (clone $baseQuery(PosOrder::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        // Coût des ventes (approximation via achats)
        $costOfGoodsSold = (clone $baseQuery(Purchase::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['received', 'completed'])
            ->sum('total');

        // Marge brute
        $grossProfit = $totalSales - $costOfGoodsSold;

        // Charges
        $totalExpenses = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // Résultat net
        $netIncome = $grossProfit - $totalExpenses;

        // Marge bénéficiaire
        $profitMargin = $totalSales > 0 ? ($netIncome / $totalSales) * 100 : 0;

        // Valeur du stock
        $stockValue = DB::table('stocks')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->sum(DB::raw('stocks.quantity * products.purchase_price'));

        // TVA collectée
        $totalTax = (clone $baseQuery(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('tax_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'gross_profit' => $grossProfit,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'profit_margin' => round($profitMargin, 2),
                'stock_value' => $stockValue,
                'total_tax' => $totalTax,
                'period' => ['from' => $from, 'to' => $to],
                'tenant_id' => $tenantId,
            ],
        ]);
    }

    /**
     * Compte de résultat agrégé
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $baseQuery = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Produits d'exploitation
        $salesRevenue = (clone $baseQuery(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('total');

        $posRevenue = (clone $baseQuery(PosOrder::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        $totalRevenue = $salesRevenue + $posRevenue;

        // Charges d'exploitation
        $purchases = (clone $baseQuery(Purchase::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['received', 'completed'])
            ->sum('total');

        // Charges par catégorie
        $expensesByCategory = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->toArray();

        $totalExpenses = array_sum($expensesByCategory);

        // Résultats
        $grossProfit = $totalRevenue - $purchases;
        $operatingProfit = $grossProfit - $totalExpenses;

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => [
                    'sales' => $salesRevenue,
                    'pos' => $posRevenue,
                    'total' => $totalRevenue,
                ],
                'cost_of_sales' => $purchases,
                'gross_profit' => $grossProfit,
                'operating_expenses' => $expensesByCategory,
                'total_expenses' => $totalExpenses,
                'operating_profit' => $operatingProfit,
                'net_income' => $operatingProfit,
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    /**
     * Bilan agrégé
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $date = $request->query('date', now()->toDateString());

        $baseQuery = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Actifs
        $stockValue = DB::table('stocks')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->sum(DB::raw('stocks.quantity * products.purchase_price'));

        $cashBalance = DB::table('cash_movements')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('created_at', '<=', $date)
            ->sum(DB::raw("CASE WHEN type = 'in' THEN amount ELSE -amount END"));

        // Créances clients (ventes non payées)
        $receivables = (clone $baseQuery(Sale::class))
            ->whereDate('sale_date', '<=', $date)
            ->where('status', 'pending')
            ->sum('total');

        $totalAssets = $stockValue + $cashBalance + $receivables;

        // Passifs
        $payables = (clone $baseQuery(Purchase::class))
            ->whereDate('created_at', '<=', $date)
            ->where('status', 'pending')
            ->sum('total');

        // Capitaux propres (simplifié)
        $equity = $totalAssets - $payables;

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => [
                    'current' => [
                        'cash' => $cashBalance,
                        'inventory' => $stockValue,
                        'receivables' => $receivables,
                    ],
                    'total' => $totalAssets,
                ],
                'liabilities' => [
                    'current' => [
                        'payables' => $payables,
                    ],
                    'total' => $payables,
                ],
                'equity' => $equity,
                'date' => $date,
            ],
        ]);
    }

    /**
     * Balance générale agrégée
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        // Récupérer les écritures comptables agrégées
        $entries = DB::table('accounting_entries')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('entry_date', [$from, $to])
            ->join('chart_of_accounts', 'accounting_entries.account_id', '=', 'chart_of_accounts.id')
            ->select(
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) - SUM(credit) as balance')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name', 'chart_of_accounts.account_type')
            ->orderBy('chart_of_accounts.account_code')
            ->get();

        $totalDebit = $entries->sum('total_debit');
        $totalCredit = $entries->sum('total_credit');

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $entries,
                'totals' => [
                    'debit' => $totalDebit,
                    'credit' => $totalCredit,
                    'balanced' => abs($totalDebit - $totalCredit) < 0.01,
                ],
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    /**
     * Journal comptable agrégé
     */
    public function journal(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');
        $journalType = $request->query('journal_type');

        $entries = DB::table('accounting_entries')
            ->when($tenantId, fn($q) => $q->where('accounting_entries.tenant_id', $tenantId))
            ->when($journalType, fn($q) => $q->where('journal_type', $journalType))
            ->whereBetween('entry_date', [$from, $to])
            ->join('chart_of_accounts', 'accounting_entries.account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('tenants', 'accounting_entries.tenant_id', '=', 'tenants.id')
            ->select(
                'accounting_entries.*',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'tenants.name as tenant_name'
            )
            ->orderBy('entry_date', 'desc')
            ->orderBy('accounting_entries.id', 'desc')
            ->limit(500)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => $entries,
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    /**
     * Rapport de caisse agrégé
     */
    public function cashReport(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $movements = DB::table('cash_movements')
            ->when($tenantId, fn($q) => $q->where('cash_movements.tenant_id', $tenantId))
            ->whereBetween('cash_movements.created_at', [$from, $to])
            ->leftJoin('tenants', 'cash_movements.tenant_id', '=', 'tenants.id')
            ->select(
                'cash_movements.*',
                'tenants.name as tenant_name'
            )
            ->orderBy('cash_movements.created_at', 'desc')
            ->limit(500)
            ->get();

        // Résumé
        $summary = DB::table('cash_movements')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->select(
                DB::raw("SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out")
            )
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'movements' => $movements,
                'summary' => [
                    'total_in' => $summary->total_in ?? 0,
                    'total_out' => $summary->total_out ?? 0,
                    'net' => ($summary->total_in ?? 0) - ($summary->total_out ?? 0),
                ],
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    /**
     * Soldes Intermédiaires de Gestion (SIG) agrégés
     */
    public function sig(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $baseQuery = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Chiffre d'affaires
        $revenue = (clone $baseQuery(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('total');

        $revenue += (clone $baseQuery(PosOrder::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        // Achats consommés
        $purchases = (clone $baseQuery(Purchase::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['received', 'completed'])
            ->sum('total');

        // Marge commerciale
        $commercialMargin = $revenue - $purchases;

        // Charges externes
        $externalCharges = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->whereIn('category', ['utilities', 'rent', 'services'])
            ->sum('amount');

        // Valeur ajoutée
        $valueAdded = $commercialMargin - $externalCharges;

        // Charges de personnel
        $personnelCharges = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->where('category', 'salaries')
            ->sum('amount');

        // EBE (Excédent Brut d'Exploitation)
        $ebe = $valueAdded - $personnelCharges;

        // Autres charges
        $otherCharges = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->whereNotIn('category', ['utilities', 'rent', 'services', 'salaries'])
            ->sum('amount');

        // Résultat d'exploitation
        $operatingResult = $ebe - $otherCharges;

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue,
                'purchases' => $purchases,
                'commercial_margin' => $commercialMargin,
                'external_charges' => $externalCharges,
                'value_added' => $valueAdded,
                'personnel_charges' => $personnelCharges,
                'ebe' => $ebe,
                'other_charges' => $otherCharges,
                'operating_result' => $operatingResult,
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    /**
     * Ratios financiers agrégés
     */
    public function ratios(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tenantId = $request->query('tenant_id');

        $baseQuery = fn($model) => $model::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Données de base
        $revenue = (clone $baseQuery(Sale::class))
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'completed')
            ->sum('total');

        $revenue += (clone $baseQuery(PosOrder::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'validated', 'served'])
            ->sum('total');

        $purchases = (clone $baseQuery(Purchase::class))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['received', 'completed'])
            ->sum('total');

        $expenses = (clone $baseQuery(Expense::class))
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        $grossProfit = $revenue - $purchases;
        $netProfit = $grossProfit - $expenses;

        // Stock moyen
        $stockValue = DB::table('stocks')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->sum(DB::raw('stocks.quantity * products.purchase_price'));

        // Ratios
        $grossMarginRatio = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        $netMarginRatio = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        $stockTurnover = $stockValue > 0 ? $purchases / $stockValue : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'profitability' => [
                    'gross_margin' => round($grossMarginRatio, 2),
                    'net_margin' => round($netMarginRatio, 2),
                ],
                'activity' => [
                    'stock_turnover' => round($stockTurnover, 2),
                ],
                'base_data' => [
                    'revenue' => $revenue,
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'stock_value' => $stockValue,
                ],
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }
}
