<?php

namespace App\Http\Controllers\Api;

use App\Models\PosTable;
use App\Models\PosOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class POSController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ========================================
    // TABLES
    // ========================================

    /**
     * Liste des tables
     */
    public function tables(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $tables = PosTable::where('tenant_id', $tenantId)
            ->orderBy('number')
            ->get();

        return response()->json(['data' => $tables]);
    }

    /**
     * Créer une table
     */
    public function createTable(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'number' => 'required|string',
            'capacity' => 'nullable|integer|min:1',
            'zone' => 'nullable|string',
        ]);

        // Vérifier unicité
        $exists = PosTable::where('tenant_id', $tenantId)
            ->where('number', $validated['number'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ce numéro de table existe déjà'], 422);
        }

        $table = PosTable::create([
            'tenant_id' => $tenantId,
            'number' => $validated['number'],
            'capacity' => $validated['capacity'] ?? 4,
            'zone' => $validated['zone'] ?? null,
            'status' => 'available',
        ]);

        return response()->json(['data' => $table], 201);
    }

    /**
     * Modifier une table
     */
    public function updateTable(Request $request, $id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $table = PosTable::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'number' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'zone' => 'nullable|string',
            'status' => 'nullable|in:available,occupied,reserved,cleaning',
        ]);

        $table->update($validated);

        return response()->json(['data' => $table]);
    }

    /**
     * Supprimer une table
     */
    public function deleteTable($id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $table = PosTable::where('tenant_id', $tenantId)->findOrFail($id);

        if ($table->status === 'occupied') {
            return response()->json(['message' => 'Impossible de supprimer une table occupée'], 422);
        }

        $table->delete();

        return response()->json(['message' => 'Table supprimée']);
    }

    // ========================================
    // KITCHEN
    // ========================================

    /**
     * Commandes pour la cuisine
     */
    public function kitchenOrders(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $status = $request->query('status', 'pending');

        $orders = PosOrder::where('tenant_id', $tenantId)
            ->whereIn('status', $status === 'all' ? ['pending', 'preparing', 'ready'] : [$status])
            ->with(['items.product'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateOrderStatus(Request $request, $orderId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $order = PosOrder::where('tenant_id', $tenantId)->findOrFail($orderId);

        $validated = $request->validate([
            'status' => 'required|in:pending,preparing,ready,served,completed,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        // Mettre à jour la table si nécessaire
        if ($order->table_number && in_array($validated['status'], ['completed', 'cancelled'])) {
            PosTable::where('tenant_id', $tenantId)
                ->where('number', $order->table_number)
                ->update(['status' => 'cleaning', 'current_order_id' => null]);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * Modifier un item de commande
     */
    public function updateOrderItem(Request $request, $orderId, $itemId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $order = PosOrder::where('tenant_id', $tenantId)->findOrFail($orderId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = $order->items()->findOrFail($itemId);
        $item->update(['quantity' => $validated['quantity']]);

        // Recalculer le total
        $order->recalculateTotal();

        return response()->json(['data' => $order->fresh(['items.product'])]);
    }

    /**
     * Supprimer un item de commande
     */
    public function deleteOrderItem($orderId, $itemId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $order = PosOrder::where('tenant_id', $tenantId)->findOrFail($orderId);

        $order->items()->where('id', $itemId)->delete();

        // Recalculer le total
        $order->recalculateTotal();

        return response()->json(['data' => $order->fresh(['items.product'])]);
    }

    /**
     * Annuler une commande
     */
    public function cancelOrder($orderId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $order = PosOrder::where('tenant_id', $tenantId)->findOrFail($orderId);

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Cette commande ne peut pas être annulée'], 422);
        }

        $order->update(['status' => 'cancelled']);

        // Libérer la table
        if ($order->table_number) {
            PosTable::where('tenant_id', $tenantId)
                ->where('number', $order->table_number)
                ->update(['status' => 'available', 'current_order_id' => null]);
        }

        return response()->json(['data' => $order]);
    }
}
