<?php

namespace App\Services;

use GuzzleHttp\Client;

class WAAPIService
{
    protected $client;
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('waapi.api_url');
        $this->apiKey = config('waapi.api_key');
    }

    public function sendMessage($to, $message)
{
    try {
	$chatId = $to . '@c.us'; // Append '@c.us' to the phone number
        // Make sure the full API URL is correct.
        $fullApiUrl = "{$this->apiUrl}/api/v1/instances/46035/client/action/send-message"; // Ensure you append the endpoint to the base URL

        $response = $this->client->post($fullApiUrl, [
            'json' => [
                'chatId' => $chatId,
                'message' => $message
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey
            ]
        ]);
        $body = $response->getBody()->getContents();
        \Log::info("WAAPI Response: " . $body);  // Log response to inspect

        return json_decode($body, true);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        \Log::error('WAAPI API Request Error: ' . $e->getMessage());  // Log request error
        return ['error' => 'Request Error: ' . $e->getMessage()];
    } catch (\Exception $e) {
        \Log::error('WAAPI General Error: ' . $e->getMessage());  // Log any general errors
        return ['error' => 'Error: ' . $e->getMessage()];
    }
}


}
