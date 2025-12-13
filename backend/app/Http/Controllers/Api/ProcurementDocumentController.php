<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcurementDocument;
use Illuminate\Http\Request;

class ProcurementDocumentController extends Controller
{
    public function index()
    {
        $tenantId = request()->header('X-Tenant-ID');
        return ProcurementDocument::where('tenant_id', $tenantId)
            ->with('purchase', 'supplier', 'issuedBy', 'approvedBy', 'receivedBy')
            ->orderByDesc('issued_at')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
        
        $validated = $request->validate([
            'document_number' => [
                'required',
                \Illuminate\Validation\Rule::unique('procurement_documents')->where('tenant_id', $tenantId),
            ],
            'type' => 'required|in:purchase_order,quotation,invoice,receipt',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'in:draft,issued,approved,received,cancelled',
            'total_amount' => 'numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $validated['tenant_id'] = $tenantId;
        $validated['issued_at'] = $validated['issued_at'] ?? now();
        $validated['issued_by'] = auth()->id();

        $doc = ProcurementDocument::create($validated);
        return response()->json($doc, 201);
    }

    public function show(ProcurementDocument $procurementDocument)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($procurementDocument->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        return $procurementDocument->load('purchase', 'supplier', 'issuedBy', 'approvedBy', 'receivedBy');
    }

    public function update(Request $request, ProcurementDocument $procurementDocument)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($procurementDocument->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'in:draft,issued,approved,received,cancelled',
            'received_at' => 'nullable|datetime',
            'received_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'total_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $procurementDocument->update($validated);
        return response()->json($procurementDocument);
    }

    public function destroy(ProcurementDocument $procurementDocument)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($procurementDocument->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $procurementDocument->delete();
        return response()->json(null, 204);
    }

    public function approve(Request $request, ProcurementDocument $procurementDocument)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($procurementDocument->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $procurementDocument->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        return response()->json($procurementDocument);
    }

    public function receive(Request $request, ProcurementDocument $procurementDocument)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($procurementDocument->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $procurementDocument->update([
            'status' => 'received',
            'received_at' => now(),
            'received_by' => auth()->id(),
        ]);

        return response()->json($procurementDocument);
    }
}
