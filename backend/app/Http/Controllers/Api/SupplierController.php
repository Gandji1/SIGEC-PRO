<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        // PRIORITÉ: tenant_id de l'utilisateur authentifié (plus sécurisé que le header)
        $tenantId = $user?->tenant_id ?? $request->header('X-Tenant-ID');
        
        \Log::info('[SupplierController] index called', [
            'user_id' => $user?->id,
            'user_tenant_id' => $user?->tenant_id,
            'header_tenant_id' => $request->header('X-Tenant-ID'),
            'resolved_tenant_id' => $tenantId,
        ]);
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé', 'data' => []], 400);
        }
        
        $perPage = min($request->query('per_page', 50), 200);
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        // Désactiver le TenantScope global et filtrer manuellement pour éviter le double filtrage
        $query = Supplier::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'phone', 'contact_person', 'status', 'created_at', 'has_portal_access', 'portal_email', 'user_id', 'address', 'city', 'country', 'tax_id']);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $suppliers = $query->orderBy('name')->paginate($perPage);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        // PRIORITÉ: tenant_id de l'utilisateur authentifié
        $tenantId = $user?->tenant_id ?? $request->header('X-Tenant-ID');
        
        \Log::info('[SupplierController] store called', [
            'user_id' => $user?->id,
            'resolved_tenant_id' => $tenantId,
        ]);
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:suppliers,email,NULL,id,tenant_id,'.$tenantId,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'bank_details' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'tenant_id' => $tenantId,
            ...$validated,
            'status' => 'active',
        ]);

        return response()->json($supplier, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $user?->tenant_id;
        
        $supplier = Supplier::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$supplier) {
            return response()->json(['error' => 'Fournisseur non trouvé'], 404);
        }
        
        $this->authorize('view', $supplier);

        return response()->json(
            $supplier->load(['purchases', 'payments'])
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $user?->tenant_id;
        
        $supplier = Supplier::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$supplier) {
            return response()->json(['error' => 'Fournisseur non trouvé'], 404);
        }
        
        $this->authorize('update', $supplier);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:suppliers,email,'.$supplier->id.',id,tenant_id,'.$tenantId,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:255',
            'tax_id' => 'sometimes|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'bank_details' => 'sometimes|array',
            'notes' => 'sometimes|string',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $supplier->update($validated);

        return response()->json($supplier);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $user?->tenant_id;
        
        $supplier = Supplier::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$supplier) {
            return response()->json(['error' => 'Fournisseur non trouvé'], 404);
        }
        
        $this->authorize('delete', $supplier);

        if ($supplier->purchases()->exists()) {
            return response()->json(
                ['error' => 'Impossible de supprimer un fournisseur avec des commandes existantes'],
                422
            );
        }
        
        $supplier->delete();

        return response()->json(['message' => 'Fournisseur supprimé']);
    }

    public function statistics(Request $request, int $id): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $user?->tenant_id;
        
        $supplier = Supplier::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$supplier) {
            return response()->json(['error' => 'Fournisseur non trouvé'], 404);
        }
        
        $this->authorize('view', $supplier);

        $totalPurchases = $supplier->purchases()
            ->where('status', 'received')
            ->sum('total');

        $totalPaid = $supplier->payments()
            ->sum('amount');

        $lastPurchase = $supplier->purchases()
            ->where('status', 'received')
            ->latest('received_date')
            ->first();

        return response()->json([
            'total_purchases' => $totalPurchases,
            'total_paid' => $totalPaid,
            'outstanding_balance' => $totalPurchases - $totalPaid,
            'purchases_count' => $supplier->purchases()->count(),
            'last_purchase_date' => $lastPurchase?->received_date,
            'average_order_value' => $supplier->purchases()->count() > 0 
                ? $totalPurchases / $supplier->purchases()->count() 
                : 0,
        ]);
    }
}
