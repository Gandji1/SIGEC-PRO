<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function index()
    {
        $tenantId = request()->header('X-Tenant-ID');
        return DeliveryNote::where('tenant_id', $tenantId)
            ->with('sale', 'purchase', 'customer', 'supplier', 'issuedBy', 'deliveredBy')
            ->orderByDesc('issued_at')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
        
        $validated = $request->validate([
            'sale_id' => 'nullable|exists:sales,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'note_number' => [
                'required',
                \Illuminate\Validation\Rule::unique('delivery_notes')->where('tenant_id', $tenantId),
            ],
            'description' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'status' => 'in:draft,issued,delivered,cancelled',
            'total_amount' => 'numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $validated['tenant_id'] = $tenantId;
        $validated['issued_at'] = $validated['issued_at'] ?? now();
        $validated['issued_by'] = auth()->id();

        $note = DeliveryNote::create($validated);
        return response()->json($note, 201);
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($deliveryNote->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        return $deliveryNote->load('sale', 'purchase', 'customer', 'supplier', 'issuedBy', 'deliveredBy');
    }

    public function update(Request $request, DeliveryNote $deliveryNote)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($deliveryNote->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'in:draft,issued,delivered,cancelled',
            'delivered_at' => 'nullable|datetime',
            'delivered_by' => 'nullable|exists:users,id',
            'total_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $deliveryNote->update($validated);
        return response()->json($deliveryNote);
    }

    public function destroy(DeliveryNote $deliveryNote)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($deliveryNote->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $deliveryNote->delete();
        return response()->json(null, 204);
    }

    public function deliver(Request $request, DeliveryNote $deliveryNote)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($deliveryNote->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $deliveryNote->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'delivered_by' => auth()->id(),
        ]);

        return response()->json($deliveryNote);
    }
}
