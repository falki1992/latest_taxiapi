<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrder
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $order_id;

    public function __construct($order, $order_id)
    {
        $this->order = $order;
        $this->order_id = $order_id;

        \Log::info('CustomerOrder Event Fired', [
            'order_id' => $this->order_id,
            'order' => $this->order,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {

        return [
            new PrivateChannel('new-request' . $this->order_id),
        ];

    }
    public function broadcastWith()
    {
        return [
            'order' => $this->order,
        ];
    }
}
