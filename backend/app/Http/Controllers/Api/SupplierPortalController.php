<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use App\Models\User;
use App\Models\Purchase;
use App\Services\SupplierNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Contrôleur pour le portail fournisseur
 * Permet aux fournisseurs de voir et gérer leurs commandes
 */
class SupplierPortalController extends Controller
{
    protected SupplierNotificationService $notificationService;

    public function __construct(SupplierNotificationService $notificationService)
    {
        $this->middleware('auth:sanctum');
        $this->notificationService = $notificationService;
    }

    /**
     * Activer l'accès portail pour un fournisseur
     * Crée un compte utilisateur lié au fournisseur
     */
    public function enablePortalAccess(Request $request, $supplierId): JsonResponse
    {
        $user = auth()->user();
        
        // Seul le owner/manager peut activer le portail
        if (!$user->isManagement()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $supplier = Supplier::where('tenant_id', $user->tenant_id)->findOrFail($supplierId);

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        try {
            DB::beginTransaction();

            // Utiliser le mot de passe fourni
            $password = $validated['password'];
            
            $supplierUser = User::create([
                'tenant_id' => $user->tenant_id,
                'name' => $supplier->name,
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'role' => 'supplier',
                'status' => 'active',
            ]);

            // Lier le fournisseur au compte utilisateur
            $supplier->update([
                'user_id' => $supplierUser->id,
                'has_portal_access' => true,
                'portal_email' => $validated['email'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Accès portail activé',
                'data' => [
                    'supplier' => $supplier->fresh(),
                    'credentials' => [
                        'email' => $validated['email'],
                        'password' => $password, // À envoyer par email en production
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Désactiver l'accès portail
     */
    public function disablePortalAccess($supplierId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->isManagement()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $supplier = Supplier::where('tenant_id', $user->tenant_id)->findOrFail($supplierId);

        if ($supplier->user_id) {
            User::where('id', $supplier->user_id)->update(['status' => 'inactive']);
        }

        $supplier->update([
            'has_portal_access' => false,
        ]);

        return response()->json(['message' => 'Accès portail désactivé']);
    }

    /**
     * Dashboard fournisseur - Voir ses commandes
     */
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        
        // Vérifier que c'est bien un fournisseur
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        // Trouver le fournisseur lié par user_id (priorité absolue)
        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)
            ->first();
        
        // Fallback: chercher par email dans le tenant de l'utilisateur
        if (!$supplier && $user->tenant_id) {
            $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('tenant_id', $user->tenant_id)
                ->where(function($q) use ($user) {
                    $q->where('portal_email', $user->email)
                      ->orWhere('email', $user->email);
                })
                ->first();
            
            // Lier le fournisseur à l'utilisateur si trouvé
            if ($supplier) {
                $supplier->update(['user_id' => $user->id]);
            }
        }
        
        if (!$supplier) {
            // Créer un fournisseur temporaire pour l'affichage
            return response()->json([
                'data' => [
                    'supplier' => [
                        'id' => null,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'orders' => [],
                    'stats' => [
                        'total_orders' => 0,
                        'pending_orders' => 0,
                        'confirmed_orders' => 0,
                        'delivered_orders' => 0,
                        'total_value' => 0,
                    ],
                    'message' => 'Aucune commande pour le moment. Votre compte fournisseur sera lié automatiquement lors de la prochaine commande.'
                ]
            ]);
        }

        // Récupérer les commandes (achats) du fournisseur
        // IMPORTANT: Ne montrer que les commandes approuvées par le tenant (submitted ou après)
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->whereIn('status', [
                Purchase::STATUS_SUBMITTED,    // Approuvée par tenant, envoyée au fournisseur
                Purchase::STATUS_CONFIRMED,    // Confirmée par fournisseur
                Purchase::STATUS_SHIPPED,      // Expédiée
                Purchase::STATUS_DELIVERED,    // Livrée
                Purchase::STATUS_RECEIVED,     // Réceptionnée par gérant
                Purchase::STATUS_PAID,         // Payée
            ])
            ->with(['items.product', 'warehouse', 'tenant:id,name', 'createdBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Stats
        $stats = [
            'total_orders' => $purchases->count(),
            'pending_orders' => $purchases->whereIn('status', ['pending', 'submitted'])->count(),
            'confirmed_orders' => $purchases->where('status', 'confirmed')->count(),
            'shipped_orders' => $purchases->where('status', 'shipped')->count(),
            'delivered_orders' => $purchases->where('status', 'delivered')->count(),
            'received_orders' => $purchases->where('status', 'received')->count(),
            'paid_orders' => $purchases->where('status', 'paid')->count(),
            'total_value' => $purchases->sum('total'),
            'pending_payment' => $purchases->whereIn('status', ['delivered', 'received'])->sum('total'),
        ];

        return response()->json([
            'data' => [
                'supplier' => $supplier,
                'orders' => $purchases,
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Liste des commandes pour le fournisseur
     */
    public function orders(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        if (!$supplier) {
            return response()->json(['message' => 'Fournisseur non trouvé'], 404);
        }

        // IMPORTANT: Ne montrer que les commandes approuvées par le tenant
        $query = Purchase::where('supplier_id', $supplier->id)
            ->whereIn('status', [
                Purchase::STATUS_SUBMITTED,
                Purchase::STATUS_CONFIRMED,
                Purchase::STATUS_SHIPPED,
                Purchase::STATUS_DELIVERED,
                Purchase::STATUS_RECEIVED,
                Purchase::STATUS_PAID,
                Purchase::STATUS_CANCELLED,
            ])
            ->with(['items.product', 'warehouse', 'createdBy', 'tenant:id,name']);

        // Filtres additionnels
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($orders);
    }

    /**
     * Détail d'une commande
     */
    public function orderDetail($id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        // Vérifier que la commande est visible pour le fournisseur (approuvée par tenant)
        $order = Purchase::where('supplier_id', $supplier->id)
            ->whereIn('status', [
                Purchase::STATUS_SUBMITTED,
                Purchase::STATUS_CONFIRMED,
                Purchase::STATUS_SHIPPED,
                Purchase::STATUS_DELIVERED,
                Purchase::STATUS_RECEIVED,
                Purchase::STATUS_PAID,
                Purchase::STATUS_CANCELLED,
            ])
            ->with(['items.product', 'warehouse', 'createdBy', 'tenant:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * Confirmer une commande (fournisseur accepte)
     */
    public function confirmOrder(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        $order = Purchase::where('supplier_id', $supplier->id)->findOrFail($id);

        // Le fournisseur ne peut confirmer que les commandes soumises (approuvées par tenant)
        if ($order->status !== Purchase::STATUS_SUBMITTED) {
            return response()->json(['message' => 'Seules les commandes soumises peuvent être confirmées. Statut actuel: ' . $order->status], 422);
        }

        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $order->update([
            'status' => 'confirmed',
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
            'supplier_notes' => $validated['notes'] ?? null,
            'confirmed_at' => now(),
        ]);

        // Notifier le tenant/gérant que la commande est confirmée
        $this->notificationService->notifyTenantOrderConfirmed($order->fresh()->load(['supplier', 'tenant', 'createdBy', 'user']));

        return response()->json([
            'message' => 'Commande confirmée',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Marquer comme expédiée (préparation)
     */
    public function markShipped(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        $order = Purchase::where('supplier_id', $supplier->id)->findOrFail($id);

        if ($order->status !== 'confirmed') {
            return response()->json(['message' => 'La commande doit être confirmée avant expédition'], 422);
        }

        $validated = $request->validate([
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $order->update([
            'status' => 'shipped',
            'tracking_number' => $validated['tracking_number'] ?? null,
            'supplier_notes' => $validated['notes'] ?? $order->supplier_notes,
            'shipped_at' => now(),
        ]);

        // Notifier le tenant/gérant que la commande est expédiée
        $this->notificationService->notifyTenantOrderShipped($order->fresh()->load(['supplier', 'tenant', 'createdBy', 'user']));

        return response()->json([
            'message' => 'Commande marquée comme expédiée',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Livrer la commande - Déclenche notification au tenant
     */
    public function deliverOrder(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        $order = Purchase::where('supplier_id', $supplier->id)->findOrFail($id);

        if (!in_array($order->status, ['confirmed', 'shipped'])) {
            return response()->json(['message' => 'La commande doit être confirmée ou expédiée avant livraison'], 422);
        }

        $validated = $request->validate([
            'delivery_notes' => 'nullable|string|max:500',
            'delivery_proof' => 'nullable|string',
        ]);

        $order->update([
            'status' => 'delivered',
            'supplier_notes' => $validated['delivery_notes'] ?? $order->supplier_notes,
            'delivered_at' => now(),
            'delivery_proof' => $validated['delivery_proof'] ?? null,
        ]);

        // Notifier le tenant/gérant que la commande est livrée (notification in-app + email)
        $this->notificationService->notifyTenantOrderDelivered($order->fresh()->load(['supplier', 'tenant', 'createdBy', 'user']));

        return response()->json([
            'message' => 'Commande livrée - Le tenant a été notifié pour réception',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Valider le paiement reçu
     * RÈGLE MÉTIER: Seul le fournisseur peut valider qu'il a reçu le paiement
     */
    public function validatePayment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        if (!$supplier) {
            return response()->json(['message' => 'Fournisseur non trouvé'], 404);
        }
        
        $order = Purchase::where('supplier_id', $supplier->id)->findOrFail($id);

        // Le paiement peut être validé après livraison (avant ou après réception)
        if (!in_array($order->status, [Purchase::STATUS_DELIVERED, Purchase::STATUS_RECEIVED])) {
            return response()->json([
                'message' => 'La commande doit être livrée avant validation du paiement. Statut actuel: ' . $order->status
            ], 422);
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:500',
            'amount_received' => 'nullable|numeric|min:0',
        ]);

        // RÈGLE MÉTIER: Le fournisseur valide qu'il a reçu le paiement du gérant
        // Cela NE CHANGE PAS le statut - le gérant doit encore réceptionner
        // Le statut 'paid' n'est atteint qu'après réception par le gérant
        $updateData = [
            'payment_validated_by_supplier' => true,
            'payment_validated_at' => now(),
            'amount_paid' => $validated['amount_received'] ?? $order->total,
            'metadata' => array_merge($order->metadata ?? [], [
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_notes' => $validated['payment_notes'] ?? null,
            ]),
        ];
        
        // Si la commande est déjà réceptionnée, on peut passer à 'paid'
        if ($order->status === Purchase::STATUS_RECEIVED) {
            $updateData['status'] = Purchase::STATUS_PAID;
            $updateData['paid_at'] = now();
        }
        // Sinon, on garde le statut 'delivered' - le gérant doit réceptionner
        
        $order->update($updateData);

        // Notifier le tenant que le paiement a été validé par le fournisseur
        $this->notificationService->notifyTenantPaymentValidated($order->fresh()->load(['supplier', 'tenant']));

        return response()->json([
            'message' => 'Paiement validé avec succès',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Historique des commandes du fournisseur
     */
    public function history(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        if (!$supplier) {
            return response()->json(['data' => [], 'stats' => []]);
        }

        $query = Purchase::where('supplier_id', $supplier->id)
            ->with(['items.product', 'warehouse']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(50);

        // Stats globales
        $allOrders = Purchase::where('supplier_id', $supplier->id);
        $stats = [
            'total_orders' => $allOrders->count(),
            'total_revenue' => $allOrders->where('status', 'paid')->sum('total'),
            'pending_payment' => $allOrders->whereIn('status', ['delivered', 'received'])->sum('total'),
            'this_month' => Purchase::where('supplier_id', $supplier->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total'),
        ];

        return response()->json([
            'data' => $orders,
            'stats' => $stats
        ]);
    }

    /**
     * Rejeter une commande
     */
    public function rejectOrder(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'supplier') {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        $supplier = Supplier::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('user_id', $user->id)->first();
        
        $order = Purchase::where('supplier_id', $supplier->id)->findOrFail($id);

        if (!in_array($order->status, ['pending', 'submitted', 'confirmed'])) {
            return response()->json(['message' => 'Cette commande ne peut plus être rejetée'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order->update([
            'status' => 'cancelled',
            'supplier_notes' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Commande rejetée',
            'data' => $order->fresh()
        ]);
    }
}
