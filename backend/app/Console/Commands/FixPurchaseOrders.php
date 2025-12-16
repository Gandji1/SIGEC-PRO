<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Purchase;

class FixPurchaseOrders extends Command
{
    protected $signature = 'purchases:fix';
    protected $description = 'Corrige les commandes avec total=0 ou status=draft';

    public function handle()
    {
        $purchases = Purchase::where('total', 0)
            ->orWhere('status', 'draft')
            ->with('items.product')
            ->get();

        $this->info("Commandes à corriger: {$purchases->count()}");

        foreach ($purchases as $p) {
            // Recalculer le total depuis les items
            $total = 0;
            
            foreach ($p->items as $item) {
                $price = $item->unit_price;
                
                // Si prix = 0, récupérer depuis le produit
                if ($price <= 0 && $item->product) {
                    $price = $item->product->purchase_price 
                        ?? $item->product->cost_price 
                        ?? $item->product->price 
                        ?? 0;
                    
                    // Mettre à jour l'item
                    $item->unit_price = $price;
                    $item->line_subtotal = $item->quantity_ordered * $price;
                    $item->line_total = $item->quantity_ordered * $price;
                    $item->save();
                }
                
                $total += $item->quantity_ordered * $price;
            }

            // Mettre à jour le statut si fournisseur lié
            $newStatus = $p->supplier_id ? 'submitted' : 'draft';

            $p->subtotal = $total;
            $p->total = $total;
            $p->status = $newStatus;
            
            if ($newStatus == 'submitted' && !$p->submitted_at) {
                $p->submitted_at = now();
            }
            
            $p->save();

            $this->line("{$p->reference} -> Total: {$total} FCFA, Status: {$newStatus}");
        }

        $this->info('Terminé!');
        return 0;
    }
}
