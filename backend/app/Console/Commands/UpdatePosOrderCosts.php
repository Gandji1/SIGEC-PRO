<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PosOrderItem;
use App\Models\Stock;
use App\Models\Product;

class UpdatePosOrderCosts extends Command
{
    protected $signature = 'pos:update-costs';
    protected $description = 'Met à jour les unit_cost des commandes POS avec le CMP du stock';

    public function handle()
    {
        $this->info('Mise à jour des coûts des commandes POS...');
        
        $items = PosOrderItem::whereNull('unit_cost')
            ->orWhere('unit_cost', 0)
            ->with(['posOrder', 'product'])
            ->get();
        
        $updated = 0;
        
        foreach ($items as $item) {
            $tenantId = $item->posOrder?->tenant_id;
            if (!$tenantId) continue;
            
            // Chercher le CMP dans le stock
            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $item->product_id)
                ->first();
            
            // Utiliser cost_average (CMP), sinon unit_cost du stock, sinon purchase_price du produit
            $unitCost = $stock?->cost_average 
                ?? $stock?->unit_cost 
                ?? $item->product?->purchase_price 
                ?? 0;
            
            if ($unitCost > 0) {
                $item->unit_cost = $unitCost;
                $item->save();
                $updated++;
                $this->line("  - {$item->product?->name}: {$unitCost} FCFA");
            }
        }
        
        $this->info("Terminé! {$updated} items mis à jour.");
        
        return 0;
    }
}
