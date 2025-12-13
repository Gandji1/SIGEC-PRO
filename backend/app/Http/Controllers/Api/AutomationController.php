<?php

namespace App\Http\Controllers\Api;

use App\Services\VirtualRoleAutomationService;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    protected $automationService;

    public function __construct()
    {
        $this->automationService = new VirtualRoleAutomationService();
    }

    /**
     * Exécuter toutes les automatisations
     */
    public function runAll(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $results = $this->automationService->runAllAutomations();

        return response()->json([
            'success' => true,
            'message' => 'Automatisations exécutées',
            'data' => $results,
        ]);
    }

    /**
     * Vérifier les alertes de stock bas
     */
    public function checkLowStock(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $alerts = $this->automationService->checkLowStockAlerts();

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'count' => count($alerts),
        ]);
    }

    /**
     * Auto-approuver les demandes urgentes
     */
    public function approveUrgentRequests(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $approved = $this->automationService->autoApproveUrgentRequests();

        return response()->json([
            'success' => true,
            'message' => count($approved) . ' demandes approuvées automatiquement',
            'data' => $approved,
        ]);
    }

    /**
     * Créer les transferts depuis les demandes approuvées
     */
    public function createTransfers(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $created = $this->automationService->autoCreateTransfersFromApprovedRequests();

        return response()->json([
            'success' => true,
            'message' => count($created) . ' transferts créés automatiquement',
            'data' => $created,
        ]);
    }

    /**
     * Générer les écritures comptables
     */
    public function generateAccountingEntries(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $salesEntries = $this->automationService->autoGenerateSalesEntries();
        $purchaseEntries = $this->automationService->autoGeneratePurchaseEntries();

        return response()->json([
            'success' => true,
            'message' => 'Écritures générées',
            'data' => [
                'sales_entries' => $salesEntries,
                'purchase_entries' => $purchaseEntries,
            ],
        ]);
    }

    /**
     * Obtenir le résumé de caisse du jour
     */
    public function dailyCashSummary(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $posId = $request->query('pos_id');
        
        $this->automationService->setTenantId($tenantId);
        $summary = $this->automationService->getDailyCashSummary($posId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Clôturer les sessions inactives
     */
    public function closeStaleSessions(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $this->automationService->setTenantId($tenantId);

        $closed = $this->automationService->autoCloseStaleSessions();

        return response()->json([
            'success' => true,
            'message' => count($closed) . ' sessions clôturées',
            'data' => $closed,
        ]);
    }
}
