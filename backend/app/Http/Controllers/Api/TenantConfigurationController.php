<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Pos;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TenantConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Obtenir la configuration complète du tenant
     */
    public function show(): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            $tenant = Tenant::with(['warehouses', 'users', 'pos', 'paymentMethods'])->findOrFail($tenantId);

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => $tenant,
                    'warehouses' => $tenant->warehouses,
                    'users' => $tenant->users,
                    'pos_locations' => $tenant->pos,
                    'payment_methods' => $tenant->paymentMethods,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour la configuration du tenant
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            $tenant = Tenant::findOrFail($tenantId);

            $validated = $request->validate([
                // Infos générales
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:500',
                'country' => 'nullable|string|max:100',
                'currency' => 'nullable|string|max:10',
                'business_type' => 'nullable|in:retail,restaurant,bar,hotel,other',
                'mode_pos' => 'nullable|in:A,B',
                'pos_option' => 'nullable|in:A,B',
                // Finances
                'tva_rate' => 'nullable|numeric|min:0|max:100',
                'default_markup' => 'nullable|numeric|min:0|max:1000',
                'stock_policy' => 'nullable|in:fifo,lifo,cmp',
                'allow_credit' => 'nullable|boolean',
                'credit_limit' => 'nullable|numeric|min:0',
                // Paiements
                'kkiapay_enabled' => 'nullable|boolean',
                'fedapay_enabled' => 'nullable|boolean',
                // Comptabilité
                'accounting_enabled' => 'nullable|boolean',
            ]);

            // Filtrer les valeurs null
            $dataToUpdate = array_filter($validated, fn($v) => $v !== null);
            $tenant->update($dataToUpdate);

            return response()->json([
                'success' => true,
                'message' => 'Configuration mise à jour',
                'data' => $tenant,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Configurer un moyen de paiement
     */
    public function configurePaymentMethod(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            $validated = $request->validate([
                'type' => 'required|in:especes,kkiapay,fedapay,virement,cheque,credit_card,other',
                'name' => 'required|string',
                'is_active' => 'nullable|boolean',
                'api_key' => 'nullable|string',
                'api_secret' => 'nullable|string',
                'settings' => 'nullable|array',
            ]);

            $paymentMethod = PaymentMethod::updateOrCreate(
                ['tenant_id' => $tenantId, 'type' => $validated['type']],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Moyen de paiement configuré',
                'data' => $paymentMethod,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lister les moyens de paiement
     */
    public function paymentMethods(): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            $methods = PaymentMethod::where('tenant_id', $tenantId)->get();

            return response()->json([
                'success' => true,
                'data' => $methods,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer/modifier un POS
     */
    public function createPos(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            $validated = $request->validate([
                'name' => 'required|string',
                'code' => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::unique('pos', 'code')->where('tenant_id', $tenantId),
                ],
                'location' => 'nullable|string',
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'responsible_user_id' => 'nullable|exists:users,id',
                'settings' => 'nullable|array',
            ]);

            // Si pas de warehouse, utiliser le POS warehouse du tenant
            if (empty($validated['warehouse_id'])) {
                $warehouse = \App\Models\Warehouse::where('tenant_id', $tenantId)
                    ->where('type', 'pos')
                    ->first();
                
                if (!$warehouse) {
                    // Si pas de warehouse POS, utiliser la première warehouse du tenant
                    $warehouse = \App\Models\Warehouse::where('tenant_id', $tenantId)
                        ->first();
                }
                
                if (!$warehouse) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aucun entrepôt trouvé pour ce tenant',
                    ], 400);
                }
                
                $validated['warehouse_id'] = $warehouse->id;
            }

            $pos = Pos::create([
                'tenant_id' => $tenantId,
                ...$validated,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'POS créé',
                'data' => $pos,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lister les POS du tenant
     */
    public function posList(): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            $posList = Pos::where('tenant_id', $tenantId)
                ->with(['warehouse', 'responsibleUser'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $posList,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
