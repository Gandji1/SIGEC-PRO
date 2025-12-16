<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ComptabiliteController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = auth()->guard('sanctum')->user();
        
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }
        
        // SuperAdmin peut utiliser le header X-Tenant-ID pour impersonation
        if ($user && in_array($user->role, ['super_admin', 'superadmin'])) {
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                return (int) $tenantId;
            }
        }
        
        return null;
    }

    /**
     * Liste des écritures comptables (Journaux) - Générées à partir des données réelles
     */
    public function entries(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $journal = $request->query('journal');

        $entries = [];

        // Ventes
        if (!$journal || $journal === 'ventes' || $journal === 'all') {
            $sales = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->get();

            foreach ($sales as $sale) {
                $entries[] = [
                    'date' => date('Y-m-d', strtotime($sale->created_at)),
                    'reference' => $sale->reference,
                    'account_code' => '411',
                    'account_name' => 'Clients',
                    'description' => 'Vente ' . ($sale->customer_name ?: 'Comptoir'),
                    'debit' => (float) $sale->total,
                    'credit' => 0,
                    'journal_type' => 'ventes',
                ];
                $entries[] = [
                    'date' => date('Y-m-d', strtotime($sale->created_at)),
                    'reference' => $sale->reference,
                    'account_code' => '701',
                    'account_name' => 'Ventes de marchandises',
                    'description' => 'Vente ' . ($sale->customer_name ?: 'Comptoir'),
                    'debit' => 0,
                    'credit' => (float) ($sale->subtotal ?? $sale->total),
                    'journal_type' => 'ventes',
                ];
            }
        }

        // Achats
        if (!$journal || $journal === 'achats' || $journal === 'all') {
            $purchases = DB::table('purchases')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->get();

            foreach ($purchases as $purchase) {
                $entries[] = [
                    'date' => date('Y-m-d', strtotime($purchase->created_at)),
                    'reference' => $purchase->reference,
                    'account_code' => '601',
                    'account_name' => 'Achats de marchandises',
                    'description' => 'Achat ' . ($purchase->supplier_name ?? 'Fournisseur'),
                    'debit' => (float) $purchase->total,
                    'credit' => 0,
                    'journal_type' => 'achats',
                ];
                $entries[] = [
                    'date' => date('Y-m-d', strtotime($purchase->created_at)),
                    'reference' => $purchase->reference,
                    'account_code' => '401',
                    'account_name' => 'Fournisseurs',
                    'description' => 'Achat ' . ($purchase->supplier_name ?? 'Fournisseur'),
                    'debit' => 0,
                    'credit' => (float) $purchase->total,
                    'journal_type' => 'achats',
                ];
            }
        }

        // Dépenses
        if (!$journal || $journal === 'operations' || $journal === 'all') {
            $expenses = DB::table('expenses')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$from, $to])
                ->get();

            foreach ($expenses as $expense) {
                $entries[] = [
                    'date' => $expense->date,
                    'reference' => 'DEP-' . $expense->id,
                    'account_code' => '6',
                    'account_name' => 'Charges - ' . ($expense->category ?? 'Diverses'),
                    'description' => $expense->description ?? 'Dépense',
                    'debit' => (float) $expense->amount,
                    'credit' => 0,
                    'journal_type' => 'operations',
                ];
            }
        }

        // Trier par date
        usort($entries, fn($a, $b) => strcmp($a['date'], $b['date']));

        return response()->json(['data' => $entries]);
    }

    /**
     * Grand Livre - Mouvements d'un compte (données réelles)
     */
    public function grandLivre(Request $request, $accountId): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        // Récupérer le compte
        $account = ChartOfAccounts::where('tenant_id', $tenantId)->where('id', $accountId)->first();
        if (!$account) {
            return response()->json(['error' => 'Compte non trouvé'], 404);
        }

        $movements = [];
        $code = $account->code;

        // Ventes (comptes 411, 701, 571)
        if (in_array($code, ['411', '701', '571']) || str_starts_with($code, '7')) {
            $sales = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->get();

            foreach ($sales as $sale) {
                if ($code === '411' || str_starts_with($code, '41')) {
                    $movements[] = ['date' => date('Y-m-d', strtotime($sale->created_at)), 'reference' => $sale->reference, 'description' => 'Vente ' . ($sale->customer_name ?: 'Comptoir'), 'debit' => (float) $sale->total, 'credit' => 0];
                }
                if ($code === '701' || str_starts_with($code, '70')) {
                    $movements[] = ['date' => date('Y-m-d', strtotime($sale->created_at)), 'reference' => $sale->reference, 'description' => 'Vente ' . ($sale->customer_name ?: 'Comptoir'), 'debit' => 0, 'credit' => (float) ($sale->subtotal ?? $sale->total)];
                }
                if ($code === '571' && $sale->payment_method === 'cash') {
                    $movements[] = ['date' => date('Y-m-d', strtotime($sale->created_at)), 'reference' => $sale->reference, 'description' => 'Encaissement vente', 'debit' => (float) $sale->amount_paid, 'credit' => 0];
                }
            }
        }

        // Achats (comptes 601, 401)
        if (in_array($code, ['601', '401']) || str_starts_with($code, '6') || str_starts_with($code, '40')) {
            $purchases = DB::table('purchases')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->get();

            foreach ($purchases as $purchase) {
                if ($code === '601' || str_starts_with($code, '60')) {
                    $movements[] = ['date' => date('Y-m-d', strtotime($purchase->created_at)), 'reference' => $purchase->reference, 'description' => 'Achat ' . ($purchase->supplier_name ?? 'Fournisseur'), 'debit' => (float) $purchase->total, 'credit' => 0];
                }
                if ($code === '401' || str_starts_with($code, '40')) {
                    $movements[] = ['date' => date('Y-m-d', strtotime($purchase->created_at)), 'reference' => $purchase->reference, 'description' => 'Dette fournisseur', 'debit' => 0, 'credit' => (float) $purchase->total];
                }
            }
        }

        // Immobilisations (comptes 2x, 28x, 681)
        if (str_starts_with($code, '2') || $code === '681') {
            $immobilisations = DB::table('immobilisations')
                ->where('tenant_id', $tenantId)
                ->where('date_acquisition', '<=', $to)
                ->get();

            foreach ($immobilisations as $immo) {
                $catCode = substr($immo->category_code, 0, 2);
                $annuite = ($immo->valeur_acquisition - $immo->valeur_residuelle) / $immo->duree_vie;

                if (str_starts_with($code, '2' . $catCode) || $code === '2' . $catCode) {
                    $movements[] = ['date' => $immo->date_acquisition, 'reference' => 'IMMO-' . $immo->id, 'description' => 'Acquisition ' . $immo->designation, 'debit' => (float) $immo->valeur_acquisition, 'credit' => 0];
                }
                if (str_starts_with($code, '28') && str_contains($code, $catCode)) {
                    $movements[] = ['date' => $to, 'reference' => 'AMORT-' . $immo->id, 'description' => 'Amortissement ' . $immo->designation, 'debit' => 0, 'credit' => round($immo->cumul_amortissement, 2)];
                }
                if ($code === '681') {
                    $movements[] = ['date' => $to, 'reference' => 'DOT-' . $immo->id, 'description' => 'Dotation ' . $immo->designation, 'debit' => round($annuite, 2), 'credit' => 0];
                }
            }
        }

        // Calculer solde courant
        usort($movements, fn($a, $b) => strcmp($a['date'], $b['date']));
        $runningBalance = 0;
        foreach ($movements as &$mv) {
            $runningBalance += ($mv['debit'] - $mv['credit']);
            $mv['running_balance'] = $runningBalance;
        }

        return response()->json([
            'data' => $movements,
            'opening_balance' => 0,
            'closing_balance' => $runningBalance,
        ]);
    }

    /**
     * Balance Comptable (données réelles)
     */
    public function balance(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $balanceData = [];

        // Ventes
        $sales = DB::table('sales')->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to . ' 23:59:59']);
        $totalVentes = (clone $sales)->sum('subtotal') ?? 0;
        $totalClients = (clone $sales)->sum('total') ?? 0;
        $totalCaisse = (clone $sales)->where('payment_method', 'cash')->sum('amount_paid') ?? 0;

        if ($totalVentes > 0) {
            $balanceData[] = ['code' => '701', 'name' => 'Ventes de marchandises', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => 0, 'credit_mouvement' => $totalVentes, 'debit_solde' => 0, 'credit_solde' => $totalVentes];
            $balanceData[] = ['code' => '411', 'name' => 'Clients', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => $totalClients, 'credit_mouvement' => 0, 'debit_solde' => $totalClients, 'credit_solde' => 0];
        }
        if ($totalCaisse > 0) {
            $balanceData[] = ['code' => '571', 'name' => 'Caisse', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => $totalCaisse, 'credit_mouvement' => 0, 'debit_solde' => $totalCaisse, 'credit_solde' => 0];
        }

        // Achats
        $totalAchats = DB::table('purchases')->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to . ' 23:59:59'])->sum('total') ?? 0;
        if ($totalAchats > 0) {
            $balanceData[] = ['code' => '601', 'name' => 'Achats de marchandises', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => $totalAchats, 'credit_mouvement' => 0, 'debit_solde' => $totalAchats, 'credit_solde' => 0];
            $balanceData[] = ['code' => '401', 'name' => 'Fournisseurs', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => 0, 'credit_mouvement' => $totalAchats, 'debit_solde' => 0, 'credit_solde' => $totalAchats];
        }

        // Dépenses
        $totalDepenses = DB::table('expenses')->where('tenant_id', $tenantId)->whereBetween('date', [$from, $to])->sum('amount') ?? 0;
        if ($totalDepenses > 0) {
            $balanceData[] = ['code' => '6', 'name' => 'Charges diverses', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => $totalDepenses, 'credit_mouvement' => 0, 'debit_solde' => $totalDepenses, 'credit_solde' => 0];
        }

        // Immobilisations et Amortissements
        $immobilisations = DB::table('immobilisations')->where('tenant_id', $tenantId)->where('status', 'active')->get();
        $immoByCategory = [];
        $totalDotation = 0;

        foreach ($immobilisations as $immo) {
            $catCode = substr($immo->category_code, 0, 2);
            if (!isset($immoByCategory[$catCode])) {
                $immoByCategory[$catCode] = ['valeur' => 0, 'cumul' => 0, 'label' => $immo->category_label];
            }
            $immoByCategory[$catCode]['valeur'] += $immo->valeur_acquisition;
            $immoByCategory[$catCode]['cumul'] += $immo->cumul_amortissement;
            $totalDotation += ($immo->valeur_acquisition - $immo->valeur_residuelle) / $immo->duree_vie;
        }

        foreach ($immoByCategory as $catCode => $data) {
            $balanceData[] = ['code' => '2' . $catCode, 'name' => $data['label'] ?? 'Immobilisations', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => $data['valeur'], 'credit_mouvement' => 0, 'debit_solde' => $data['valeur'], 'credit_solde' => 0];
            if ($data['cumul'] > 0) {
                $balanceData[] = ['code' => '28' . $catCode, 'name' => 'Amortissements ' . ($data['label'] ?? ''), 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => 0, 'credit_mouvement' => $data['cumul'], 'debit_solde' => 0, 'credit_solde' => $data['cumul']];
            }
        }

        if ($totalDotation > 0) {
            $balanceData[] = ['code' => '681', 'name' => 'Dotations aux amortissements', 'debit_ouverture' => 0, 'credit_ouverture' => 0, 'debit_mouvement' => round($totalDotation, 2), 'credit_mouvement' => 0, 'debit_solde' => round($totalDotation, 2), 'credit_solde' => 0];
        }

        // Trier par code
        usort($balanceData, fn($a, $b) => strcmp($a['code'], $b['code']));

        return response()->json(['data' => $balanceData]);
    }
}
