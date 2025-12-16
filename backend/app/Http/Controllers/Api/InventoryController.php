<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Domains\Stocks\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InventoryController extends Controller
{
    use AuthorizesRequests;

    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Créer un nouvel inventaire
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);

        try {
            $inventory = $this->inventoryService->createInventory(
                $validated['warehouse_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Inventaire créé avec succès',
                'data' => $inventory,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir les détails d'un inventaire
     */
    public function show(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('view', $inventory);

            return response()->json([
                'success' => true,
                'data' => $inventory->load('items', 'warehouse', 'user'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Démarrer un inventaire
     */
    public function start(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('update', $inventory);

            if ($inventory->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul les inventaires en brouillon peuvent être démarrés',
                ], 422);
            }

            $inventory = $this->inventoryService->startInventory($inventory);

            return response()->json([
                'success' => true,
                'message' => 'Inventaire démarré',
                'data' => $inventory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ajouter un article à l'inventaire
     */
    public function addItem(Request $request, Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('update', $inventory);

            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'counted_qty' => 'required|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($inventory->status === 'draft') {
                $inventory = $this->inventoryService->startInventory($inventory);
            }

            $item = $this->inventoryService->addItem(
                $inventory,
                $validated['product_id'],
                $validated['counted_qty']
            );

            if (isset($validated['notes'])) {
                $item->notes = $validated['notes'];
                $item->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Article ajouté',
                'data' => $item->load('product'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Finaliser l'inventaire (créer les ajustements)
     */
    public function complete(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('update', $inventory);

            if ($inventory->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul les inventaires en cours peuvent être finalisés',
                ], 422);
            }

            $inventory = $this->inventoryService->completeInventory($inventory);

            return response()->json([
                'success' => true,
                'message' => 'Inventaire finalisé',
                'data' => $inventory->load('items'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Valider l'inventaire (avant transmission au comptable)
     */
    public function validateInventory(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('update', $inventory);

            if ($inventory->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul les inventaires complétés peuvent être validés',
                ], 422);
            }

            $inventory = $this->inventoryService->validateInventory($inventory);

            return response()->json([
                'success' => true,
                'message' => 'Inventaire validé',
                'data' => $inventory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir le résumé d'un inventaire avec écarts
     */
    public function summary(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('view', $inventory);

            $summary = $this->inventoryService->getInventorySummary($inventory);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Importer des items d'inventaire depuis CSV
     * Format: SKU,Quantité Comptée
     */
    public function importCSV(Request $request, Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('update', $inventory);

            $validated = $request->validate([
                'csv' => 'required|string',
            ]);

            $results = $this->inventoryService->importFromCSV(
                $inventory,
                $validated['csv']
            );

            return response()->json([
                'success' => true,
                'message' => "{$results['added']} articles importés",
                'data' => [
                    'added' => $results['added'],
                    'errors' => $results['errors'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exporter l'inventaire en CSV
     */
    public function exportCSV(Inventory $inventory)
    {
        try {
            $this->authorize('view', $inventory);

            $csv = $this->inventoryService->exportAsCSV($inventory);

            return response($csv, 200)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=inventory_{$inventory->reference}.csv");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Lister les inventaires (avec filtres)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            $query = Inventory::where('tenant_id', $tenantId);

            // Filtres
            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $inventories = $query->with('warehouse', 'user')
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $inventories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir les données d'inventaire enrichies pour un entrepôt
     * Inclut: SDU Théorique, Stock Physique, Écart, CMM, Min, Max, Point de commande
     */
    public function getEnrichedInventoryData(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'period' => 'nullable|in:day,week,month',
            ]);

            $warehouseId = $validated['warehouse_id'];
            $period = $validated['period'] ?? 'month';
            
            // Récupérer l'entrepôt pour connaître son type
            $warehouse = \App\Models\Warehouse::find($warehouseId);
            $warehouseType = $warehouse->type ?? 'detail';

            // Récupérer tous les stocks de l'entrepôt avec les produits (SEULEMENT du tenant)
            $stocks = \App\Models\Stock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->whereHas('product', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId); // Produits du tenant uniquement
                })
                ->with(['product' => function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->select('id', 'name', 'sku', 'min_stock', 'max_stock', 'unit', 'tenant_id');
                }])
                ->get();

            // Calculer la période de référence selon le type choisi
            if ($period === 'day') {
                $periodStart = now()->subDays(3);
                $periodCount = 3;
            } elseif ($period === 'week') {
                $periodStart = now()->subWeeks(3);
                $periodCount = 3;
            } else {
                $periodStart = now()->subMonths(3);
                $periodCount = 3;
            }
            
            // Pré-charger les données CMM pour éviter les requêtes N+1
            $productIds = $stocks->pluck('product_id')->toArray();
            
            // Calculer les ENTRÉES (réceptions fournisseur) par produit pour la période
            $entreesTotals = [];
            if (count($productIds) > 0) {
                // Entrées = Quantités reçues des achats fournisseur (status = received)
                $entreesTotals = \App\Models\PurchaseItem::whereIn('product_id', $productIds)
                    ->whereHas('purchase', function($q) use ($tenantId, $warehouseId, $periodStart) {
                        $q->where('tenant_id', $tenantId)
                          ->where('warehouse_id', $warehouseId)
                          ->where('status', 'received')
                          ->where('received_date', '>=', $periodStart);
                    })
                    ->selectRaw('product_id, SUM(COALESCE(quantity_received, quantity_ordered, 0)) as total')
                    ->groupBy('product_id')
                    ->pluck('total', 'product_id')
                    ->toArray();
            }
            
            // Calculer les SORTIES (ventes POS) par produit pour la période
            $sortiesTotals = [];
            if (count($productIds) > 0) {
                $sortiesTotals = \App\Models\PosOrderItem::whereIn('product_id', $productIds)
                    ->whereHas('posOrder', function($q) use ($tenantId, $periodStart) {
                        $q->where('tenant_id', $tenantId)
                          ->where('created_at', '>=', $periodStart)
                          ->where(function($q2) {
                              $q2->whereIn('status', ['completed', 'paid', 'served', 'validated'])
                                 ->orWhere('payment_status', 'confirmed');
                          });
                    })
                    ->selectRaw('product_id, SUM(COALESCE(quantity_ordered, quantity_served, 1)) as total')
                    ->groupBy('product_id')
                    ->pluck('total', 'product_id')
                    ->toArray();
            }
            
            // CMM pour GROS: demandes du Détail approuvées/servies
            $stockRequestTotals = [];
            if ($warehouseType === 'gros' && count($productIds) > 0) {
                $stockRequestTotals = \App\Models\StockRequestItem::whereIn('product_id', $productIds)
                    ->whereHas('stockRequest', function($q) use ($tenantId, $periodStart, $warehouseId) {
                        $q->where('tenant_id', $tenantId)
                          ->where('created_at', '>=', $periodStart)
                          ->where('from_warehouse_id', $warehouseId)
                          ->whereIn('status', ['approved', 'served', 'completed', 'delivered']);
                    })
                    ->selectRaw('product_id, SUM(quantity_requested) as total')
                    ->groupBy('product_id')
                    ->pluck('total', 'product_id')
                    ->toArray();
            }
            
            // CMM pour DÉTAIL: ventes POS validées par le gérant
            $posSalesTotals = [];
            if ($warehouseType !== 'gros' && count($productIds) > 0) {
                // Commandes POS validées - utiliser quantity_ordered ou quantity_served
                $posSalesTotals = \App\Models\PosOrderItem::whereIn('product_id', $productIds)
                    ->whereHas('posOrder', function($q) use ($tenantId, $periodStart) {
                        $q->where('tenant_id', $tenantId)
                          ->where('created_at', '>=', $periodStart)
                          ->where(function($q2) {
                              $q2->whereIn('status', ['completed', 'paid', 'served', 'validated'])
                                 ->orWhere('payment_status', 'confirmed');
                          });
                    })
                    ->selectRaw('product_id, SUM(COALESCE(quantity_ordered, quantity_served, 1)) as total')
                    ->groupBy('product_id')
                    ->pluck('total', 'product_id')
                    ->toArray();
            }
            
            $enrichedData = $stocks->map(function($stock) use ($periodCount, $warehouseType, $stockRequestTotals, $posSalesTotals, $entreesTotals, $sortiesTotals) {
                $product = $stock->product;
                if (!$product) return null;
                
                // Calculer CMM selon le type d'entrepôt
                $cmm = 0;
                
                if ($warehouseType === 'gros') {
                    // GROS: CMM = Moyenne des demandes du Détail approuvées/servies
                    $totalRequested = $stockRequestTotals[$product->id] ?? 0;
                    $cmm = round($totalRequested / $periodCount);
                } else {
                    // DÉTAIL: CMM = Moyenne des ventes POS validées par le gérant
                    $totalPOS = $posSalesTotals[$product->id] ?? 0;
                    $cmm = round($totalPOS / $periodCount);
                }

                // ENTRÉES = Quantités reçues des fournisseurs (période)
                $entrees = $entreesTotals[$product->id] ?? 0;
                
                // SORTIES = Quantités vendues (ventes POS validées)
                $sorties = $sortiesTotals[$product->id] ?? 0;
                
                // SDU Théorique actuel = quantité système
                $sduActuel = $stock->quantity ?? 0;
                
                // STOCK INITIAL = SDU Actuel - Entrées + Sorties
                // Formule inversée: SI + E - S = SDU => SI = SDU - E + S
                $stockInitial = max(0, $sduActuel - $entrees + $sorties);
                
                // SDU THÉORIQUE = Stock Initial + Entrées - Sorties
                $sduTheorique = $stockInitial + $entrees - $sorties;
                
                // Stock Physique = entré manuellement lors de l'inventaire
                $stockPhysique = $stock->stock_physique;
                
                // Paramètres de stock
                $minStock = $product->min_stock ?? 5;
                $maxStock = $product->max_stock ?? 100;

                // Écart = Stock Physique - SDU Théorique (si stock physique renseigné)
                $ecart = null;
                if ($stockPhysique !== null) {
                    $ecart = $stockPhysique - $sduTheorique;
                }

                // =====================================================
                // STATUT basé sur STOCK PHYSIQUE vs Min/Max
                // Si stock physique non renseigné, on utilise SDU théorique
                // =====================================================
                $stockPourStatut = $stockPhysique ?? $sduTheorique;
                $status = 'normal';
                if ($stockPourStatut <= 0) {
                    $status = 'rupture';
                } elseif ($stockPourStatut < $minStock) {
                    $status = 'sous-stocke';
                } elseif ($stockPourStatut > $maxStock) {
                    $status = 'sur-stocke';
                }

                // =====================================================
                // POINT DE COMMANDE:
                // Condition: Stock Physique < Stock Min
                // Formule: (CMM - Stock Physique) + 10% de CMM
                // =====================================================
                $needsReorder = false;
                $reorderPoint = 0;
                $quantityToOrder = 0;
                
                if ($stockPhysique !== null && $stockPhysique < $minStock && $cmm > 0) {
                    $needsReorder = true;
                    $quantityToOrder = round(($cmm - $stockPhysique) + ($cmm * 0.1));
                    $reorderPoint = $quantityToOrder;
                }

                // CMP et valeur
                $cmp = $stock->cost_average ?? $stock->unit_cost ?? $product->purchase_price ?? 0;
                $valeurStock = $sduTheorique * $cmp;

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?? 'unité',
                    'stock_initial' => $stockInitial,
                    'entrees' => $entrees,
                    'sorties' => $sorties,
                    'sdu_theorique' => $sduTheorique,
                    'stock_physique' => $stockPhysique,
                    'ecart' => $ecart,
                    'cmm' => $cmm,
                    'cmp' => $cmp,
                    'valeur_stock' => $valeurStock,
                    'min_stock' => $minStock,
                    'max_stock' => $maxStock,
                    'status' => $status,
                ];
            })->filter()->values();

            // Calculer la valeur totale du stock avec CMP
            $totalValue = $enrichedData->sum('valeur_stock');
            
            // Compter les produits en rupture ou sous-stockés
            $lowStockCount = $enrichedData->filter(fn($item) => in_array($item['status'], ['rupture', 'sous-stocke']))->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $enrichedData,
                    'summary' => [
                        'total_products' => $enrichedData->count(),
                        'low_stock_count' => $lowStockCount,
                        'total_value' => $totalValue,
                    ],
                    'warehouse' => $warehouse,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Enriched inventory error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sauvegarder les stocks physiques entrés lors de l'inventaire
     */
    public function savePhysicalCounts(Request $request): JsonResponse
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'counts' => 'required|array|min:1',
                'counts.*.product_id' => 'required|integer|exists:products,id',
                'counts.*.stock_physique' => 'required|integer|min:0',
            ]);

            $warehouseId = $validated['warehouse_id'];
            $savedCount = 0;
            $adjustments = [];

            foreach ($validated['counts'] as $count) {
                $stock = \App\Models\Stock::where('tenant_id', $tenantId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $count['product_id'])
                    ->first();

                if ($stock) {
                    $oldQuantity = $stock->quantity;
                    $newPhysique = $count['stock_physique'];
                    $ecart = $newPhysique - $oldQuantity;

                    // Mettre à jour le stock avec le stock physique et l'écart
                    $stock->update([
                        'stock_physique' => $newPhysique,
                        'ecart' => $ecart,
                        'last_inventory_at' => now(),
                        'last_inventory_by' => $user->id,
                    ]);

                    // Si écart, créer un mouvement d'ajustement
                    if ($ecart != 0) {
                        \App\Models\StockMovement::create([
                            'tenant_id' => $tenantId,
                            'warehouse_id' => $warehouseId,
                            'product_id' => $count['product_id'],
                            'type' => $ecart > 0 ? 'adjustment_in' : 'adjustment_out',
                            'quantity' => abs($ecart),
                            'reference' => 'INV-' . date('Ymd-His'),
                            'notes' => 'Ajustement inventaire: écart de ' . $ecart,
                            'created_by' => $user->id,
                        ]);

                        // Mettre à jour la quantité système
                        $stock->update(['quantity' => $newPhysique]);

                        $adjustments[] = [
                            'product_id' => $count['product_id'],
                            'old_quantity' => $oldQuantity,
                            'new_quantity' => $newPhysique,
                            'ecart' => $ecart,
                        ];
                    }

                    $savedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "$savedCount stock(s) physique(s) enregistré(s)",
                'data' => [
                    'saved_count' => $savedCount,
                    'adjustments' => $adjustments,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Save physical counts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Déterminer le statut du stock
     */
    private function getStockStatus(int $quantity, ?int $min, ?int $max, int $reorderPoint): string
    {
        if ($quantity <= 0) return 'rupture';
        if ($quantity <= $reorderPoint) return 'critique';
        if ($min && $quantity <= $min) return 'bas';
        if ($max && $quantity >= $max) return 'surplus';
        return 'normal';
    }

    /**
     * Exporter la fiche d'inventaire enrichi en CSV
     */
    public function exportEnrichedInventory(Request $request)
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'period' => 'nullable|in:day,week,month',
            ]);

            // Récupérer les données enrichies via la même logique
            $response = $this->getEnrichedInventoryData($request);
            $data = json_decode($response->getContent(), true);
            
            if (!$data['success'] || empty($data['data']['items'])) {
                return response()->json(['success' => false, 'message' => 'Aucune donnée à exporter'], 422);
            }

            $items = $data['data']['items'];
            $warehouse = $data['data']['warehouse'];
            $summary = $data['data']['summary'];
            
            // Générer le CSV avec BOM UTF-8
            $csv = "\xEF\xBB\xBF"; // BOM UTF-8
            
            // En-tête du document
            $csv .= "FICHE D'INVENTAIRE ENRICHI\n";
            $csv .= "Entrepôt:;" . ($warehouse['name'] ?? 'N/A') . "\n";
            $csv .= "Type:;" . ($warehouse['type'] ?? 'N/A') . "\n";
            $csv .= "Date d'export:;" . now()->format('d/m/Y H:i') . "\n";
            $csv .= "Période CMM:;" . ($validated['period'] ?? 'month') . "\n";
            $csv .= "\n";
            
            // Résumé
            $csv .= "RÉSUMÉ\n";
            $csv .= "Total Produits:;" . $summary['total_products'] . "\n";
            $csv .= "Stock Faible/Rupture:;" . ($summary['low_stock_count'] ?? 0) . "\n";
            $csv .= "Valeur Stock:;" . number_format($summary['total_value'], 0, ',', ' ') . " FCFA\n";
            $csv .= "\n";
            
            // En-têtes colonnes
            $csv .= "Produit;SKU;Unité;Stock Initial;Entrées;Sorties;SDU Théorique;Stock Physique;Écart;CMM;CMP;Valeur Stock;Min;Max;Statut\n";
            
            // Données
            foreach ($items as $item) {
                $csv .= implode(';', [
                    $item['product_name'],
                    $item['sku'] ?? '',
                    $item['unit'],
                    $item['stock_initial'],
                    $item['entrees'],
                    $item['sorties'] ?? 0,
                    $item['sdu_theorique'],
                    $item['stock_physique'] ?? '',
                    $item['ecart'] ?? '',
                    $item['cmm'],
                    number_format($item['cmp'], 0, ',', ' '),
                    number_format($item['valeur_stock'], 0, ',', ' '),
                    $item['min_stock'],
                    $item['max_stock'],
                    $this->translateStatus($item['status']),
                ]) . "\n";
            }
            
            // Pied de page
            $csv .= "\n";
            $csv .= "LÉGENDE\n";
            $csv .= "Stock Initial = Stock en début de période\n";
            $csv .= "Entrées = Réceptions fournisseur sur la période\n";
            $csv .= "Sorties = Ventes POS validées sur la période\n";
            $csv .= "SDU Théorique = Stock Initial + Entrées - Sorties\n";
            $csv .= "Stock Physique = Comptage réel (inventaire)\n";
            $csv .= "Écart = Stock Physique - SDU Théorique\n";
            $csv .= "CMM = Consommation Moyenne Mensuelle\n";
            $csv .= "CMP = Coût Moyen Pondéré\n";
            
            $filename = 'inventaire_enrichi_' . ($warehouse['name'] ?? 'export') . '_' . date('Ymd_His') . '.csv';
            
            return response($csv, 200)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"$filename\"");
                
        } catch (\Exception $e) {
            \Log::error('Export enriched inventory error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Traduire le statut en français
     */
    private function translateStatus(string $status): string
    {
        return match($status) {
            'rupture' => 'Rupture',
            'sous-stocke' => 'Sous-stocké',
            'normal' => 'Normal',
            'sur-stocke' => 'Sur-stocké',
            default => $status,
        };
    }

    /**
     * Générer automatiquement une commande fournisseur à partir de l'inventaire
     */
    public function generatePurchaseOrder(Request $request): JsonResponse
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            // Créer le bon de commande
            $reference = 'PO-' . date('Ymd') . '-' . str_pad(\App\Models\Purchase::where('tenant_id', $tenantId)->count() + 1, 4, '0', STR_PAD_LEFT);
            
            $purchase = \App\Models\Purchase::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_id' => $validated['supplier_id'],
                'reference' => $reference,
                'status' => 'pending',
                'created_by' => $user->id,
                'total' => 0,
                'notes' => 'Généré automatiquement depuis inventaire',
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                $unitPrice = $product->purchase_price ?? 0;
                $lineTotal = $item['quantity'] * $unitPrice;
                
                \App\Models\PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ]);
                
                $total += $lineTotal;
            }

            $purchase->update(['total' => $total]);

            return response()->json([
                'success' => true,
                'message' => 'Bon de commande généré',
                'data' => $purchase->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Générer une demande de stock (détail vers gros)
     */
    public function generateStockRequest(Request $request): JsonResponse
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user->tenant_id;
            
            $validated = $request->validate([
                'from_warehouse_id' => 'required|integer|exists:warehouses,id', // Gros
                'to_warehouse_id' => 'required|integer|exists:warehouses,id',   // Détail
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $reference = 'SR-' . date('Ymd') . '-' . str_pad(\App\Models\StockRequest::where('tenant_id', $tenantId)->count() + 1, 4, '0', STR_PAD_LEFT);
            
            $stockRequest = \App\Models\StockRequest::create([
                'tenant_id' => $tenantId,
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'reference' => $reference,
                'status' => 'pending',
                'created_by' => $user->id,
                'notes' => 'Généré automatiquement depuis inventaire détail',
            ]);

            foreach ($validated['items'] as $item) {
                \App\Models\StockRequestItem::create([
                    'stock_request_id' => $stockRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Demande de stock générée',
                'data' => $stockRequest->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Supprimer un inventaire (si brouillon)
     */
    public function destroy(Inventory $inventory): JsonResponse
    {
        try {
            $this->authorize('delete', $inventory);

            if ($inventory->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul les brouillons peuvent être supprimés',
                ], 422);
            }

            $inventory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inventaire supprimé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
