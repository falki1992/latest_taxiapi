<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\FirebaseServices;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Services\FCMService;
use App\Services\WAAPIService;
use App\Jobs\SendOtpMessageJob;


class RegisterController extends Controller
{
    protected $firebaseService;
    protected $whatsappService;

    public function __construct(WAAPIService $whatsappService)
    {

        $this->whatsappService = $whatsappService;
    }

    public function register(Request $request, WAAPIService $whatsappService)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|string|max:15',
            'fcm_token' => 'required',
            'user_type' => 'required',
            'lat' => 'required',
            'lng' => 'required',
            'country_code' => 'required|string',
            'role_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Format the mobile number correctly with the country code
        $mobileNo = $request->country_code . $request->mobile_no;

        // Check if user already exists
        $user = User::where('mobile_no', $request->mobile_no)->first();

        // Generate OTP and set expiry
        $otp = rand(1000, 9999);
        $expiry = now()->addMinutes(5);

        if ($user) {
            // Update user record with new OTP and expiry
            $user->update([
                'otp' => $otp,
                'otp_expiry' => $expiry,
                'fcm' => $request->fcm_token,
                'user_type' => $request->user_type,
                'lat' => $request->lat,
                'lng' => $request->lng,
                'country_code' => $request->country_code,
                'role_id' => $request->role_id
            ]);
        } else {
            // Create new user
            $user = User::create([
                'mobile_no' => $request->mobile_no,
                'otp' => $otp,
                'otp_expiry' => $expiry,
                'fcm' => $request->fcm_token,
                'user_type' => $request->user_type,
                'lat' => $request->lat,
                'lng' => $request->lng,
                'country_code' => $request->country_code,
                'role_id' => $request->role_id
            ]);
        }

        // Dispatch OTP message job with 15 seconds delay and pass the correct service (WAAPIService)
        SendOtpMessageJob::dispatch($mobileNo, $otp, $whatsappService)
            ->delay(now()->addSeconds(15));

        // Return response with OTP expiry time
        return response()->json([
            'message' => 'OTP sent successfully',
            'otp' => $otp, // Remove this in production for security reasons
            'otp_expiry' => now()->diffInMinutes($expiry),
            'status_code' => 200
        ], 200);
    }





    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|string',
            'otp' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'status_code' => 400], 400);
        }

        // Find user by mobile number and OTP
        $user = User::where('mobile_no', $request->mobile_no)
            ->where('otp', $request->otp)
            ->first();

        if ($user) {
            $user->tokens->each(function ($token) {
                $token->revoke();
            });
        }


        if (!$user) {
            return response()->json(['error' => 'User not found or OTP is incorrect', 'status_code' => 404], 404);
        }

        // Generate access token
        $token = $user->createToken('appToken')->accessToken;
        $user->update(['otp' => null]);

        return response()->json([
            'message' => 'OTP verified successfully',
            'token' => $token,
            'response' => $user,
            'status_code' => 200
        ], 200);
    }
    public function sendNotification(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        $user = User::find($loggedIn);

        $token = $user->fcm;
        $title = $request->input('title');
        $body = $request->input('body');

        $response = $this->fcmService->sendNotification($token, $title, $body);

        return response()->json(['message' => 'Notification sent', 'response' => json_decode($response)]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();

        return response()->json(['message' => 'Successfully logged out', 'status_code' => 400]);
    }
}
