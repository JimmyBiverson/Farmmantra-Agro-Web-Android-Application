<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $status,
        public string $deliveryStatus = 'pending',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.'.$this->order->id),
            new PrivateChannel('franchise.'.$this->order->franchise_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }
}
