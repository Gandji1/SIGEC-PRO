<?php

namespace App\Events;

use App\Models\Stock;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement diffusé lors de la mise à jour du stock
 */
class StockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tenantId;
    public int $productId;
    public string $productName;
    public int $quantity;
    public int $minStock;
    public bool $isLowStock;
    public string $action;

    public function __construct(Stock $stock, string $action = 'updated')
    {
        $this->tenantId = $stock->tenant_id;
        $this->productId = $stock->product_id;
        $this->productName = $stock->product->name ?? 'Produit';
        $this->quantity = $stock->quantity;
        $this->minStock = $stock->product->min_stock ?? 10;
        $this->isLowStock = $stock->quantity <= $this->minStock;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId . '.stock'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'min_stock' => $this->minStock,
            'is_low_stock' => $this->isLowStock,
            'action' => $this->action,
        ];
    }

    public function broadcastAs(): string
    {
        return 'stock.' . $this->action;
    }
}
