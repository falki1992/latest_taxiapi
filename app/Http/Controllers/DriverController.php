<?php

namespace App\Http\Controllers;

use App\Jobs\SendDriverOtpMessageJob;
use App\Models\Bid;
use App\Models\Driver;
use App\Models\DriverProofs;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Pusher\Pusher;
use App\Models\FreightOrder;
use App\Models\DriverCarType;
use App\Services\WAAPIService;
use Illuminate\Support\Facades\DB;



class DriverController extends Controller
{
    protected $pusherService;
    protected $whatsappService;


    public function __construct(WAAPIService $whatsappService)
    {

        $this->whatsappService = $whatsappService;
    }
    private $mimeTypeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        // Add more MIME types and extensions as needed
    ];


    public function driverLogin(Request $request, WAAPIService $whatsappService)
    {
        // Validate the request
        $validated = $request->validate([
            'mobile_no' => 'required|string',
            'country_code' => 'required|string',
        ]);

        $mobileNo = $validated['country_code'] . $validated['mobile_no'];

        // Find the driver by mobile number
        $driver = Driver::where('mobile_no', $validated['mobile_no'])
            ->where('user_type', 2)
            ->where('status', 1)
            ->first();

        if (!$driver) {
            return response()->json(['status_code' => 400, 'message' => 'Invalid credentials'], 400);
        }

        // Generate OTP
        $otp = rand(1000, 9999);  // Generate a random 4-digit OTP
        $expiry = Carbon::now()->addMinutes(1);  // Set expiry for 1 minute

        // Store OTP and expiration in the database
        DB::table('verify_emails')->updateOrInsert(
            ['mobile_no' => $validated['mobile_no']],
            ['code' => $otp, 'expires_at' => $expiry, 'user_id' => $driver->id]
        );
        SendDriverOtpMessageJob::dispatch($mobileNo, $otp, $whatsappService)
            ->delay(now()->addSeconds(15));

        return response()->json([
            'status_code' => 200,
            'message' => 'Login successful. OTP sent to mobile number.',
            'otp' => $otp,
            'expiry' => $expiry
        ]);
    }
    public function verifyOtp(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'mobile_no' => 'required|string',
            'otp' => 'required|integer',
            'lat' => 'required|string',
            'lng' => 'required',
            'fcm_token' => 'required'
        ]);

        // Retrieve the OTP record from the database
        $driver = Driver::with('car', 'driverCarType', 'driverProofs', 'driverType')

            ->where('mobile_no', $validated['mobile_no'])
            ->first();
        $otpRecord = DB::table('verify_emails')->where('mobile_no', $validated['mobile_no'])
            ->where('code', $validated['otp'])
            ->first();

        if (!$otpRecord) {
            return response()->json(['status_code' => 400, 'message' => 'Invalid OTP'], 400);
        }

        // Check if the OTP has expired
        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            // Optionally, delete the expired OTP record
            return response()->json(['status_code' => 400, 'message' => 'OTP has expired'], 400);
        }


        DB::table('verify_emails')->where('mobile_no', $validated['mobile_no'])->delete();
        $token = $driver->createToken('appDriverToken')->accessToken;
        return response()->json(['status_code' => 200, 'message' => 'OTP verified successfully', 'token' => $token, 'driver' => $driver], 200);
    }
    public function create(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'mobile_no' => 'required|string|max:15',
            'user_type' => 'required|string',
            'address' => 'required|string',
            'email' => 'required|email',
            'dob' => 'required|date_format:Y-m-d',
            'avatar' => 'required|string',
            'gender' => 'required|string|in:male,female,other',
            'country_code' => 'required',
            'role_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // **Check if driver exists (by email or mobile number)**
        $existingDriver = Driver::where('mobile_no', $request->mobile_no)
            ->first();

        // Process the image
        $imageData = $request->avatar;
        if (preg_match('/^data:image\/(?<type>png|jpeg);base64,(?<data>.+)$/', $imageData, $matches)) {
            $imageData = base64_decode($matches['data']);
            if ($imageData === false) {
                return response()->json(['error' => 'Base64 decode failed'], 400);
            }

            $fileName = 'avatar_' . time() . '.' . $matches['type'];
        } else {
            return response()->json(['error' => 'Invalid image format'], 400);
        }

        $storagePath = 'driver/avatars';
        $filePath = public_path($storagePath . '/' . $fileName);

        if (!file_exists(public_path($storagePath))) {
            mkdir(public_path($storagePath), 0755, true);
        }

        if (file_put_contents($filePath, $imageData) === false) {
            return response()->json(['error' => 'Failed to store image'], 500);
        }

        $imageUrl = asset($storagePath . '/' . $fileName);
        $hashedPassword = Hash::make($request->password);
        $driverIdUUid = $this->getUniqueDriverUUID();
        $existingDriverUUid = Driver::where('uuid', $driverIdUUid)->first();
        if ($existingDriverUUid) {
            $driverIdUUid = $this->getUniqueDriverUUID();
        }



        if ($existingDriver) {
            // **Update the existing driver**
            $existingDriver->update([

                'first_name' => $request->firstname,
                'last_name' => $request->lastname,
                'mobile_no' => $request->mobile_no,
                'email' => $request->email,
                'user_type' => $request->user_type,
                'avatar' => $imageUrl,

                'address' => $request->address,
                'dob' => $request->dob,
                'gender' => $request->gender,
                'country_code' => $request->country_code,
                'role_id' => $request->role_id,
            ]);

            return response()->json(['message' => 'Driver updated successfully', 'status_code' => 200, 'result' => $existingDriver], 200);
        } else {
            // **Create a new driver**
            $user = Driver::create([
                'uuid' => $driverIdUUid,
                'first_name' => $request->firstname,
                'last_name' => $request->lastname,
                'mobile_no' => $request->mobile_no,
                'email' => $request->email,
                'user_type' => $request->user_type,
                'avatar' => $imageUrl,
                'password' => $hashedPassword,
                'status' => 0,
                'address' => $request->address,
                'dob' => $request->dob,
                'gender' => $request->gender,
                'country_code' => $request->country_code,
                'role_id' => $request->role_id,
            ]);


            return response()->json(['message' => 'Driver created successfully', 'status_code' => 200, 'result' => $user], 200);
        }
    }
    function getUniqueDriverUUID()
    {
        do {
            $uuid = 'DR-' . mt_rand(1000000000, 9999999999);
        } while (Driver::where('uuid', $uuid)->exists());

        return $uuid;
    }


    public function getDriverType(Request $request)
    {
        $getDriverType = DriverCarType::get();
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $getDriverType
        ];
        return response($response, 200);
    }


    public function createDriverCarType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|string|max:15|unique:users,mobile_no',
            'car_type' => 'required|string',
            'vehicle_type_id' => 'required',
            'driver_type_id' => 'nullable'
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }
        $driver = Driver::find($request->driver_id);
        if (empty($driver)) {
            return response()->json(['error' => 'no driver found against this id .',], 400);
        }
        $driver->update([
            'car_type' => $request->car_type,
            'vehicle_type_id' => $request->vehicle_type_id,
            'driver_type_id' => $request->driver_type_id
        ]);

        return response()->json([
            'message' => 'Driver info updated successfully',
            'result' => $driver,
            'status_code' => 200
        ], 200);
    }

    public function driverProofs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|string|max:15|unique:users,mobile_no',
            'car_type' => 'required|string',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }
    }


    public function driverCnicUpload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'nic_front' => 'required|string',
            'nic_back' => 'required|string',
            'cnic_no' => 'required|string',
            'driver_id' => 'required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Directory to save images
        $directory = public_path('driver_proofs');

        // Check if the directory exists, if not create it
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Initialize array to hold file paths
        $filePaths = [];

        foreach (['nic_front', 'nic_back'] as $field) {
            $base64Image = $request->input($field);

            // Check if the base64 image is present
            if (!$base64Image) {
                return response()->json(['error' => "{$field} is missing", 'status_code' => 400], 400);
            }

            // Validate base64 format
            if (!preg_match('/^data:image\/(?<type>png|jpeg);base64,(?<data>.+)$/', $base64Image, $matches)) {
                return response()->json(['error' => 'Invalid image format for ' . $field, 'status_code' => 400], 400);
            }

            // Decode the base64 string
            $data = base64_decode($matches['data']);
            if ($data === false) {
                return response()->json(['error' => 'Base64 decode failed for ' . $field, 'status_code' => 400], 400);
            }

            // Determine file extension
            $fileExtension = $matches['type'] === 'png' ? 'png' : 'jpeg';

            // Generate a file name and path
            $fileName = time() . '_' . $field . '.' . $fileExtension;
            $filePath = $directory . '/' . $fileName;

            // Save the image to the file system
            if (File::put($filePath, $data) === false) {
                return response()->json(['error' => 'Failed to save ' . $field, 'status_code' => 500], 500);
            }

            // Store the full file path
            $filePaths[$field] = asset('driver_proofs/' . $fileName);
        }

        // Check if a record exists for the driver_id
        $driverProof = DriverProofs::where('driver_id', $request->driver_id)->first();

        if ($driverProof) {
            // Update existing record
            $driverProof->update([
                'nic_front' => $filePaths['nic_front'],
                'nic_back' => $filePaths['nic_back'],
                'cnic' => $request->cnic_no
            ]);
        } else {
            // Create new record
            DriverProofs::create([
                'nic_front' => $filePaths['nic_front'],
                'nic_back' => $filePaths['nic_back'],
                'cnic' => $request->cnic_no,
                'driver_id' => $request->driver_id
            ]);
        }

        // Return a success message with full paths
        return response()->json(['status_code' => 200, 'message' => 'Success', 'file_paths' => $filePaths], 200);
    }



    public function driverSelfieWIthCnicUpload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'selfie_with_nic_licence' => 'required|string', // Base64-encoded image string
            'driver_id' => 'required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Directory to save images
        $directory = public_path('driver_proofs');

        // Check if the directory exists, if not create it
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Initialize array to hold file paths
        $filePaths = [];

        foreach (['selfie_with_nic_licence'] as $field) {
            $base64Image = $request->input($field);
            if ($base64Image) {
                // Split the base64 string into its components
                list($type, $data) = explode(';', $base64Image);
                list(, $data) = explode(',', $data);

                // Decode the base64 string
                $data = base64_decode($data);

                // Extract MIME type and determine file extension
                $mimeType = str_replace('data:', '', $type);
                $fileExtension = $this->getFileExtensionFromMimeType($mimeType);

                if (!$fileExtension) {
                    return response()->json(['error' => 'Unsupported file type', 'status_code' => 400], 400);
                }

                // Generate a file name and path
                $fileName = time() . '_' . $field . '.' . $fileExtension;
                $filePath = $directory . '/' . $fileName;

                // Save the image to the file system
                File::put($filePath, $data);

                // Store the file path relative to the public directory
                $filePaths[$field] = 'driver_proofs/' . $fileName;
            } else {
                // Handle missing files gracefully
                $filePaths[$field] = null;
            }
        }

        // Update the record in the database
        DriverProofs::where('driver_id', $request->driver_id)->update([
            'selfie_with_nic_licence' => $filePaths['selfie_with_nic_licence'] ?? null,
            'nic_back' => $filePaths['nic_back'] ?? null,


        ]);

        // Return a success message
        return response()->json(['status_code' => 200, 'message' => 'Success'], 200);
    }

    public function driverSelectCar(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
            'car_id' => 'required|exists:cars,id',
            'car_year' => 'required',
            'car_images' => 'required|array', // Ensure it's an array
            'car_images.*' => 'required|string', // Each image should be a base64 string
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Update the driver's car details
        Driver::where('id', $request->driver_id)->update([
            'car_id' => $request->car_id,
            'car_model' => $request->car_model ?? null,
            'car_year' => $request->car_year,
        ]);

        // Handle multiple images
        $imageUrls = [];
        foreach ($request->car_images as $imageData) {
            if (preg_match('/^data:image\/(?<type>png|jpeg|jpg);base64,(?<data>.+)$/', $imageData, $matches)) {
                $imageDecoded = base64_decode($matches['data']);
                if ($imageDecoded === false) {
                    return response()->json(['error' => 'Invalid base64 image format'], 400);
                }

                // Generate a unique filename
                $fileName = 'vehicle_' . time() . '_' . uniqid() . '.' . $matches['type'];
                $storagePath = 'uploads/vehicles/';
                $filePath = public_path($storagePath . $fileName);

                // Ensure the directory exists
                if (!file_exists(public_path($storagePath))) {
                    mkdir(public_path($storagePath), 0755, true);
                }

                // Save the image
                file_put_contents($filePath, $imageDecoded);

                // Store the image URL in the database
                $imageUrl = asset($storagePath . $fileName);
                $imageUrls[] = $imageUrl;

                // Save each image in the `driver_vehicle_images` table
                \DB::table('driver_vehicle_pictures')->insert([
                    'driver_id' => $request->driver_id,
                    'image' => $imageUrl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                return response()->json(['error' => 'Invalid image format'], 400);
            }
        }

        return response()->json(['status_code' => 200, 'message' => 'Success', 'images' => $imageUrls], 200);
    }

    public function driverNoPlate(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'no_plate' => 'required', // Base64-encoded image string
            'driver_id' => 'required|exists:drivers,id',
            'no_plate_firstname' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Update the record in the database
        Driver::where('id', $request->driver_id)->update([
            'no_plate' => $request['no_plate'] ?? null,
            'no_plate_firstname' => $request['no_plate_firstname'] ?? null,

        ]);

        // Return a success message
        return response()->json(['status_code' => 200, 'message' => 'Success'], 200);
    }

    public function driverLicenceUpload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'licence_front' => 'required|string', // Base64-encoded image string
            'licence_back' => 'required|string',
            'expiry_year' => 'required|digits:4', // Validate as a 4-digit year
            'driver_id' => 'required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Directory to save images inside the public folder
        $storagePath = 'driver_proofs/';
        $directory = public_path($storagePath);

        // Ensure the directory exists
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Initialize array to hold file URLs
        $fileUrls = [];

        foreach (['licence_front', 'licence_back'] as $field) {
            $base64Image = $request->input($field);
            if ($base64Image) {
                // Extract the base64 data
                list($type, $data) = explode(';', $base64Image);
                list(, $data) = explode(',', $data);
                $data = base64_decode($data);

                // Get file extension from MIME type
                $mimeType = str_replace('data:', '', $type);
                $fileExtension = $this->getFileExtensionFromMimeType($mimeType);

                if (!$fileExtension) {
                    return response()->json(['error' => 'Unsupported file type', 'status_code' => 400], 400);
                }

                // Generate unique file name
                $fileName = 'driver_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $filePath = $directory . '/' . $fileName;

                // Save the image to the file system
                File::put($filePath, $data);

                // Store the full public URL
                $fileUrls[$field] = asset($storagePath . $fileName);
            } else {
                // Handle missing images gracefully
                $fileUrls[$field] = null;
            }
        }

        // Update the record in the database
        DriverProofs::updateOrCreate(
            ['driver_id' => $request->driver_id],
            [
                'licence_front' => $fileUrls['licence_front'],
                'licence_back' => $fileUrls['licence_back'],
                'licence_expiry_year' => $request->expiry_year
            ]
        );

        // Return success response with full URLs
        return response()->json([
            'status_code' => 200,
            'message' => 'Success',
            'licence_front' => $fileUrls['licence_front'],
            'licence_back' => $fileUrls['licence_back']
        ], 200);
    }

    // Function to get the file extension from MIME type



    public function driverRegistrationCertificateUpload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'registration_front' => 'required|string', // Base64-encoded image string
            'registration_back' => 'required|string',
            'driver_id' => 'required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Directory to save images
        $directory = public_path('driver_proofs');

        // Check if the directory exists, if not create it
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Initialize array to hold file paths
        $fileUrls = [];

        foreach (['registration_front', 'registration_back'] as $field) {
            $base64Image = $request->input($field);
            if ($base64Image) {
                // Split the base64 string into its components
                list($type, $data) = explode(';', $base64Image);
                list(, $data) = explode(',', $data);

                // Decode the base64 string
                $data = base64_decode($data);

                // Extract MIME type and determine file extension
                $mimeType = str_replace('data:', '', $type);
                $fileExtension = $this->getFileExtensionFromMimeType($mimeType);

                if (!$fileExtension) {
                    return response()->json(['error' => 'Unsupported file type', 'status_code' => 400], 400);
                }

                // Generate a file name and path
                $fileName = time() . '_' . $field . '.' . $fileExtension;
                $filePath = $directory . '/' . $fileName;

                // Save the image to the file system
                File::put($filePath, $data);

                // Store the full URL
                $fileUrls[$field] = asset('driver_proofs/' . $fileName);
            } else {
                // Handle missing files gracefully
                $fileUrls[$field] = null;
            }
        }

        // Update the record in the database
        DriverProofs::where('driver_id', $request->driver_id)->update([
            'vehicle_certificate_front' => $fileUrls['registration_front'],
            'vehicle_certificate_back' => $fileUrls['registration_back'],
        ]);

        // Return a success message with full URLs
        return response()->json([
            'status_code' => 200,
            'message' => 'Success',
            'data' => [
                'vehicle_certificate_front' => $fileUrls['registration_front'],
                'vehicle_certificate_back' => $fileUrls['registration_back'],
            ]
        ], 200);
    }


    public function driverProductionYear(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'production_year' => 'required|string', // Base64-encoded image string
            'driver_id' => 'required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }
        // Update the record in the database
        Driver::find($request->driver_id)->update([
            'production_year' => $request->production_year ?? null,
        ]);

        // Return a success message
        return response()->json(['status_code' => 200, 'message' => 'Success'], 200);
    }

    public function getFileExtensionFromMimeType($mimeType)
    {
        // Check if the MIME type exists in the mapping
        if (array_key_exists($mimeType, $this->mimeTypeToExtension)) {
            return $this->mimeTypeToExtension[$mimeType];
        }

        // Return a default extension or null if MIME type is not found
        return null;
    }



    public function createDriverProofs(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'selfie' => 'required|string',
            'selfie_with_nic_licence' => 'required|string',
            'nic_front' => 'required|string',
            'nic_back' => 'required|string',
            'licence' => 'required|string',
            'driver_id' => 'required|exists:drivers,id', // Ensure driver_id exists in the drivers table
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Directory to store the proofs
        $directory = public_path('driver_proofs');

        // Check if the directory exists, if not create it
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePaths = [];

        // Process each base64-encoded image
        foreach (['selfie', 'selfie_with_nic_licence', 'nic_front', 'nic_back', 'licence'] as $field) {
            $base64Image = $request->input($field);
            if ($base64Image) {
                // Split the base64 string into its components
                list($type, $data) = explode(';', $base64Image);
                list(, $data) = explode(',', $data);

                // Decode the base64 string
                $data = base64_decode($data);

                // Generate a file name and path
                $fileExtension = 'png'; // Default file extension; adjust as needed
                $fileName = time() . '_' . $field . '.' . $fileExtension;
                $filePath = $directory . '/' . $fileName;

                // Save the image to the file system
                File::put($filePath, $data);

                // Store the file path relative to the public directory
                $filePaths[$field] = 'driver_proofs/' . $fileName;
            } else {
                // Handle missing files gracefully
                $filePaths[$field] = null;
            }
        }

        // Create a new record in the database
        $driverProof = DriverProofs::create([
            'driver_id' => $request->input('driver_id'),
            'selfie' => $filePaths['selfie'] ?? null,
            'selfie_with_nic_licence' => $filePaths['selfie_with_nic_licence'] ?? null,
            'nic_front' => $filePaths['nic_front'] ?? null,
            'nic_back' => $filePaths['nic_back'] ?? null,
            'licence' => $filePaths['licence'] ?? null,
        ]);

        // Return the created record or a success message
        return response()->json(['status_code' => 200, 'message' => 'Success', 'data' => $driverProof], 200);
    }



    public function OrderUpdate(Request $request, $order_id)
    {
        $loggedInDriver = Auth::guard('driver')->user();

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Fetch the order with related user and driver details
            $myOrder = Order::with('user', 'driver')->where('order_id', $order_id)->first();

            if (empty($myOrder)) {
                return response()->json(['error' => 'No order found against this order ID', 'status_code' => 500], 500);
            }

            // Update order status and assign driver ID
            $myOrder->status = $request->status;
            $myOrder->driver_id = $loggedInDriver->id;

            // Initialize GST amount
            $gstAmount = 0;

            // If the order status is 'Accepted', deduct 5% GST from the total amount
            if (strtolower($request->status) == 'accepted') {
                $gstPercentage = 5;
                $gstAmount = ($myOrder->amount * $gstPercentage) / 100;

                // Deduct GST from the order amount
                $myOrder->remaining_amount = $myOrder->amount - $gstAmount;
                $myOrder->gst_amount = $gstAmount;

                // Update the driver's `gst_amount_to_pay` field
                $loggedInDriver->gst_amount_to_pay += $gstAmount;
                $loggedInDriver->save(); // Save driver GST update
            }

            // Save the updated order details
            $myOrder->save();

            // Commit the transaction (save changes permanently)
            DB::commit();

            return response()->json([
                'message' => 'Request status updated successfully',
                'result' => $myOrder,
                'driver_gst_amount' => $loggedInDriver->gst_amount_to_pay, // Returning updated driver GST amount
                'status_code' => 200
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollBack();

            return response()->json([
                'error' => 'Something went wrong. Please try again.',
                'exception' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }




    public function orderHistory(Request $request)
    {
        $loggedInDriver = Auth::guard('driver')->user()->id;
        $myOrder = Order::with('user', 'driver')->where('driver_id', $loggedInDriver)
            ->orderBy('created_at', 'desc')->paginate(10);
        return response()->json([
            'message' => 'Driver history get sucessfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }


    public function updateProfile(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'dob' => 'nullable|date',
            'avatar' => 'nullable|url',
            'email' => 'nullable|email|max:50|unique:users,email,' . Auth::id(),
        ]);
        $loggedInDriver = Auth::guard('driver')->user()->id;

        // Update user profile
        $driver = Driver::find($loggedInDriver);
        return response()->json(['status_code' => 200, 'message' => 'OTP verified successfully', 'token' => $token], 200);
    }

    public function checkOnlineStatus()
    {
        // Fetch the user by ID
        $loggedInDriver = Auth::guard('driver')->user()->id;

        $driver = Driver::find($loggedInDriver);

        if (!$driver) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if the user is online
        $isOnline = $driver->is_online;

        return response()->json([
            'status_code' => 200,
            'message' => 'Driver status get successfully',
            'driver' => $driver,
            'is_online' => $isOnline
        ], 200);
    }

    // You might want to update the online status somewhere else in your code
    public function updateOnlineStatus($status)
    {
        $loggedInDriver = Auth::guard('driver')->user()->id;
        $driver = Driver::find($loggedInDriver);

        if (!$driver) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $driver->is_online = $status;
        $driver->save();
        $driverData = ['driver' => $driver, 'status' => $status];
        // event(new DriverOnline($loggedInDriver, $driver));

        $driverChannel = 'private-driver-channel';
        $this->pusherService->triggerEvent($driverChannel, 'driver-online', $driverData);
        return response()->json([
            'status_code' => 200,
            'message' => 'Driver status updated successfully',
            'driver' => $driver,
            'is_online' => $driver->is_online
        ]);
    }
    public function updateLocation(Request $request)
    {
        // Validate incoming request
        $validatedData = $request->validate([
            // 'driver_id' => 'required|integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ]
        );

        $loggedInDriver = Auth::guard('driver')->user()->id;
        $data = [
            'driverId' => $loggedInDriver,

            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ];
        $this->pusherService->triggerEvent('location-updated', $data);

        // Dispatch the event
        // event(new LocationUpdated( $loggedInDriver, $validatedData['latitude'], $validatedData['longitude']));
        return response()->json(['message' => 'Location updated successfully!']);
    }

    public function findNearbyDrivers(Request $request, $orderId)
    {

        $ride = Order::where('order_id', $orderId)->first();

        $latitude = $ride->from_lat ?? null;
        $longitude = $ride->from_lng ?? null;

        $distances = [1, 2, 3];
        $bids = [];

        foreach ($distances as $distance) {
            $drivers = Driver::select('id', 'first_name', 'last_name', 'mobile_no', 'lat', 'lng')
                ->whereRaw("(
                    6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))
                ) < ?", [$latitude, $longitude, $latitude, $distance])
                ->where('car_type', $ride->car_type)->get();

            // If drivers are found within the distance
            if ($drivers->count() > 0) {
                foreach ($drivers as $driver) {
                    // Create a bid (you might want to customize this part)
                    $bids[] = [
                        'driver_id' => $driver->id,
                        'driver_name' => $driver->first_name . ' ' . $driver->last_name,
                        'mobile_no' => $driver->mobile_no,
                        'profile_picture' => $driver->avatar,
                        'ride_id' => $ride->id,
                        'amount' => $ride->amount, // Define this method as needed
                        'driver_detail' => $driver
                    ];
                }

                // Break after finding drivers for the first valid distance
                break;
            }
        }
        $nearByDrivers = 'private-driver-channel';
        $this->pusherService->triggerEvent($nearByDrivers, 'nearby-driver', $bids);

        // Send bids (e.g., save to database, notify drivers, etc.)
        // You can call a method here to send notifications or save bids
        return response()->json([
            'status_code' => 200,
            'message' => 'You find new drivers near by you',
            'data' => $bids
        ]);
    }
    private function calculateBidAmount($ride, $driver)
    {
        // Implement your logic to calculate the bid amount based on ride and driver
        return 100; // Example static amount
    }

    public function bids(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,order_id',
            'amount' => 'required|numeric|min:0',
            'bid_type' => 'required'

        ]);

        $loggedInDriverId = Auth::guard('driver')->user()->id;
        $driver = Driver::find($loggedInDriverId);
        $order = Order::where('order_id', $request->order_id)->first();
        $user = User::find($order->user_id);
        // Create the bid
        $bid = Bid::create([
            'driver_id' => $loggedInDriverId,
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'bid_type' => $request->bid_type,
            'status' => 'active'
        ]);

        // Prepare the response data
        $responseData = [
            'bid' => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'bid_type' => $bid->bid_type,
                'created_at' => $bid->created_at,
                'updated_at' => $bid->updated_at,
            ],
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->first_name . ' ' . $driver->last_name,
                'mobile_no' => $driver->mobile_no,
                'profile_picture' => $driver->avatar,
                'lat' => $driver->lat,
                'lng' => $driver->lng
                // Add any other driver details you need
            ],
            'order' => [
                'ride_id' => $order->order_id,
                'from' => [
                    'lat' => $order->from_lat,
                    'lng' => $order->from_lng,
                ],
                'to' => [
                    'lat' => $order->to_lat,
                    'lng' => $order->to_lng,
                ],
                'amount' => $order->amount,
                'status' => $order->status,
                // Add any other order details you need
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->firstname . ' ' . $user->lastname,
                'mobile_no' => $user->mobile_no,
                'profile_picture' => $user->avatar,
                'lat' => $user->lat,
                'lng' => $user->lng
            ],
        ];

        return response()->json([
            'status_code' => 200,
            'message' => 'Bid sent to customer successfully',
            'data' => $responseData,
        ]);
    }

    public function bidUpdate(Request $request)
    {
        $request->validate([
            'bid_id' => 'required|exists:bids,id',
            'status' => 'required',


        ]);


        $bid = Bid::find($request->bid_id);
        $bid->update(['status' => 'accepted']);
        return response()->json([
            'status_code' => 200,
            'message' => 'Bid updated successfully',

        ]);
    }

    public function freightOrder(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'parcel_image' => 'required|string', // Assuming you keep 'parcel_image'

        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status_code' => 400], 400);
        }

        // Process the image
        $imageData = $request->parcel_image; // Use the correct key here

        // Check for a valid base64 image format
        if (preg_match('/^data:image\/(?<type>png|jpeg|jpg);base64,(?<data>.+)$/', $imageData, $matches)) {
            $imageData = base64_decode($matches['data']);
            if ($imageData === false) {
                return response()->json(['error' => 'Base64 decode failed'], 400);
            }

            // Generate a unique filename for the image
            $fileName = 'freight_' . time() . '.' . $matches['type'];
        } else {
            return response()->json(['error' => 'Invalid image format'], 400);
        }

        // Define the storage path in the public directory
        $publicPath = public_path('driver/freight');

        // Ensure the directory exists
        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        // Store the image file in the public directory
        $filePath = $publicPath . '/' . $fileName;

        try {
            file_put_contents($filePath, $imageData);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to store image: ' . $e->getMessage()], 500);
        }

        // Generate the full URL of the image
        $imageUrl = asset('driver/freight/' . $fileName);

        // Update the order record


        // Return a success response
        return response()->json(['message' => 'Order image uploaded successfully', 'status_code' => 200, 'result' => $imageUrl], 200);
    }

    public function freightOrderStatusUpdate(Request $request)
    {
        $loggedInDriverId = Auth::guard('driver')->user()->id;
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'order_id' => 'required|exists:orders,order_id',


        ]);
        $orderId = $request->order_id;
        $myOrder = FreightOrder::where('order_id', $orderId)->first();
        if (empty($myOrder)) {
            return response()->json(['error' => 'No order found against this order id', 'status_code' => 500], 500);
        }
        $myOrder->status = $request->status;
        $myOrder->driver_id = $loggedInDriverId;
        $myOrder->save();
        return response()->json([
            'message' => 'Request status updated successfully',
            'result' => $myOrder,
            'status_code' => 200
        ], 200);
    }
    public function profileUpdate(Request $request)
    {
        // Get the currently authenticated driver
        $loggedInDriver = Auth::guard('driver')->user();

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|unique:drivers,email,' . $loggedInDriver->id,  // Only check uniqueness for other drivers, not for the current one
            'dob' => [
                'nullable',
                'date',
                'before:' . now()->subYears(18)->toDateString(), // Ensure date of birth is at least 18 years ago
            ],
            'avatar' => 'nullable|string', // Base64 image as a string
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        // If there's an avatar (profile picture), process the base64 image
        if ($request->has('avatar') && $request->avatar) {
            $imageData = $request->avatar;

            // Check if the image is in a valid base64 format
            if (preg_match('/^data:image\/(?<type>png|jpeg);base64,(?<data>.+)$/', $imageData, $matches)) {
                // Decode the base64 string
                $imageData = base64_decode($matches['data']);

                // If decoding fails, return an error
                if ($imageData === false) {
                    return response()->json(['error' => 'Base64 decode failed'], 400);
                }

                // Generate a unique filename based on the timestamp and file type
                $fileName = 'avatar_' . time() . '.' . $matches['type'];
            } else {
                return response()->json(['error' => 'Invalid image format'], 400);
            }

            // Define the storage path (public disk) for avatars
            $storagePath = 'driver/avatars';
            $filePath = public_path($storagePath . '/' . $fileName);

            // Ensure the directory exists
            if (!file_exists(public_path($storagePath))) {
                mkdir(public_path($storagePath), 0755, true);
            }

            // Store the image file in the storage
            if (file_put_contents($filePath, $imageData) === false) {
                return response()->json(['error' => 'Failed to store image'], 500);
            }

            // Generate the full URL of the image to store in the user's profile
            $imageUrl = asset($storagePath . '/' . $fileName);
        } else {
            // If no avatar is provided, retain the current avatar image (if any)
            $imageUrl = $loggedInDriver->avatar; // Assuming 'avatar' is the column storing the image path
        }

        // Update the driver's profile with the new data
        $loggedInDriver->update([
            'first_name' => $request->input('first_name', $loggedInDriver->first_name),
            'last_name' => $request->input('last_name', $loggedInDriver->last_name),
            'address' => $request->input('address', $loggedInDriver->address),
            'email' => $request->input('email', $loggedInDriver->email),
            'dob' => $request->input('dob', $loggedInDriver->dob),
            'avatar' => $imageUrl, // Update with the new avatar URL
        ]);

        // Return a success response with the updated user data
        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $loggedInDriver
        ], 200);
    }

    public function myProfile()
    {
        $loggedInDriver = Auth::guard('driver')->user();

        if (!$loggedInDriver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Get driver details
        $driver = Driver::find($loggedInDriver->id);

        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Driver not found'], 404);
        }

        // Count today's orders
        $todayOrdersCount = Order::where('driver_id', $loggedInDriver->id)
            ->whereDate('created_at', Carbon::today()) // Filter only today's orders
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Driver profile details retrieved successfully',
            'data' => [
                'driver' => $driver,
                'today_orders_count' => $todayOrdersCount
            ]
        ], 200);
    }
}
