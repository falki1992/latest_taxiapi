<?php

namespace App\Http\Controllers;

use App\Events\CustomerOrder;
use App\Events\LocationUpdated;
use App\Models\CityToCityOrders;
use App\Models\FreightOrder;
use App\Models\Order;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Pusher\Pusher;
use App\Services\PusherService;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected $pusherService;

    public function __construct(PusherService $pusherService)
    {
        $this->pusherService = $pusherService;
    }
    public function create(Request $request)
    {
        $loggedIn = \Auth::id(); // Get the ID of the logged-in user

        // Validate the request
        $validator = Validator::make($request->all(), [
            'from' => 'required|string',
            'to' => 'required|string',
            'amount' => 'required|numeric',
            'passenger' => 'nullable|string',
            'comment' => 'nullable|string',

            'car_type' => 'required|string',
            'request_screen_shot' => 'required|string',
            'distance' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Decode and handle base64-encoded screenshot
        $base64Image = $request->input('request_screen_shot');
        $url = null;

        if ($base64Image) {
            // Split the base64 string into its components
            list($type, $data) = explode(';', $base64Image);
            list(, $data) = explode(',', $data);

            // Decode the base64 string
            $data = base64_decode($data);

            // Generate a unique filename for the image
            $fileName = 'screenshot_' . time() . '.png'; // Assuming PNG format; adjust if needed

            // Define the storage path (public folder)
            $storagePath = 'screenshots';
            $filePath = public_path($storagePath . '/' . $fileName);

            // Ensure the directory exists
            if (!is_dir(public_path($storagePath))) {
                mkdir(public_path($storagePath), 0755, true);
            }

            // Save the image file to the public directory
            $success = file_put_contents($filePath, $data);

            if (!$success) {
                return response()->json(['error' => 'Failed to store screenshot'], 500);
            }

            // Store the file path
            $url = $storagePath . '/' . $fileName;
        }

        // Process `from` and `to` coordinates
        $from = explode(',', $request->from);
        $fromLat = $from[0];
        $fromLng = $from[1];
        $to = explode(',', $request->to);
        $toLat = $to[0];
        $toLng = $to[1];

        // Generate a unique order ID
        $random = 'ord-' . mt_rand(10000, 99999);

        // Create a new order
        $order = Order::create([
            'order_id' => $random,
            'from_lat' => $fromLat,
            'from_lng' => $fromLng,
            'to_lat' => $toLat,
            'to_lng' => $toLng,
            'amount' => $request->amount,
            'passengers' => $request->passenger,
            'comments' => $request->comment,
            'car_type' => $request->car_type,
            'status' => 'active',
            'user_id' => $loggedIn,
            'request_screen_shot' => $url,
            'distance' => $request->distance // Save file path in database
        ]);
        $customerChaneelName = "customer-channel";
        $this->pusherService->triggerEvent($customerChaneelName, 'new-request', $order);
        // event(new CustomerOrder( $random, $order));

        return response()->json([
            'message' => 'Request sent successfully',
            'result' => $order,
            'status_code' => 200
        ], 200);
    }


    public function orderStatusUpdate(Request $request, $order_id)
    {

        $loggedIn = \Auth::user()->id;

        $myOrder = Order::where('user_id', $loggedIn)->where('order_id', $order_id)->first();
        if (empty($myOrder)) {
            return response()->json(['error' => 'No order found against this order id', 'status_code' => 500], 500);
        }
        $myOrder->status = $request->status;
        $myOrder->save();
        return response()->json([
            'message' => 'Request status updated successfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }


    public function myOrderRequests()
    {
        $loggedIn = \Auth::user()->id;
        $getMyRequests = Order::where('user_id', $loggedIn)->paginate(10);
        if (empty($getMyRequests)) {
            return response()->json([
                'status_code' => 400,
                'message' => 'Error',
                'result' => 'No data fount',
            ], 400);
        }
        return response()->json([
            'status_code' => 200,
            'message' => 'Success',
            'result' => $getMyRequests,
        ], 200);
    }
    
    public function show($id)
    {
        // Fetch the order from the database where 'order_id' matches $id
        $order = Order::where('order_id', $id)->first();

        // Check if the order exists
        if (!$order) {
            // If order does not exist, return a response with a 404 status
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // If order exists, return a JSON response with order details and a 200 status
        return response()->json([
            'status_code' => 200,
            'message' => 'Order detail fetch successfully',
            'result' => $order
        ], 200);
    }


    public function orderCityToCity(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'from' => 'required|string',
            'to' => 'required',
            'amount' => 'required',
            'passenger' => 'bail',
            'comment' => 'bail',
            'status' => 'required',
            'car_type' => 'required',
            'departure' => 'required|date|date_format:Y-m-d H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $from = explode(',', $request->from);
        $fromLat = $from[0];
        $fromLng = $from[1];
        $to = explode(',', $request->to);
        $toLat = $to[0];
        $toLng = $to[1];
        $loggedIn = \Auth::user()->id;

        $random = 'ord-' . mt_rand(10000, 99999);
        $order = CityToCityOrders::create([
            'order_id' => $random,
            'from_lat' => $fromLat,
            'from_lng' => $fromLng,
            'to_lat' => $toLat,
            'to_lng' => $toLng,
            'amount' => $request->amount,
            'passengers' => $request->passenger,
            'comments' => $request->comment,
            'car_type' => $request->car_type,
            'status' => $request->status,
            'departure' => $request->departure,
            'user_id' => $loggedIn
        ]);
        return response()->json([
            'message' => 'Request sent successfully',
            'result' => $order,
            'status_code' => 200
        ], 200);
    }

    public function citytocityOrderStatusUpdate(Request $request, $order_id)
    {
        $loggedIn = \Auth::user()->id;

        $myOrder = CityToCityOrders::where('user_id', $loggedIn)->where('order_id', $order_id)->first();
        if (empty($myOrder)) {
            return response()->json(['error' => 'No order found against this order id', 'status_code' => 500], 500);
        }
        $myOrder->status = $request->status;
        $myOrder->save();
        return response()->json([
            'message' => 'Request status updated successfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }

    public function cityToCityRequests()
    {
        $loggedIn = \Auth::user()->id;

        $myOrder = CityToCityOrders::where('user_id', $loggedIn)->get();
        if (empty($myOrder)) {
            return response()->json(['error' => 'No order found against this order id', 'status_code' => 500], 500);
        }

        return response()->json([
            'message' => 'City to city orders get successfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }

    public function freightOrder(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'from' => 'required|string',
            'to' => 'required',
            'amount' => 'required',
            'description' => 'bail',
            'status' => 'required',
            'car_type' => 'required',
            'pickup_datetime' => 'required|date|date_format:Y-m-d H:i:s',
            'other_options' => 'required',
            'screen_shot' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $base64Image = $request->input('screen_shot');
        $url = null;

        if ($base64Image) {
            // Split the base64 string into its components
            list($type, $data) = explode(';', $base64Image);
            list(, $data) = explode(',', $data);

            // Decode the base64 string
            $data = base64_decode($data);

            // Generate a unique filename for the image
            $fileName = 'screenshot_' . time() . '.png'; // Assuming PNG format; adjust if needed

            // Define the storage path (public folder)
            $storagePath = 'frieght_screenshots';
            $filePath = public_path($storagePath . '/' . $fileName);

            // Ensure the directory exists
            if (!is_dir(public_path($storagePath))) {
                mkdir(public_path($storagePath), 0755, true);
            }

            // Save the image file to the public directory
            $success = file_put_contents($filePath, $data);

            if (!$success) {
                return response()->json(['error' => 'Failed to store screenshot'], 500);
            }

            // Store the file path
            $url = $storagePath . '/' . $fileName;
        }

        $from = explode(',', $request->from);
        $fromLat = $from[0];
        $fromLng = $from[1];
        $to = explode(',', $request->to);
        $toLat = $to[0];
        $toLng = $to[1];
        $loggedIn = \Auth::user()->id;

        $random = 'ord-' . mt_rand(10000, 99999);
        $order = FreightOrder::create([
            'order_id' => $random,
            'from_lat' => $fromLat,
            'from_lng' => $fromLng,
            'to_lat' => $toLat,
            'to_lng' => $toLng,
            'amount' => $request->amount,
            'description' => $request->description,
            'car_type' => $request->car_type,
            'status' => $request->status,
            'pickup_datetime' => $request->departure,
            'user_id' => $loggedIn,
            'options' => $request->other_options,
            'screen_shot' => $url
        ]);
        return response()->json([
            'message' => 'Request sent successfully',
            'result' => $order,
            'status_code' => 200
        ], 200);
    }

    public function showFreightOrders()
    {
        $loggedIn = \Auth::user()->id;
        $order = FreightOrder::where('user_id', $loggedIn)->get();

        // Check if the order exists
        if (!$order) {
            // If order does not exist, return a response with a 404 status
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // If order exists, return a JSON response with order details and a 200 status
        return response()->json([
            'status_code' => 200,
            'message' => 'Order detail fetch successfully',
            'result' => $order
        ], 200);
    }

    public function freightOrderStatusUpdate(Request $request, $order_id)
    {
        $loggedIn = \Auth::user()->id;

        $myOrder = FreightOrder::where('user_id', $loggedIn)->where('order_id', $order_id)->first();
        if (empty($myOrder)) {
            return response()->json(['error' => 'No order found against this order id', 'status_code' => 500], 500);
        }
        $myOrder->status = $request->status;
        $myOrder->save();
        return response()->json([
            'message' => 'Request status updated successfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }

    public function track(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // Dispatch the event to broadcast the location
        event(new LocationUpdated(
            $validated['user_id'],
            $validated['latitude'],
            $validated['longitude']
        ));

        return response()->json(['status' => 'Location updated']);
    }

    public function rating(Request $request)
    {
        $validatedData = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'rate' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
            'remark' => 'required',
        ]);
        $loggedIn = Auth::user();
        $validatedData['customer_id'] = $loggedIn->id;
        $rating = Rating::create($validatedData);
        $ratingWithDetails = Rating::with(['customer', 'driver'])->find($rating->id);

        return response()->json([
            'success' => true,
            'message' => 'Rating created successfully',
            'data' => $ratingWithDetails,
        ], 200);
    }
}
