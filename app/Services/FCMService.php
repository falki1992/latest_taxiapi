<?php

namespace App\Services;

use GuzzleHttp\Client;

class FCMService
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendNotification($token, $title, $body)
    {
        $serverKey = 'MZ_C8F5kKrFmu0E2O7xHeI57QP6lYGgkdno0qfQbvTcs'; // Replace with your Firebase server key
        $url = 'https://fcm.googleapis.com/fcm/send';

        $notification = [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'to' => $token,
        ];

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $notification,
        ]);

        return $response->getBody();
    }
}
