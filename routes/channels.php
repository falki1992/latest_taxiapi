<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('new-request.{orderId}', function ($user, $orderId) {
    // Fetch the order based on the provided orderId
    $order = Order::find($orderId);

    // Ensure the order exists and check if the user is the owner
    return $order && $order->user_id === $user->id;
});