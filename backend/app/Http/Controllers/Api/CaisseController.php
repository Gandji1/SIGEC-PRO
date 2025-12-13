<?php

namespace App\Http\Controllers\Api;

use App\Models\PosOrder;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\AccountingEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CaisseController extends Controller
{
    /**
     * Mouvements de caisse Gros
     */
    public function grosMovements(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        // Achats (sorties)
        $purchases = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->whereIn('status', ['received', 'completed', 'paid'])
            ->select('id', 'reference', 'total', 'created_at')
            ->get()
            ->map(fn($p) => [
                'date' => $p->created_at,
                'reference' => $p->reference,
                'type' => 'out',
                'description' => 'Achat fournisseur',
                'amount' => $p->total,
            ]);

        // Transferts vers détail (sorties virtuelles)
        $movements = $purchases->sortByDesc('date')->values();

        // Calculer solde courant
        $runningBalance = 0;
        $movements = $movements->map(function ($mv) use (&$runningBalance) {
            $runningBalance += ($mv['type'] === 'in' ? $mv['amount'] : -$mv['amount']);
            $mv['running_balance'] = $runningBalance;
            return $mv;
        });

        return response()->json(['data' => $movements]);
    }

    /**
     * Résumé caisse Gros
     */
    public function grosSummary(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $totalOut = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->whereIn('status', ['received', 'completed', 'paid'])
            ->sum('total');

        return response()->json(['data' => [
            'total_in' => 0,
            'total_out' => $totalOut,
            'balance' => -$totalOut,
            'operations_count' => Purchase::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->count(),
        ]]);
    }

    /**
     * Mouvements de caisse Détail
     */
    public function detailMovements(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        // Ventes POS (entrées)
        $sales = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->select('id', 'reference', 'total', 'created_at')
            ->get()
            ->map(fn($s) => [
                'date' => $s->created_at,
                'reference' => $s->reference,
                'type' => 'in',
                'description' => 'Vente POS',
                'amount' => $s->total,
            ]);

        // Charges (sorties)
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->select('id', 'reference', 'amount', 'date', 'description')
            ->get()
            ->map(fn($e) => [
                'date' => $e->date,
                'reference' => $e->reference,
                'type' => 'out',
                'description' => $e->description ?? 'Charge',
                'amount' => $e->amount,
            ]);

        $movements = $sales->concat($expenses)->sortByDesc('date')->values();

        // Calculer solde courant
        $runningBalance = 0;
        $movements = $movements->map(function ($mv) use (&$runningBalance) {
            $runningBalance += ($mv['type'] === 'in' ? $mv['amount'] : -$mv['amount']);
            $mv['running_balance'] = $runningBalance;
            return $mv;
        });

        return response()->json(['data' => $movements]);
    }

    /**
     * Résumé caisse Détail
     */
    public function detailSummary(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $totalIn = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->sum('total');

        $totalOut = Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return response()->json(['data' => [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'balance' => $totalIn - $totalOut,
            'operations_count' => PosOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->count() + Expense::where('tenant_id', $tenantId)
                ->whereBetween('date', [$from, $to])
                ->count(),
        ]]);
    }

    /**
     * Mouvements caisses POS
     */
    public function posMovements(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $posId = $request->query('pos_id');

        $query = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->where('status', 'completed');

        if ($posId) {
            $query->where('pos_id', $posId);
        }

        $movements = $query->select('id', 'reference', 'total', 'payment_method', 'created_at', 'pos_id')
            ->with('pos:id,name')
            ->get()
            ->map(fn($s) => [
                'date' => $s->created_at,
                'reference' => $s->reference,
                'type' => 'in',
                'description' => 'Vente ' . ($s->pos->name ?? 'POS'),
                'amount' => $s->total,
                'payment_method' => $s->payment_method,
            ]);

        // Calculer solde courant
        $runningBalance = 0;
        $movements = $movements->map(function ($mv) use (&$runningBalance) {
            $runningBalance += $mv['amount'];
            $mv['running_balance'] = $runningBalance;
            return $mv;
        });

        return response()->json(['data' => $movements]);
    }

    /**
     * Résumé caisses POS
     */
    public function posSummary(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $totalIn = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->sum('total');

        return response()->json(['data' => [
            'total_in' => $totalIn,
            'total_out' => 0,
            'balance' => $totalIn,
            'operations_count' => PosOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->where('status', 'completed')
                ->count(),
        ]]);
    }
}
