<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccounts;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountsController extends Controller
{
    private ChartOfAccountsService $chartService;

    public function __construct()
    {
        $this->chartService = new ChartOfAccountsService();
    }

    /**
     * Initialiser le plan comptable pour un tenant
     * 
     * POST /chart-of-accounts/initialize
     */
    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_type' => 'required|in:' . implode(',', array_keys(ChartOfAccountsService::BUSINESS_TYPES)),
        ]);

        try {
            $tenant = auth()->user()->tenant;

            // Vérifier si le plan existe déjà
            if (ChartOfAccounts::where('tenant_id', $tenant->id)->exists()) {
                return response()->json([
                    'message' => 'Plan comptable existe déjà',
                    'accounts_count' => ChartOfAccounts::where('tenant_id', $tenant->id)->count(),
                ], 400);
            }

            $this->chartService->createChartOfAccounts($tenant, $validated['business_type']);

            return response()->json([
                'message' => 'Plan comptable créé avec succès',
                'business_type' => $validated['business_type'],
                'accounts_count' => ChartOfAccounts::where('tenant_id', $tenant->id)->count(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Liste tous les comptes du plan comptable
     * 
     * GET /chart-of-accounts
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ChartOfAccounts::where('tenant_id', $tenantId);

        // Filtrer par type
        if ($request->has('account_type')) {
            $query->where('account_type', $request->query('account_type'));
        }

        // Filtrer par catégorie
        if ($request->has('category')) {
            $query->where('category', $request->query('category'));
        }

        // Filtrer actifs/inactifs
        if ($request->has('is_active')) {
            $active = $request->query('is_active') === 'true';
            $query->where('is_active', $active);
        }

        $accounts = $query->orderBy('order')->paginate(50);

        return response()->json($accounts);
    }

    /**
     * Obtenir un compte spécifique
     * 
     * GET /chart-of-accounts/{id}
     */
    public function show($id): JsonResponse
    {
        $account = ChartOfAccounts::find($id);

        if (!$account || $account->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['error' => 'Non trouvé'], 404);
        }

        return response()->json($account);
    }

    /**
     * Mettre à jour un compte
     * 
     * PUT /chart-of-accounts/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $account = ChartOfAccounts::find($id);

        if (!$account || $account->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['error' => 'Non trouvé'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    /**
     * Obtenir les comptes par type
     * 
     * GET /chart-of-accounts/by-type/{type}
     */
    public function getByType($type): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $accounts = ChartOfAccounts::where('tenant_id', $tenantId)
            ->where('account_type', $type)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Obtenir les comptes par sous-type (pour auto-mapping)
     * 
     * GET /chart-of-accounts/by-subtype/{subtype}
     */
    public function getBySubType($subType): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $account = ChartOfAccounts::where('tenant_id', $tenantId)
            ->where('sub_type', $subType)
            ->where('is_active', true)
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Compte non trouvé'], 404);
        }

        return response()->json($account);
    }

    /**
     * Obtenir un résumé du plan comptable
     * 
     * GET /chart-of-accounts/summary
     */
    public function summary(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $summary = [
            'total_accounts' => ChartOfAccounts::where('tenant_id', $tenantId)->count(),
            'active_accounts' => ChartOfAccounts::where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'by_type' => [
                'assets' => ChartOfAccounts::where('tenant_id', $tenantId)
                    ->where('account_type', 'asset')
                    ->where('is_active', true)
                    ->count(),
                'liabilities' => ChartOfAccounts::where('tenant_id', $tenantId)
                    ->where('account_type', 'liability')
                    ->where('is_active', true)
                    ->count(),
                'equity' => ChartOfAccounts::where('tenant_id', $tenantId)
                    ->where('account_type', 'equity')
                    ->where('is_active', true)
                    ->count(),
                'revenue' => ChartOfAccounts::where('tenant_id', $tenantId)
                    ->where('account_type', 'revenue')
                    ->where('is_active', true)
                    ->count(),
                'expense' => ChartOfAccounts::where('tenant_id', $tenantId)
                    ->where('account_type', 'expense')
                    ->where('is_active', true)
                    ->count(),
            ],
        ];

        return response()->json($summary);
    }

    /**
     * Obtenir les types de business supportés
     * 
     * GET /chart-of-accounts/business-types
     */
    public function getBusinessTypes(): JsonResponse
    {
        return response()->json(ChartOfAccountsService::BUSINESS_TYPES);
    }
}
