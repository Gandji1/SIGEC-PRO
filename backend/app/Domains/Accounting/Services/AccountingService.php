<?php

namespace App\Domains\Accounting\Services;

use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use Illuminate\Database\Eloquent\Collection;

class AccountingService
{
    /**
     * Créer une entrée comptable
     * Adapte les données du format service vers le format table
     */
    public function createEntry(array $data): AccountingEntry
    {
        // Ajouter user_id si non fourni
        if (!isset($data['user_id'])) {
            $data['user_id'] = auth()->guard('sanctum')->id() ?? 1;
        }
        
        // Adapter account_id vers account_code
        if (isset($data['account_id']) && !isset($data['account_code'])) {
            $account = ChartOfAccounts::find($data['account_id']);
            $data['account_code'] = $account ? $account->code : '0000';
            unset($data['account_id']);
        }
        
        // Adapter debit/credit vers type/amount
        if (isset($data['debit']) || isset($data['credit'])) {
            $debit = $data['debit'] ?? 0;
            $credit = $data['credit'] ?? 0;
            
            if ($debit > 0) {
                $data['type'] = 'debit';
                $data['amount'] = $debit;
            } else {
                $data['type'] = 'credit';
                $data['amount'] = $credit;
            }
            
            unset($data['debit'], $data['credit']);
        }
        
        // Adapter date vers entry_date
        if (isset($data['date']) && !isset($data['entry_date'])) {
            $data['entry_date'] = $data['date'];
            unset($data['date']);
        }
        
        // Ajouter entry_date si non fournie
        if (!isset($data['entry_date'])) {
            $data['entry_date'] = now()->toDateString();
        }
        
        // Supprimer journal (non utilisé dans la table)
        unset($data['journal']);
        
        // Rendre reference unique
        $data['reference'] = $data['reference'] . '-' . uniqid();
        
        return AccountingEntry::create($data);
    }

    /**
     * Enregistrer un mouvement de stock automatiquement
     */
    public function recordStockMovement(array $data): AccountingEntry
    {
        return $this->createEntry([
            'tenant_id' => $data['tenant_id'],
            'account_id' => $data['account_id'],
            'journal' => $data['journal'] ?? 'ST', // Stock Journal
            'reference' => $data['reference'],
            'description' => $data['description'] ?? 'Mouvement de stock',
            'debit' => $data['debit'] ?? 0,
            'credit' => $data['credit'] ?? 0,
            'date' => $data['date'] ?? now(),
        ]);
    }

    /**
     * Enregistrer une vente automatiquement
     */
    public function recordSale(array $data): AccountingEntry
    {
        return $this->createEntry([
            'tenant_id' => $data['tenant_id'],
            'account_id' => $data['account_id'],
            'journal' => 'VT', // Ventes Journal
            'reference' => $data['reference'],
            'description' => $data['description'] ?? 'Vente',
            'debit' => $data['debit'] ?? 0,
            'credit' => $data['credit'] ?? 0,
            'date' => $data['date'] ?? now(),
        ]);
    }

    /**
     * Enregistrer un achat automatiquement
     */
    public function recordPurchase(array $data): AccountingEntry
    {
        return $this->createEntry([
            'tenant_id' => $data['tenant_id'],
            'account_id' => $data['account_id'],
            'journal' => 'AC', // Achats Journal
            'reference' => $data['reference'],
            'description' => $data['description'] ?? 'Achat',
            'debit' => $data['debit'] ?? 0,
            'credit' => $data['credit'] ?? 0,
            'date' => $data['date'] ?? now(),
        ]);
    }

    /**
     * Récupérer le solde d'un compte
     */
    public function getAccountBalance(int $accountId, int $tenantId): float
    {
        $entries = AccountingEntry::where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->get();

        $total = 0;
        foreach ($entries as $entry) {
            $total += $entry->debit - $entry->credit;
        }

        return $total;
    }

