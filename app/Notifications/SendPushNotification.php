<?php

// app/Notifications/SendPushNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

class SendPushNotification extends Notification
{
    use Queueable;

    private $title;
    private $message;

    public function __construct($title, $message)
    {
        $this->title = $title;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['firebase'];
    }

    public function toFirebase($notifiable)
    {
        return [
            'notification' => [
                'title' => $this->title,
                'body' => $this->message,
            ],
            'to' => $notifiable->routeNotificationFor('firebase'),
        ];
    }
}
