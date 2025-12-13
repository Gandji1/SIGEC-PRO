<?php

namespace App\Domains\Accounting\Services;

use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * AccountingPostingService
 * 
 * Génère automatiquement les écritures comptables pour les opérations
 * - Ventes
 * - Achats
 * - Transferts
 * - Ajustements d'inventaire
 * - Calcul CMP (Coût Moyen Pondéré)
 */
class AccountingPostingService
{
    /**
     * Génère les écritures comptables pour une vente
     * 
     * Schéma comptable:
     * - Débit: Caisse (530x) / Banque (512x)
     * - Crédit: Ventes (70x)
     * - Débit: COGS (60x) / Stock (37x)
     * - Crédit: Réduction Stock (37x)
     * 
     * @param int $tenantId
     * @param int $saleId
     * @param float $amount
     * @param float $costAmount
     * @param string $reference
     * @return void
     */
    public function generateSaleEntries(int $tenantId, int $saleId, float $amount, float $costAmount, string $reference): void
    {
        try {
            DB::beginTransaction();

            // Compte ventes (revenu)
            $salesAccount = $this->getAccountBySubType($tenantId, 'sales', 'revenue');
            if (!$salesAccount) {
                throw new Exception('Compte de ventes non trouvé dans le plan comptable');
            }

            // Compte caisse (hypothèse : paiement en cash)
            $cashAccount = $this->getAccountBySubType($tenantId, 'cash', 'asset');
            if (!$cashAccount) {
                throw new Exception('Compte de caisse non trouvé');
            }

            // Créer l'écriture principale : Caisse/Débit et Ventes/Crédit
            AccountingEntry::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'date' => now()->format('Y-m-d'),
                'debit_account_id' => $cashAccount->id,
                'credit_account_id' => $salesAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => $amount,
                'description' => "Vente #{$saleId}",
                'source' => 'sale',
                'metadata' => ['sale_id' => $saleId],
            ]);

            // Créer l'écriture de coût : COGS/Débit et Stock/Crédit
            $cogsAccount = $this->getAccountBySubType($tenantId, 'cogs', 'expense');
            $stockAccount = $this->getAccountBySubType($tenantId, 'inventory', 'asset');

