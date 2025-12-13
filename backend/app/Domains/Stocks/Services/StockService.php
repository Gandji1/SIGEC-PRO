<?php

namespace App\Domains\Stocks\Services;

use App\Models\Stock;
use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class StockService
{
    /**
     * Ajouter du stock avec calcul automatique du CMP (Coût Moyen Pondéré)
     * Formule CMP: (ancien_qty * ancien_cmp + nouveau_qty * nouveau_prix) / (ancien_qty + nouveau_qty)
     */
    public function addStock(int $product_id, int $quantity, string $warehouse = 'main', float $unit_cost = 0): Stock
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        // Récupérer ou créer le stock
        $stock = Stock::firstOrNew([
            'tenant_id' => $tenantId,
            'product_id' => $product_id,
            'warehouse' => $warehouse,
        ]);
        
        // Si nouveau stock, initialiser les valeurs
        if (!$stock->exists) {
            $stock->quantity = 0;
            $stock->reserved = 0;
            $stock->available = 0;
            $stock->cost_average = 0;
            $stock->unit_cost = 0;
        }
        
        // Récupérer le prix d'achat du produit si unit_cost non fourni
        if ($unit_cost <= 0) {
            $product = Product::find($product_id);
            $unit_cost = $product?->purchase_price ?? 0;
        }
        
        // Calculer le nouveau CMP (Coût Moyen Pondéré)
        $oldQty = $stock->quantity ?? 0;
        $oldCMP = $stock->cost_average ?? 0;
        
        if ($oldQty + $quantity > 0) {
            // Formule CMP: (ancien_qty * ancien_cmp + nouveau_qty * nouveau_prix) / total_qty
            $newCMP = ($oldQty * $oldCMP + $quantity * $unit_cost) / ($oldQty + $quantity);
        } else {
            $newCMP = $unit_cost;
        }
        
        // Mettre à jour le stock
        $stock->quantity = $oldQty + $quantity;
        $stock->cost_average = round($newCMP, 2); // CMP calculé
        $stock->unit_cost = $unit_cost; // Dernier prix d'achat
        $stock->save();
        
        $stock->updateAvailableQuantity();
        AuditLog::log('create', 'stock', $stock->id, [
            'quantity' => $quantity, 
            'unit_cost' => $unit_cost,
            'new_cmp' => $newCMP
        ], "Added $quantity units to stock, CMP updated to $newCMP");

        return $stock;
    }

    public function removeStock(int $product_id, int $quantity, string $warehouse = 'main'): bool
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        // Mode POS sans stock - on ne décrémente pas
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant && $tenant->mode_pos === 'A') {
            return true; // Mode A: pas de gestion de stock au POS
        }
        
        // Chercher le stock dans le warehouse spécifié
        $stock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $product_id)
            ->where(function($q) use ($warehouse) {
                $q->where('warehouse', $warehouse)
                  ->orWhere('warehouse_id', is_numeric($warehouse) ? $warehouse : null);
            })
            ->first();

        // Si pas trouvé, chercher dans detail ou gros
        if (!$stock) {
            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $product_id)
                ->whereIn('warehouse', ['detail', 'gros', 'main'])
                ->where('available', '>=', $quantity)
                ->first();
        }

        if (!$stock) {
            throw new Exception("Stock non trouvé pour le produit $product_id");
        }

        if ($stock->available < $quantity) {
            throw new Exception("Stock insuffisant pour le produit $product_id (disponible: {$stock->available}, demandé: $quantity)");
        }

        $stock->quantity -= $quantity;
        $stock->updateAvailableQuantity();
        AuditLog::log('delete', 'stock', $stock->id, ['quantity' => -$quantity], "Removed $quantity units from stock");

        return true;
    }

    public function reserveStock(int $product_id, int $quantity, string $warehouse = 'main'): bool
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        // Vérifier si le tenant utilise le mode POS sans stock
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant && $tenant->mode_pos === 'A') {
            // Mode A: POS sans stock - on autorise la vente sans vérification
            return true;
        }
        
        // Chercher le stock dans l'ordre: warehouse spécifié, puis detail, puis gros, puis main
        $stock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $product_id)
            ->where(function($q) use ($warehouse) {
                $q->where('warehouse', $warehouse)
                  ->orWhere('warehouse_id', is_numeric($warehouse) ? $warehouse : null);
            })
            ->first();

        // Si pas trouvé dans le warehouse spécifié, chercher dans detail ou gros
        if (!$stock) {
            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $product_id)
                ->whereIn('warehouse', ['detail', 'gros', 'main'])
                ->where('available', '>=', $quantity)
                ->first();
        }

        if (!$stock) {
            return false; // Pas de stock disponible
        }

        return $stock->reserve($quantity);
    }

    public function releaseStock(int $product_id, int $quantity, string $warehouse = 'main'): void
    {
        $stock = Stock::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->where('product_id', $product_id)
            ->where('warehouse', $warehouse)
            ->first();

        if ($stock) {
            $stock->release($quantity);
        }
    }

    public function getLowStockProducts(): Collection
    {
        return Product::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->where('track_stock', true)
            ->get()
            ->filter(fn ($p) => $p->isLowStock());
    }

    public function transferStock(int $product_id, int $quantity, string $from_warehouse, string $to_warehouse): bool
    {
        if (!$this->removeStock($product_id, $quantity, $from_warehouse)) {
            return false;
        }

        $this->addStock($product_id, $quantity, $to_warehouse);
        AuditLog::log('update', 'stock_transfer', $product_id, 
            ['from' => $from_warehouse, 'to' => $to_warehouse, 'quantity' => $quantity],
            "Transferred $quantity units from $from_warehouse to $to_warehouse"
        );

        return true;
    }

    public function getStockValue(): float
    {
        return Stock::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->sum('quantity' * 'unit_cost');
    }

    public function adjustStock(int $product_id, int $new_quantity, string $warehouse = 'main', string $reason = ''): Stock
    {
        $stock = Stock::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->where('product_id', $product_id)
            ->where('warehouse', $warehouse)
            ->first();

        if (!$stock) {
            throw new Exception("Stock not found");
        }

        $difference = $new_quantity - $stock->quantity;
        $stock->quantity = $new_quantity;
        $stock->last_counted_at = now();
        $stock->save();
        $stock->updateAvailableQuantity();

        AuditLog::log('update', 'stock', $stock->id, 
            ['old_quantity' => $stock->quantity - $difference, 'new_quantity' => $new_quantity],
            "Adjusted stock by $difference units. Reason: $reason"
        );

        return $stock;
    }
}
