<?php

namespace App\Events;

use App\Models\PosOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement diffusé lors de la mise à jour d'une commande POS
 * Permet les notifications temps réel vers le frontend
 */
class PosOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PosOrder $order;
    public string $action;
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(PosOrder $order, string $action, array $data = [])
    {
        $this->order = $order;
        $this->action = $action;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->order->tenant_id . '.pos'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'action' => $this->action,
            'preparation_status' => $this->order->preparation_status,
            'payment_status' => $this->order->payment_status,
            'status' => $this->order->status,
            'total' => $this->order->total,
            'table_number' => $this->order->table_number,
            'created_by' => $this->order->created_by,
            'updated_at' => $this->order->updated_at->toISOString(),
            'data' => $this->data,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'pos.order.' . $this->action;
    }
}
