<?php

namespace App\Listeners;

use App\Events\LocationUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LocationUpdatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LocationUpdated $event)
    {
        $userId = $event->userId;
        $latitude = $event->latitude;
        $longitude = $event->longitude;

        // Perform actions based on the event
        // For example, log the location update
        \Log::info("User $userId updated location to $latitude, $longitude");
    }
}
