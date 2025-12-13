<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Domains\Purchases\Services\PurchaseService;
use App\Services\SupplierNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    protected PurchaseService $purchaseService;
    protected SupplierNotificationService $notificationService;

    public function __construct(PurchaseService $purchaseService, SupplierNotificationService $notificationService)
    {
        $this->purchaseService = $purchaseService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $purchases = Purchase::where('tenant_id', $tenantId)
            ->with(['user', 'items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($purchases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'supplier_email' => 'nullable|email',
            'supplier_phone' => 'nullable|string',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $purchase = $this->purchaseService->createPurchase($validated);

            foreach ($validated['items'] as $item) {
                $this->purchaseService->addItem(
                    $purchase->id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price']
                );
            }

            DB::commit();

            return response()->json(
                $purchase->fresh()->load(['items', 'items.product']),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        return response()->json(
            $purchase->load(['items', 'items.product', 'user'])
        );
    }

    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        if (!in_array($purchase->status, ['pending', 'confirmed'])) {
            return response()->json(
                ['error' => 'Cannot update purchase in ' . $purchase->status . ' status'],
                422
            );
        }

        $validated = $request->validate([
            'supplier_name' => 'sometimes|string|max:255',
            'supplier_email' => 'sometimes|email',
            'supplier_phone' => 'sometimes|string',
            'expected_delivery' => 'sometimes|date',
            'notes' => 'sometimes|string',
        ]);

        $purchase->update($validated);

        return response()->json($purchase);
    }

    public function destroy(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);

        if ($purchase->status !== 'pending') {
            return response()->json(
                ['error' => 'Cannot delete purchase in ' . $purchase->status . ' status'],
                422
            );
        }

        $purchase->delete();

        return response()->json(['message' => 'Purchase deleted'], 200);
    }

    public function addItem(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== 'pending') {
            return response()->json(
                ['error' => 'Cannot add items to purchase in ' . $purchase->status . ' status'],
                422
            );
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1',
            'unit_price' => 'required|numeric|min:0.01',
        ]);

        try {
            $item = $this->purchaseService->addItem(
                $purchase->id,
                $validated['product_id'],
                $validated['quantity'],
                $validated['unit_price']
            );

            return response()->json($item->load('product'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function removeItem(Request $request, Purchase $purchase, PurchaseItem $item): JsonResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== 'pending') {
            return response()->json(
                ['error' => 'Cannot remove items from purchase in ' . $purchase->status . ' status'],
                422
            );
        }

        $item->delete();

        return response()->json(['message' => 'Item removed'], 200);
    }

    /**
     * Gérant soumet la commande pour approbation par le Tenant
     */
    public function submitForApproval(Request $request, Purchase $purchase): JsonResponse
    {
        $user = auth()->user();
        
        // Seul le gérant peut soumettre
        if (!in_array($user->role, ['manager', 'super_admin'])) {
            return response()->json(['error' => 'Seul le gérant peut soumettre une commande'], 403);
        }

        if ($purchase->status !== 'draft' && $purchase->status !== 'pending') {
            return response()->json(
                ['error' => 'Seules les commandes en brouillon peuvent être soumises'],
                422
            );
        }

        $purchase->update([
            'status' => 'pending_approval',
            'created_by_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Commande soumise pour approbation par le propriétaire',
            'data' => $purchase->fresh()->load(['items', 'items.product'])
        ], 200);
    }

    /**
     * Tenant approuve la commande (envoi au fournisseur)
     */
    public function approveByTenant(Request $request, Purchase $purchase): JsonResponse
    {
        $user = auth()->user();
        
        // Seul le tenant/owner peut approuver
        if (!in_array($user->role, ['owner', 'admin', 'super_admin'])) {
            return response()->json(['error' => 'Seul le propriétaire peut approuver une commande'], 403);
        }

        if ($purchase->status !== 'pending_approval') {
            return response()->json(
                ['error' => 'Cette commande n\'est pas en attente d\'approbation'],
                422
            );
        }

        $purchase->update([
            'status' => 'submitted',
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        // Notifier le fournisseur par email et notification in-app
        $this->notificationService->notifySupplierNewOrder($purchase->fresh()->load(['supplier', 'tenant', 'items']));

        return response()->json([
            'message' => 'Commande approuvée et envoyée au fournisseur',
            'data' => $purchase->fresh()->load(['items', 'items.product'])
        ], 200);
    }

    /**
     * Tenant rejette la commande
     */
    public function rejectByTenant(Request $request, Purchase $purchase): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['owner', 'admin', 'super_admin'])) {
            return response()->json(['error' => 'Seul le propriétaire peut rejeter une commande'], 403);
        }

        if ($purchase->status !== 'pending_approval') {
            return response()->json(
                ['error' => 'Cette commande n\'est pas en attente d\'approbation'],
                422
            );
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $purchase->update([
            'status' => 'draft',
            'notes' => ($purchase->notes ? $purchase->notes . "\n" : '') . 
                       "Rejeté par {$user->name}: " . ($validated['reason'] ?? 'Aucune raison'),
        ]);

        return response()->json([
            'message' => 'Commande rejetée et renvoyée au gérant',
            'data' => $purchase->fresh()->load(['items', 'items.product'])
        ], 200);
    }

    /**
     * Ancien confirm - gardé pour compatibilité mais redirige vers le nouveau flux
     */
    public function confirm(Request $request, Purchase $purchase): JsonResponse
    {
        // Si c'est une commande en attente d'approbation, utiliser le nouveau flux
        if ($purchase->status === 'pending_approval') {
            return $this->approveByTenant($request, $purchase);
        }

        $this->authorize('update', $purchase);

        if ($purchase->status !== 'pending' && $purchase->status !== 'draft') {
            return response()->json(
                ['error' => 'Can only confirm pending purchases'],
                422
            );
        }

        $this->purchaseService->confirmPurchase($purchase->id);

        return response()->json(
            $purchase->fresh()->load(['items', 'items.product']),
            200
        );
    }

    public function receive(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        // Seules les commandes livrées peuvent être réceptionnées
        if ($purchase->status !== 'delivered') {
            return response()->json(
                ['error' => 'Seules les commandes livrées peuvent être réceptionnées. Statut actuel: ' . $purchase->status],
                422
            );
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.purchase_item_id' => 'required|exists:purchase_items,id',
            'items.*.received_quantity' => 'required|numeric|min:1',
        ]);

        try {
            DB::beginTransaction();

            // Si items fournis, les traiter individuellement
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $purchaseItem = PurchaseItem::findOrFail($itemData['purchase_item_id']);
                    
                    if ($itemData['received_quantity'] > $purchaseItem->quantity_ordered) {
                        throw new \Exception('Received quantity exceeds ordered quantity');
                    }

                    $this->purchaseService->receiveItem(
                        $purchase->id,
                        $purchaseItem->id,
                        $itemData['received_quantity']
                    );
                }
            } else {
                // Réceptionner tous les items avec quantité commandée
                foreach ($purchase->items as $item) {
                    $item->quantity_received = $item->quantity_ordered;
                    $item->received_at = now();
                    $item->save();
                }
            }

            $this->purchaseService->receivePurchase($purchase->id);

            DB::commit();

            // Notifier le fournisseur que la commande a été réceptionnée
            $this->notificationService->notifySupplierOrderReceived($purchase->fresh()->load(['supplier', 'tenant']));

            return response()->json([
                'message' => 'Commande réceptionnée avec succès. Stock et CMP mis à jour.',
                'data' => $purchase->fresh()->load(['items', 'items.product'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        try {
            $this->purchaseService->cancelPurchase($purchase->id);
            return response()->json(
                $purchase->fresh()->load(['items', 'items.product']),
                200
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $report = $this->purchaseService->getPurchasesReport(
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json($report);
    }
}
