<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\User;
use App\Models\UserPaymentMethods;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Pusher\Pusher;

class UserController extends Controller
{
    public function getUserType(Request $request)
    {
        // Retrieve UserType instances with pagination, order by id in descending order, and apply an offset of 10
        $userType = UserType::orderBy('id', 'desc')->offset($request->offset)->paginate(10);

        $response = [
            'status_code' => 'OK',
            'message' => 'Success',
            'result' => $userType
        ];

        return response($response, 200);
    }

    public function wallet(Request $request)
    {
        // Retrieve UserType instances with pagination, order by id in descending order, and apply an offset of 10
        $loggedIn = \Auth::user()->id;
        $user = User::find($loggedIn)->wallet;

        $response = [
            'status_code' => 'OK',
            'message' => 'Success',
            'result' => $user
        ];

        return response($response, 200);
    }

    public function myLocation(Request $request)
    {
        try {
            // Get the ID of the logged-in user
            $loggedInUserId = \Auth::user()->id;

            // Retrieve the user record with only 'lat' and 'lng' columns
            $user = User::select('lat', 'lng')->find($loggedInUserId);

            if (!$user) {
                return response()->json([
                    'status_code' => 'NOT_FOUND',
                    'message' => 'User not found',
                ], 404);
            }

            // Prepare the response
            $response = [
                'status_code' => 'OK',
                'message' => 'Success',
                'result' => [
                    'lat' => $user->lat,
                    'lng' => $user->lng,
                ],
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Exception in myLocation method: ' . $e->getMessage());

            // Return error response
            return response()->json([
                'status_code' => 'ERROR',
                'message' => 'Failed to fetch location data. Please try again later.',
            ], 500);
        }
    }

    public function profile()
    {
        try {
            // Get the ID of the logged-in user
            $loggedInUserId = \Auth::user()->id;

            // Retrieve the user record with only 'lat' and 'lng' columns
            $user = User::select('firstname', 'lastname', 'avatar')->find($loggedInUserId);

            if (!$user) {
                return response()->json([
                    'status_code' => 'NOT_FOUND',
                    'message' => 'User not found',
                ], 404);
            }

            // Prepare the response
            $response = [
                'status_code' => 'OK',
                'message' => 'Success',
                'result' => $user
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Exception in myLocation method: ' . $e->getMessage());

            // Return error response
            return response()->json([
                'status_code' => 'ERROR',
                'message' => 'Failed to fetch location data. Please try again later.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'avatar' => 'bail|string', // base64 encoded image
            'firstname' => 'bail|string',
            'lastname' => 'bail|string',

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        // Get the current authenticated user
        $user = $request->user();

        // Decode the base64 image data
        $imageData = $request->avatar;
        $imageData = str_replace('data:image/png;base64,', '', $imageData); // Adjust according to the image format
        $imageData = str_replace(' ', '+', $imageData);
        $imageBinary = base64_decode($imageData);

        // Generate a unique filename for the image
        $fileName = 'avatar_' . time() . '.png'; // Adjust file extension based on your needs

        // Define the storage path (public disk)
        $storagePath = 'avatars';

        // Store the image file in storage (under public/avatars directory)
        $success = file_put_contents(public_path($storagePath . '/' . $fileName), $imageBinary);

        if (!$success) {
            return response()->json(['error' => 'Failed to store image'], 500);
        }

        // Update user's avatar path in the database
        $user->avatar = $storagePath . '/' . $fileName;
        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;

        $user->save();

        return response()->json([
            'message' => 'Avatar updated successfully',
            'avatar_url' => asset($user->avatar),
            'status_code' => 200 // Provide full URL to the avatar
        ], 200);
    }

    public function customerRateDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rate' => 'required|numeric|min:1|max:5', // Example: numeric and between 1 to 5
            'driver_id' => 'required|exists:users,id', // Assuming drivers table with 'id' field
            'comment' => 'nullable|string|max:255', // Optional comment
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $loggedInUserId = \Auth::user()->id;
            $driverRate = DriverRating::create([
                'customer_id' => $loggedInUserId,
                'driver_id' => $request->driver_id,
                'rate' => $request->rate,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'status_code' => 200,
                'message' => 'Customer rated driver successfully',
                'result' => $driverRate, // Optionally return the rating object

            ], 200);

        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'status_code' => 500,
                'error' => 'Failed to rate driver. Please try again.',
            ], 500);
        }
    }


    public function changePhoneNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_mobileno' => 'required',
            'new_mobileno' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'error' => $validator->errors()->first()

            ], 400);
        }
        try {
            $otp = rand(1000, 9999); // Generate a random 4-digit OTP
            $expiry = now()->addMinutes(1);
            $loggedIn = auth()->user(); // Assuming user is authenticated
            $user = User::where('mobile_no', $request->old_mobileno)->where('user_type', 1)->first();
            dd($user);
            if (!empty($user)) {
                $user->mobile_no = $request->new_mobileno;
                $user->otp = $otp; // Generate OTP (4-digit random number)
                $user->otp_expiry = $expiry;
                $user->save();
                return response()->json([
                    'status_code' => 200,
                    'message' => 'OTP sent successfully',

                    'otp' => $otp
                ], 200);
            } else {
                return response()->json(['status_code' => 500, 'error' => 'This mobile no is not exist'], 500);

            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to change phone number. Please try again.',
                'status_code' => 500
            ], 500);
        }
    }

    public function paymentMethods()
    {
        $userType = UserPaymentMethods::orderBy('id', 'desc')->paginate(10);

        $response = [
            'status_code' => 'OK',
            'message' => 'Success',
            'result' => $userType
        ];

        return response($response, 200);
    }

    public function updateMyPayment(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required', // base64 encoded image


        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::find($loggedIn);
        $user->update(['payment_method' => $request->payment_method]);

        $response = [
            'status_code' => 'OK',
            'message' => 'Success',
            'result' => $user
        ];

        return response($response, 200);
    }

    public function notifications()
    {
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true
            ]
        );

        $data['message'] = 'Hello, this is a notification!';
        $pusher->trigger('notification-channel', 'notification-event', $data);

    }
}
