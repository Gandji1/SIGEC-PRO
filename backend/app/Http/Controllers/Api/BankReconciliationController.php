<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur pour le rapprochement bancaire
 * Conforme au SYSCOHADA Révisé
 */
class BankReconciliationController extends Controller
{
    /**
     * Liste des relevés bancaires
     */
    public function statements(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = DB::table('bank_statements')
            ->where('tenant_id', $tenantId);
        
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        
        $statements = $query->orderBy('date_releve', 'desc')->get();
        
        return response()->json(['data' => $statements]);
    }

    /**
     * Créer un relevé bancaire
     */
    public function storeStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|integer',
            'date_releve' => 'required|date',
            'solde_releve' => 'required|numeric',
            'reference' => 'nullable|string|max:100',
        ]);
        
        $tenantId = auth()->user()->tenant_id;
        
        $id = DB::table('bank_statements')->insertGetId([
            'tenant_id' => $tenantId,
            'account_id' => $validated['account_id'],
            'date_releve' => $validated['date_releve'],
            'solde_releve' => $validated['solde_releve'],
            'reference' => $validated['reference'] ?? 'REL-' . date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Relevé enregistré',
            'data' => DB::table('bank_statements')->find($id)
        ], 201);
    }

    /**
     * Basculer le statut rapproché d'une écriture
     */
    public function toggleRapproche($entryId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $entry = DB::table('accounting_entries')
            ->where('tenant_id', $tenantId)
            ->where('id', $entryId)
            ->first();
        
        if (!$entry) {
            return response()->json(['message' => 'Écriture non trouvée'], 404);
        }
        
        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'rapproche' => !$entry->rapproche,
                'date_rapprochement' => !$entry->rapproche ? now() : null,
                'updated_at' => now(),
            ]);
        
        return response()->json([
            'message' => 'Statut mis à jour',
            'rapproche' => !$entry->rapproche
        ]);
    }

    /**
     * État de rapprochement
     */
    public function etatRapprochement(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $accountId = $request->input('account_id');
        $dateFin = $request->input('date_fin', date('Y-m-d'));
        
        if (!$accountId) {
            return response()->json(['message' => 'account_id requis'], 422);
        }
        
        // Dernier relevé
        $lastStatement = DB::table('bank_statements')
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->where('date_releve', '<=', $dateFin)
            ->orderBy('date_releve', 'desc')
            ->first();
        
        $soldeReleve = $lastStatement->solde_releve ?? 0;
        
        // Écritures non rapprochées
        $nonRapprochees = DB::table('accounting_entries')
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->where('rapproche', false)
            ->where('date', '<=', $dateFin)
            ->get();
        
        $debitsNonRapproches = $nonRapprochees->sum('debit');
        $creditsNonRapproches = $nonRapprochees->sum('credit');
        
        // Solde comptable
        $soldeComptable = DB::table('accounting_entries')
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->where('date', '<=', $dateFin)
            ->selectRaw('SUM(debit) - SUM(credit) as solde')
            ->value('solde') ?? 0;
        
        $soldeRapproche = $soldeReleve + $debitsNonRapproches - $creditsNonRapproches;
        $ecart = $soldeComptable - $soldeReleve;
        
        return response()->json([
            'data' => [
                'date_rapprochement' => $dateFin,
                'solde_releve' => $soldeReleve,
                'date_releve' => $lastStatement->date_releve ?? null,
                'debits_non_rapproches' => $debitsNonRapproches,
                'credits_non_rapproches' => $creditsNonRapproches,
                'solde_rapproche' => $soldeRapproche,
                'solde_comptable' => $soldeComptable,
                'ecart' => $ecart,
                'is_rapproche' => abs($ecart) < 1,
                'ecritures_non_rapprochees' => $nonRapprochees->count(),
            ]
        ]);
    }
}
