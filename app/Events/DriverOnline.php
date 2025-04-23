<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverOnline
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $driver_id;
    public $driver;

    public function __construct($driver_id,$driver)
    {
        $this->driver_id = $driver_id;
        $this->driver = $driver;
        \Log::info('DriverOnline Event Fired', [
            'driver_id' => $this->driver_id,
            'driver' => $this->driver,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('driver-channel'),
        ];

    }

    public function broadcastWith()
    {
        return [
            'driver' => $this->driver,
        ];
    }
}
