<?php

namespace App\Services;

use App\Models\User;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\Purchase;
use App\Models\PosOrder;
use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service d'automatisation pour les rôles virtuels
 * Gère les opérations automatiques pour: Magasinier, Comptable, Caissier
 */
class VirtualRoleAutomationService
{
    protected $tenantId;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    // =====================================================
    // MAGASINIER GROS - Automatisations
    // =====================================================

    /**
     * Auto-approuver les demandes de stock urgentes du magasin détail
     */
    public function autoApproveUrgentRequests(): array
    {
        $approved = [];
        
        $requests = StockRequest::where('tenant_id', $this->tenantId)
            ->where('status', 'requested')
            ->where('priority', 'urgent')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->get();

        foreach ($requests as $request) {
            try {
                $request->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => null, // Auto-approved
                    'notes' => ($request->notes ?? '') . "\n[AUTO] Approuvé automatiquement (urgence)"
                ]);
                $approved[] = $request->reference;
            } catch (\Exception $e) {
                Log::error("Auto-approve failed for request {$request->id}: " . $e->getMessage());
            }
        }

        return $approved;
    }

    /**
     * Auto-créer les transferts pour les demandes approuvées
     */
    public function autoCreateTransfersFromApprovedRequests(): array
    {
        $created = [];

        $requests = StockRequest::where('tenant_id', $this->tenantId)
            ->where('status', 'approved')
            ->whereNull('transfer_id')
            ->with('items')
            ->get();

        foreach ($requests as $request) {
            try {
                DB::transaction(function () use ($request, &$created) {
                    $transfer = Transfer::create([
                        'tenant_id' => $this->tenantId,
                        'reference' => 'TRF-AUTO-' . now()->format('YmdHis') . '-' . $request->id,
                        'from_warehouse_id' => $request->to_warehouse_id, // Gros
                        'to_warehouse_id' => $request->from_warehouse_id, // Détail
                        'status' => 'pending',
                        'notes' => "Créé automatiquement depuis demande {$request->reference}",
                        'stock_request_id' => $request->id,
                    ]);

                    foreach ($request->items as $item) {
                        $transfer->items()->create([
                            'product_id' => $item->product_id,
                            'quantity_requested' => $item->quantity,
                            'quantity_approved' => $item->quantity,
                        ]);
                    }

                    $request->update(['transfer_id' => $transfer->id]);
                    $created[] = $transfer->reference;
                });
            } catch (\Exception $e) {
                Log::error("Auto-create transfer failed for request {$request->id}: " . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Alerter sur les stocks bas
     */
    public function checkLowStockAlerts(): array
    {
        $alerts = [];

        $lowStocks = Stock::where('tenant_id', $this->tenantId)
            ->where('quantity', '<=', 10)
            ->with('product', 'warehouse')
            ->get();

        foreach ($lowStocks as $stock) {
            $alerts[] = [
                'product' => $stock->product->name ?? 'N/A',
                'warehouse' => $stock->warehouse->name ?? 'N/A',
                'quantity' => $stock->quantity,
                'min_quantity' => $stock->min_quantity ?? 10,
            ];
        }

        return $alerts;
    }

    // =====================================================
    // COMPTABLE - Automatisations
    // =====================================================

    /**
     * Auto-générer les écritures comptables pour les ventes
     */
    public function autoGenerateSalesEntries(): array
    {
        $generated = [];

        $sales = PosOrder::where('tenant_id', $this->tenantId)
            ->where('status', 'completed')
            ->whereNull('accounting_entry_id')
            ->get();

        foreach ($sales as $sale) {
            try {
                DB::transaction(function () use ($sale, &$generated) {
                    // Compte Caisse (débit)
                    $caisseAccount = ChartOfAccounts::where('tenant_id', $this->tenantId)
                        ->where('code', '571') // Caisse
                        ->first();

                    // Compte Ventes (crédit)
                    $venteAccount = ChartOfAccounts::where('tenant_id', $this->tenantId)
                        ->where('code', '701') // Ventes de marchandises
                        ->first();

                    if ($caisseAccount && $venteAccount) {
                        $entry = AccountingEntry::create([
                            'tenant_id' => $this->tenantId,
                            'reference' => 'EC-VENTE-' . $sale->reference,
                            'date' => $sale->created_at->toDateString(),
                            'description' => "Vente POS {$sale->reference}",
                            'status' => 'posted',
                        ]);

                        // Ligne débit (Caisse)
                        $entry->lines()->create([
                            'account_id' => $caisseAccount->id,
                            'debit' => $sale->total,
                            'credit' => 0,
                            'description' => 'Encaissement vente',
                        ]);

                        // Ligne crédit (Ventes)
                        $entry->lines()->create([
                            'account_id' => $venteAccount->id,
                            'debit' => 0,
                            'credit' => $sale->total,
                            'description' => 'Produit de vente',
                        ]);

                        $sale->update(['accounting_entry_id' => $entry->id]);
                        $generated[] = $entry->reference;
                    }
                });
            } catch (\Exception $e) {
                Log::error("Auto-generate entry failed for sale {$sale->id}: " . $e->getMessage());
            }
        }

        return $generated;
    }

    /**
     * Auto-générer les écritures pour les achats
     */
    public function autoGeneratePurchaseEntries(): array
    {
        $generated = [];

        $purchases = Purchase::where('tenant_id', $this->tenantId)
            ->where('status', 'received')
            ->whereNull('accounting_entry_id')
            ->get();

        foreach ($purchases as $purchase) {
            try {
                DB::transaction(function () use ($purchase, &$generated) {
                    // Compte Stock (débit)
                    $stockAccount = ChartOfAccounts::where('tenant_id', $this->tenantId)
                        ->where('code', '31') // Stock de marchandises
                        ->first();

                    // Compte Fournisseur (crédit)
                    $fournisseurAccount = ChartOfAccounts::where('tenant_id', $this->tenantId)
                        ->where('code', '401') // Fournisseurs
                        ->first();

                    if ($stockAccount && $fournisseurAccount) {
                        $entry = AccountingEntry::create([
                            'tenant_id' => $this->tenantId,
                            'reference' => 'EC-ACHAT-' . $purchase->reference,
                            'date' => $purchase->received_at ?? now()->toDateString(),
                            'description' => "Achat {$purchase->reference}",
                            'status' => 'posted',
                        ]);

                        $entry->lines()->create([
                            'account_id' => $stockAccount->id,
                            'debit' => $purchase->total,
                            'credit' => 0,
                            'description' => 'Entrée stock',
                        ]);

                        $entry->lines()->create([
                            'account_id' => $fournisseurAccount->id,
                            'debit' => 0,
                            'credit' => $purchase->total,
                            'description' => 'Dette fournisseur',
                        ]);

                        $purchase->update(['accounting_entry_id' => $entry->id]);
                        $generated[] = $entry->reference;
                    }
                });
            } catch (\Exception $e) {
                Log::error("Auto-generate entry failed for purchase {$purchase->id}: " . $e->getMessage());
            }
        }

        return $generated;
    }

    // =====================================================
    // CAISSIER - Automatisations
    // =====================================================

    /**
     * Auto-clôturer les sessions de caisse inactives
     */
    public function autoCloseStaleSessions(): array
    {
        $closed = [];

        // Sessions ouvertes depuis plus de 12h sans activité
        $staleSessions = DB::table('pos_sessions')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'open')
            ->where('last_activity_at', '<', now()->subHours(12))
            ->get();

        foreach ($staleSessions as $session) {
            try {
                DB::table('pos_sessions')
                    ->where('id', $session->id)
                    ->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                        'notes' => ($session->notes ?? '') . "\n[AUTO] Clôturée automatiquement (inactivité)",
                    ]);
                $closed[] = $session->id;
            } catch (\Exception $e) {
                Log::error("Auto-close session failed for {$session->id}: " . $e->getMessage());
            }
        }

        return $closed;
    }

    /**
     * Calculer le résumé de caisse du jour
     */
    public function getDailyCashSummary($posId = null): array
    {
        $query = PosOrder::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', today())
            ->where('status', 'completed');

        if ($posId) {
            $query->where('pos_id', $posId);
        }

        $orders = $query->get();

        return [
            'date' => today()->toDateString(),
            'total_sales' => $orders->sum('total'),
            'total_transactions' => $orders->count(),
            'average_basket' => $orders->count() > 0 ? $orders->sum('total') / $orders->count() : 0,
            'by_payment_method' => $orders->groupBy('payment_method')->map(fn($g) => [
                'count' => $g->count(),
                'total' => $g->sum('total'),
            ]),
        ];
    }

    // =====================================================
    // EXÉCUTION GLOBALE
    // =====================================================

    /**
     * Exécuter toutes les automatisations
     */
    public function runAllAutomations(): array
    {
        return [
            'urgent_requests_approved' => $this->autoApproveUrgentRequests(),
            'transfers_created' => $this->autoCreateTransfersFromApprovedRequests(),
            'low_stock_alerts' => $this->checkLowStockAlerts(),
            'sales_entries_generated' => $this->autoGenerateSalesEntries(),
            'purchase_entries_generated' => $this->autoGeneratePurchaseEntries(),
            'stale_sessions_closed' => $this->autoCloseStaleSessions(),
            'executed_at' => now()->toISOString(),
        ];
    }
}
