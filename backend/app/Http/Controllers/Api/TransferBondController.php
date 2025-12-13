<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransferBond;
use Illuminate\Http\Request;

class TransferBondController extends Controller
{
    public function index()
    {
        $tenantId = request()->header('X-Tenant-ID');
        return TransferBond::where('tenant_id', $tenantId)
            ->with('transfer', 'issuedBy', 'receivedBy')
            ->orderByDesc('issued_at')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
        
        $validated = $request->validate([
            'transfer_id' => 'required|exists:transfers,id',
            'bond_number' => [
                'required',
                \Illuminate\Validation\Rule::unique('transfer_bonds')->where('tenant_id', $tenantId),
            ],
            'description' => 'nullable|string',
            'status' => 'in:draft,issued,received,cancelled',
            'notes' => 'nullable|string',
        ]);
        $validated['tenant_id'] = $tenantId;
        $validated['issued_at'] = $validated['issued_at'] ?? now();
        $validated['issued_by'] = auth()->id();

        $bond = TransferBond::create($validated);
        return response()->json($bond, 201);
    }

    public function show(TransferBond $transferBond)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($transferBond->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        return $transferBond->load('transfer', 'issuedBy', 'receivedBy');
    }

    public function update(Request $request, TransferBond $transferBond)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($transferBond->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'in:draft,issued,received,cancelled',
            'executed_at' => 'nullable|datetime',
            'received_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $transferBond->update($validated);
        return response()->json($transferBond);
    }

    public function destroy(TransferBond $transferBond)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($transferBond->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $transferBond->delete();
        return response()->json(null, 204);
    }

    public function execute(Request $request, TransferBond $transferBond)
    {
        $tenantId = request()->header('X-Tenant-ID');
        if ($transferBond->tenant_id != $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $transferBond->update([
            'status' => 'received',
            'executed_at' => now(),
            'received_by' => auth()->id(),
        ]);

        return response()->json($transferBond);
    }
}
