<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID') ?? auth()->guard('sanctum')->user()?->tenant_id;
        $perPage = min($request->query('per_page', 50), 200);
        
        $query = Customer::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'phone', 'category', 'credit_limit', 'created_at']);

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->query('category'));
        }

        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email,NULL,id,tenant_id,'.$request->header('X-Tenant-ID'),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'category' => 'required|string|in:retail,wholesale,distributor,other',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'tenant_id' => $request->header('X-Tenant-ID'),
            ...$validated,
        ]);

        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(
            $customer->load(['sales', 'payments'])
        );
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,'.$customer->id.',id,tenant_id,'.$request->header('X-Tenant-ID'),
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:255',
            'tax_id' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|in:retail,wholesale,distributor,other',
            'credit_limit' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|string',
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->sales()->exists()) {
            return response()->json(
                ['error' => 'Cannot delete customer with existing sales'],
                422
            );
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    public function statistics(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $totalSales = $customer->sales()
            ->where('status', 'completed')
            ->sum('total');

        $totalPayments = $customer->payments()
            ->sum('amount');

        $lastSale = $customer->sales()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        return response()->json([
            'total_sales' => $totalSales,
            'total_payments' => $totalPayments,
            'outstanding_balance' => $totalSales - $totalPayments,
            'sales_count' => $customer->sales()->count(),
            'last_sale_date' => $lastSale?->completed_at,
            'average_order_value' => $customer->sales()->count() > 0 
                ? $totalSales / $customer->sales()->count() 
                : 0,
        ]);
    }
}