            if ($cogsAccount && $stockAccount) {
                AccountingEntry::create([
                    'tenant_id' => $tenantId,
                    'reference' => $reference . '-COGS',
                    'date' => now()->format('Y-m-d'),
                    'debit_account_id' => $cogsAccount->id,
                    'credit_account_id' => $stockAccount->id,
                    'debit_amount' => $costAmount,
                    'credit_amount' => $costAmount,
                    'description' => "Coût des ventes #{$saleId}",
                    'source' => 'sale',
                    'metadata' => ['sale_id' => $saleId, 'is_cogs' => true],
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Génère les écritures comptables pour un achat
     * 
     * Schéma comptable:
     * - Débit: Stock (37x)
     * - Crédit: Fournisseurs (401x) ou Caisse (530x)
     * 
     * @param int $tenantId
     * @param int $purchaseId
     * @param float $amount
     * @param string $reference
     * @return void
     */
    public function generatePurchaseEntries(int $tenantId, int $purchaseId, float $amount, string $reference): void
    {
        try {
            DB::beginTransaction();

            $stockAccount = $this->getAccountBySubType($tenantId, 'inventory', 'asset');
            $payableAccount = $this->getAccountBySubType($tenantId, 'payable', 'liability');

            if (!$stockAccount || !$payableAccount) {
                throw new Exception('Comptes de stock ou fournisseurs non trouvés');
            }

            AccountingEntry::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'date' => now()->format('Y-m-d'),
                'debit_account_id' => $stockAccount->id,
                'credit_account_id' => $payableAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => $amount,
                'description' => "Achat #{$purchaseId}",
                'source' => 'purchase',
                'metadata' => ['purchase_id' => $purchaseId],
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Génère les écritures comptables pour un transfert inter-entrepôts
     * 
     * Transfert gros → détail: les comptes changent selon les reclassements internes
     * Approche simplifiée: pas d'écriture si transfert interne (pas de changement de valeur)
     * 
     * @param int $tenantId
     * @param int $fromWarehouseId
     * @param int $toWarehouseId
     * @param float $value
     * @param string $reference
     * @return void
     */
    public function generateTransferEntries(int $tenantId, int $fromWarehouseId, int $toWarehouseId, float $value, string $reference): void
    {
        // Les transferts internes ne génèrent généralement pas d'écritures comptables
        // sauf s'il y a une valorisation (ex: gros → détail avec markup)
        
        // Cas simplifié : pas d'écriture (transfert = reclassement interne)
        // À implémenter si besoin de valorisation des transferts
    }

    /**
     * Génère les écritures comptables pour un ajustement d'inventaire
     * 
     * Schéma comptable:
     * - Si excédent: Débit Stock / Crédit Écart d'inventaire (bon résultat)
     * - Si déficit: Débit Écart / Crédit Stock (mauvais résultat)
     * 
     * @param int $tenantId
     * @param float $amount (peut être négatif)
     * @param string $reference
     * @param string $description
     * @return void
     */
    public function generateAdjustmentEntries(int $tenantId, float $amount, string $reference, string $description = 'Ajustement d\'inventaire'): void
    {
        try {
            DB::beginTransaction();

            $stockAccount = $this->getAccountBySubType($tenantId, 'inventory', 'asset');
            $adjustmentAccount = $this->getAccountBySubType($tenantId, 'adjustment', 'expense') ?? 
                                $this->getAccountBySubType($tenantId, 'other_income', 'revenue');

            if (!$stockAccount || !$adjustmentAccount) {
                throw new Exception('Comptes d\'ajustement non trouvés');
            }

            if ($amount > 0) {
                // Excédent : Stock augmente
                $debitId = $stockAccount->id;
                $creditId = $adjustmentAccount->id;
            } else {
                // Déficit : Stock diminue
                $debitId = $adjustmentAccount->id;
                $creditId = $stockAccount->id;
                $amount = abs($amount);
            }

            AccountingEntry::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'date' => now()->format('Y-m-d'),
                'debit_account_id' => $debitId,
                'credit_account_id' => $creditId,
                'debit_amount' => $amount,
                'credit_amount' => $amount,
                'description' => $description,
                'source' => 'adjustment',
                'metadata' => ['type' => 'inventory'],
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calcule le CMP (Coût Moyen Pondéré) pour un produit
     * 
     * CMP = (Ancien Stock × Ancien Coût + Nouvel Achat × Coût Achat) / (Ancien Stock + Nouvel Achat)
     * 
     * @param float $oldQuantity
     * @param float $oldCost
     * @param float $newQuantity
     * @param float $newCost
     * @return float (CMP)
     */
    public static function calculateCMP(float $oldQuantity, float $oldCost, float $newQuantity, float $newCost): float
    {
        if ($oldQuantity + $newQuantity == 0) {
            return 0;
        }

        return ($oldQuantity * $oldCost + $newQuantity * $newCost) / ($oldQuantity + $newQuantity);
    }

    /**
     * Trouve un compte par sous-type et type
     * 
     * @param int $tenantId
     * @param string $subType
     * @param string $type
     * @return ChartOfAccounts|null
     */
    private function getAccountBySubType(int $tenantId, string $subType, string $type): ?ChartOfAccounts
    {
        return ChartOfAccounts::where('tenant_id', $tenantId)
            ->where('sub_type', $subType)
            ->where('account_type', $type)
            ->active()
            ->first();
    }

    /**
     * Obtient tous les comptes d'un type
     * 
     * @param int $tenantId
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccountsByType(int $tenantId, string $type)
    {
        return ChartOfAccounts::where('tenant_id', $tenantId)
            ->where('account_type', $type)
            ->active()
            ->get();
    }

    /**
     * Génère le Grand Livre (Ledger) pour un compte
     * 
     * @param int $tenantId
     * @param int $accountId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array
     */
    public function getLedger(int $tenantId, int $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = AccountingEntry::where('tenant_id', $tenantId)
            ->where(function ($q) use ($accountId) {
                $q->where('debit_account_id', $accountId)
                  ->orWhere('credit_account_id', $accountId);
            });

        if ($fromDate) {
            $query->whereDate('date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('date', '<=', $toDate);
        }

        $entries = $query->orderBy('date')->get();

        $ledger = [];
        $balance = 0;

        foreach ($entries as $entry) {
            if ($entry->debit_account_id == $accountId) {
                $balance += $entry->debit_amount;
                $debit = $entry->debit_amount;
                $credit = 0;
            } else {
                $balance -= $entry->credit_amount;
                $debit = 0;
                $credit = $entry->credit_amount;
            }

            $ledger[] = [
                'date' => $entry->date,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        }

        return $ledger;
    }

    /**
     * Génère la Balance de Vérification (Trial Balance)
     * 
     * @param int $tenantId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array
     */
    public function getTrialBalance(int $tenantId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $accounts = ChartOfAccounts::where('tenant_id', $tenantId)->active()->get();

        $balance = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $query = AccountingEntry::where('tenant_id', $tenantId)
                ->where(function ($q) use ($account) {
                    $q->where('debit_account_id', $account->id)
                      ->orWhere('credit_account_id', $account->id);
                });

            if ($fromDate) {
                $query->whereDate('date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('date', '<=', $toDate);
            }

            $debit = $query->where('debit_account_id', $account->id)->sum('debit_amount');
            $credit = $query->where('credit_account_id', $account->id)->sum('credit_amount');

            if ($debit > 0 || $credit > 0) {
                $balance[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'debit' => $debit,
                    'credit' => $credit,
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
            }
        }

        return [
            'items' => $balance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }
}
