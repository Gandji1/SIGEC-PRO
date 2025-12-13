<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $query = Supplier::where('tenant_id', $tenantId);

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $suppliers = $query->orderBy('name')
            ->paginate($perPage);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:suppliers,email,NULL,id,tenant_id,'.$request->header('X-Tenant-ID'),
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
            'tenant_id' => $request->header('X-Tenant-ID'),
            ...$validated,
        ]);

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

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:suppliers,email,'.$supplier->id.',id,tenant_id,'.$request->header('X-Tenant-ID'),
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:255',
            'tax_id' => 'sometimes|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'bank_details' => 'sometimes|array',
            'notes' => 'sometimes|string',
        ]);

        $supplier->update($validated);

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

        $supplier->delete();

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
