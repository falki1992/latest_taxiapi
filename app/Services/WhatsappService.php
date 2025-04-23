<?php
namespace App\Services;

use GuzzleHttp\Client;

class WhatsAppService
{
    protected $client;
    protected $token;
    protected $phoneId;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = env('WHATSAPP_ACCESS_TOKEN'); // Add in .env
        $this->phoneId = env('WHATSAPP_PHONE_ID'); // Add in .env
    }

    public function sendOtp($to, $otp)
    {

        $url = "https://graph.facebook.com/v18.0/{$this->phoneId}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => 'otp_template', // Your approved OTP template name
                'language' => ['code' => 'en_US'], // Change as per your template
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->client->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }
}