    /**
     * Récupérer les entrées par journal
     */
    public function getJournalEntries(string $journal, int $tenantId): Collection
    {
        return AccountingEntry::where('tenant_id', $tenantId)
            ->where('journal', $journal)
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Générer une balance comptable
     */
    public function generateBalance(int $tenantId, $fromDate = null, $toDate = null): array
    {
        $query = AccountingEntry::where('tenant_id', $tenantId);

        if ($fromDate) {
            $query->where('date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('date', '<=', $toDate);
        }

        $entries = $query->get();
        $balance = [];

        foreach ($entries as $entry) {
            $accountId = $entry->account_id;
            if (!isset($balance[$accountId])) {
                $balance[$accountId] = ['debit' => 0, 'credit' => 0];
            }
            $balance[$accountId]['debit'] += $entry->debit;
            $balance[$accountId]['credit'] += $entry->credit;
        }

        return $balance;
    }

    /**
     * Enregistrer ecritures pour reception achat
     */
    public function postPurchaseReception($purchase): void
    {
        $tenantId = $purchase->tenant_id;
        $total = $purchase->total;
        $reference = $purchase->reference;

        // Debit: Stock (31xx)
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
            'journal' => 'AC',
            'reference' => $reference,
            'description' => 'Reception achat - Stock',
            'debit' => $total,
            'credit' => 0,
            'date' => now(),
        ]);

        // Credit: Fournisseur (401x)
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '4010', 'Fournisseurs'),
            'journal' => 'AC',
            'reference' => $reference,
            'description' => 'Reception achat - Fournisseur',
            'debit' => 0,
            'credit' => $total,
            'date' => now(),
        ]);
    }

    /**
     * Enregistrer ecritures pour transfert
     */
    public function postTransfer($transfer): void
    {
        $tenantId = $transfer->tenant_id;
        $totalValue = $transfer->items->sum(fn($item) => $item->quantity * $item->unit_cost);
        $reference = $transfer->reference;

        // Transfert interne - pas d'impact P&L, juste mouvement entre comptes de stock
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
            'journal' => 'ST',
            'reference' => $reference,
            'description' => 'Transfert interne - Sortie ' . $transfer->fromWarehouse->name,
            'debit' => 0,
            'credit' => $totalValue,
            'date' => now(),
        ]);

        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
            'journal' => 'ST',
            'reference' => $reference,
            'description' => 'Transfert interne - Entree ' . $transfer->toWarehouse->name,
            'debit' => $totalValue,
            'credit' => 0,
            'date' => now(),
        ]);
    }

    /**
     * Enregistrer ecritures pour ajustement inventaire
     */
    public function postInventoryAdjustment($inventory): void
    {
        $tenantId = $inventory->tenant_id;
        $reference = $inventory->reference;

        $totalVarianceValue = $inventory->items->sum('variance_value');

        if ($totalVarianceValue != 0) {
            if ($totalVarianceValue > 0) {
                // Surplus - augmentation stock
                $this->createEntry([
                    'tenant_id' => $tenantId,
                    'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
                    'journal' => 'OD',
                    'reference' => $reference,
                    'description' => 'Ajustement inventaire - Surplus',
                    'debit' => abs($totalVarianceValue),
                    'credit' => 0,
                    'date' => now(),
                ]);

                $this->createEntry([
                    'tenant_id' => $tenantId,
                    'account_id' => $this->getAccountId($tenantId, '7580', 'Autres produits'),
                    'journal' => 'OD',
                    'reference' => $reference,
                    'description' => 'Ajustement inventaire - Surplus',
                    'debit' => 0,
                    'credit' => abs($totalVarianceValue),
                    'date' => now(),
                ]);
            } else {
                // Manquant - diminution stock
                $this->createEntry([
                    'tenant_id' => $tenantId,
                    'account_id' => $this->getAccountId($tenantId, '6580', 'Autres charges'),
                    'journal' => 'OD',
                    'reference' => $reference,
                    'description' => 'Ajustement inventaire - Manquant',
                    'debit' => abs($totalVarianceValue),
                    'credit' => 0,
                    'date' => now(),
                ]);

                $this->createEntry([
                    'tenant_id' => $tenantId,
                    'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
                    'journal' => 'OD',
                    'reference' => $reference,
                    'description' => 'Ajustement inventaire - Manquant',
                    'debit' => 0,
                    'credit' => abs($totalVarianceValue),
                    'date' => now(),
                ]);
            }
        }
    }

    /**
     * Enregistrer ecritures pour vente POS
     */
    public function postSale($order): void
    {
        $tenantId = $order->tenant_id;
        $reference = $order->reference;
        $total = $order->total;
        $cogs = $order->items->sum(fn($item) => $item->quantity_served * $item->unit_cost);

        // Debit: Caisse/Client
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '5200', 'Caisse'),
            'journal' => 'VT',
            'reference' => $reference,
            'description' => 'Vente - Encaissement',
            'debit' => $total,
            'credit' => 0,
            'date' => now(),
        ]);

        // Credit: Ventes
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '7010', 'Ventes marchandises'),
            'journal' => 'VT',
            'reference' => $reference,
            'description' => 'Vente - Chiffre affaires',
            'debit' => 0,
            'credit' => $total,
            'date' => now(),
        ]);

        // Cout des ventes
        if ($cogs > 0) {
            $this->createEntry([
                'tenant_id' => $tenantId,
                'account_id' => $this->getAccountId($tenantId, '6030', 'Cout des ventes'),
                'journal' => 'VT',
                'reference' => $reference,
                'description' => 'Vente - Cout marchandises vendues',
                'debit' => $cogs,
                'credit' => 0,
                'date' => now(),
            ]);

            $this->createEntry([
                'tenant_id' => $tenantId,
                'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
                'journal' => 'VT',
                'reference' => $reference,
                'description' => 'Vente - Sortie stock',
                'debit' => 0,
                'credit' => $cogs,
                'date' => now(),
            ]);
        }
    }

    /**
     * Generer ecriture pour transfert entre entrepots
     */
    public function generateTransferEntry(int $tenantId, int $productId, int $fromWarehouseId, int $toWarehouseId, float $value, string $reference): void
    {
        $this->createEntry([
            'tenant_id' => $tenantId,
            'account_id' => $this->getAccountId($tenantId, '3100', 'Stock marchandises'),
            'journal' => 'ST',
            'reference' => $reference,
            'description' => "Transfert produit $productId",
            'debit' => $value,
            'credit' => $value,
            'date' => now(),
        ]);
    }

    /**
     * Obtenir ou creer un compte comptable
     * Note: La contrainte UNIQUE est sur 'code' seul, pas sur (tenant_id, code)
     * On doit contourner le TenantScope pour chercher globalement
     */
    protected function getAccountId(int $tenantId, string $code, string $name): int
    {
        // Requête SQL directe pour contourner le TenantScope
        $existing = \DB::table('chart_of_accounts')
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();
        
        if ($existing) {
            return $existing->id;
        }

        // Vérifier aussi les supprimés
        $deleted = \DB::table('chart_of_accounts')
            ->where('code', $code)
            ->whereNotNull('deleted_at')
            ->first();
        
        if ($deleted) {
            // Restaurer le compte supprimé
            \DB::table('chart_of_accounts')
                ->where('id', $deleted->id)
                ->update(['deleted_at' => null, 'updated_at' => now()]);
            return $deleted->id;
        }

        // Créer seulement si n'existe vraiment pas
        $id = \DB::table('chart_of_accounts')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'account_type' => $this->getAccountType($code),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Determiner le type de compte selon le code
     * Types valides: asset, liability, equity, revenue, expense
     */
    protected function getAccountType(string $code): string
    {
        $firstDigit = substr($code, 0, 1);

        return match ($firstDigit) {
            '1' => 'equity',      // Capitaux propres
            '2' => 'asset',       // Immobilisations
            '3' => 'asset',       // Stocks (actif)
            '4' => 'liability',   // Tiers (passif)
            '5' => 'asset',       // Trésorerie (actif)
            '6' => 'expense',     // Charges
            '7' => 'revenue',     // Produits
            default => 'asset',
        };
    }
}
