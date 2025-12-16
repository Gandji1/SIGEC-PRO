<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $request->header('X-Tenant-ID') ?? $user?->tenant_id;
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé', 'data' => []], 400);
        }
        
        $perPage = min($request->query('per_page', 50), 200);
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $page = $request->query('page', 1);
        
        // Cache pour les requêtes sans recherche
        $cacheKey = "suppliers_{$tenantId}_{$status}_{$perPage}_{$page}";
        
        if (empty($search)) {
            $suppliers = Cache::remember($cacheKey, 300, function () use ($tenantId, $status, $perPage) {
                $query = Supplier::where('tenant_id', $tenantId)
                    ->select(['id', 'name', 'email', 'phone', 'contact_person', 'status', 'created_at']);
                
                if ($status) {
                    $query->where('status', $status);
                }
                
                return $query->orderBy('name')->paginate($perPage);
            });
        } else {
            $query = Supplier::where('tenant_id', $tenantId)
                ->select(['id', 'name', 'email', 'phone', 'contact_person', 'status', 'created_at']);
            
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%$search%")
                  ->orWhere('email', 'ilike', "%$search%")
                  ->orWhere('phone', 'ilike', "%$search%");
            });
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $suppliers = $query->orderBy('name')->paginate($perPage);
        }

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        $tenantId = $request->header('X-Tenant-ID') ?? $user?->tenant_id;
        
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
        
        // Invalider le cache des fournisseurs
        Cache::forget("suppliers_{$tenantId}__50_1");

        return response()->json($supplier, 201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return response()->json(
            $supplier->load(['purchases', 'payments'])
        );
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);
        
        $user = auth()->guard('sanctum')->user();
        $tenantId = $request->header('X-Tenant-ID') ?? $user?->tenant_id;

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
        
        // Invalider le cache
        Cache::forget("suppliers_{$tenantId}__50_1");

        return response()->json($supplier);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchases()->exists()) {
            return response()->json(
                ['error' => 'Cannot delete supplier with existing purchases'],
                422
            );
        }
        
        $tenantId = $supplier->tenant_id;
        $supplier->delete();
        
        // Invalider le cache
        Cache::forget("suppliers_{$tenantId}__50_1");

        return response()->json(['message' => 'Supplier deleted']);
    }

    public function statistics(Request $request, Supplier $supplier): JsonResponse
    {
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
