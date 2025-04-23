<?php

namespace App\Http\Controllers;

use App\Services\WAAPIService;
use Illuminate\Http\Request;
use App\Services\WhatsappService;

class WhatsAppController extends Controller
{
    protected $waapiService;
    protected $whatsapp;

    public function __construct(WAAPIService $waapiService, WhatsAppService $whatsapp)
    {
        $this->waapiService = $waapiService;
        $this->whatsapp = $whatsapp;
    }

    public function sendMessage(Request $request)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'to' => 'required|string', // WhatsApp number with country code (e.g., +1234567890)
            'message' => 'required|string', // Message content
        ]);

        // Send message using WAAPI service
        $response = $this->waapiService->sendMessage($validated['to'], $validated['message']);

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 500);
        }

        return response()->json(['message' => 'WhatsApp message sent successfully!', 'data' => $response]);
    }

    //     public function sendOtp(Request $request)
//     {
//         dd($request);
//         $request->validate([
//             'phone' => 'required|string|min:10|max:15',
//         ]);

    //         $otp = rand(100000, 999999);
//         $response = $this->whatsapp->sendOtp($request->phone, $otp);

    //         return response()->json($response);
//     }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $otp = rand(100000, 999999);
        $response = $this->whatsapp->sendOtp($request->phone, $otp);

        return response()->json($response);
    }

}
