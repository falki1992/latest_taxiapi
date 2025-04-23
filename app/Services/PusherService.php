<?php
namespace App\Services;

use Pusher\Pusher;

class PusherService
{
    protected $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ]
        );
    }

    public function triggerEvent($user_id,$eventName, $data)
    {
        $this->pusher->trigger('private-drivers-channel', $eventName, $data);
    }
}
