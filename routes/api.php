<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CarTypeController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomerMessageController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverMessageController;
use App\Http\Controllers\JazzCashController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DriverTransactionController;

use App\Http\Controllers\WhatsAppController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use App\Notifications\SendPushNotification;
use Illuminate\Support\Facades\Artisan;


Route::post('send-whatsapp', [WhatsAppController::class, 'sendOtp']);
Route::post('{instance}/instance', [WhatsAppController::class, 'sendMessage']);


// Route to check Laravel version
Route::get('/', function () {
    return 'Laravel version: ' . App::version();
});

// Route to clear cache (only accessible in local environment)
Route::get('/clear-cache', function () {
    if (App::environment('local')) {
        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');


        return 'Cache cleared successfully';
    } else {
        abort(403, 'Unauthorized action.');
    }
});

Route::get('/run-composer', function () {
    Artisan::call('composer:install');
    return 'Composer installed new dependeny';
});




Route::get('/install-dependencies', function () {
    // Ensure this route can only be accessed in a local environment and by an admin user

    // Execute Composer command
    $composerOutput = [];
    $composerReturnVar = 0;
    exec('composer require kreait/laravel-firebase 2>&1', $composerOutput, $composerReturnVar);

    // Execute Artisan command
    $artisanOutput = [];
    $artisanReturnVar = 0;
    exec('php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" 2>&1', $artisanOutput, $artisanReturnVar);

    // Return results
    return response()->json([
        'composer' => [
            'status' => $composerReturnVar === 0 ? 'success' : 'error',
            'output' => $composerOutput,
        ],
        'artisan' => [
            'status' => $artisanReturnVar === 0 ? 'success' : 'error',
            'output' => $artisanOutput,
        ]
    ]);
});


Route::middleware(['throttle:60,1'])->group(function () {
    // Unauthenticated routes
    Route::post('register', [RegisterController::class, 'register'])->name('register');
    Route::post('verify', [RegisterController::class, 'verify'])->name('verify');
    Route::get('user-types', [UserController::class, 'getUserType'])->name('user-type');
    Route::get('my-status', [DriverController::class, 'testUpdate']);
    Route::post('notification', [UserController::class, 'notifications']);
    Route::get('jazzcash/callback', [JazzCashController::class, 'callback'])->name('jazzcash.callback');




    // Routes requiring authentication (auth:api middleware)
    Route::middleware('auth:api')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::post('my-profile', [UserController::class, 'update'])->name('my-profile');
        Route::get('my-location', [UserController::class, 'myLocation'])->name('my-location');
        Route::get('wallet', [UserController::class, 'wallet'])->name('wallet');
        Route::get('my-profile', [UserController::class, 'profile'])->name('my-profile.show');
        Route::post('change-number', [UserController::class, 'changePhoneNumber'])->name('change-number');
        Route::post('customer/rate/driver', [UserController::class, 'customerRateDriver'])->name('customer.rate.driver');
        Route::get('car-types', [CarTypeController::class, 'index'])->name('car-types');

        Route::post('order', [OrderController::class, 'create'])->name('order');
        Route::put('order/status/{order_id}', [OrderController::class, 'orderStatusUpdate'])->name('order.status');

        Route::post('city-to-city/order', [OrderController::class, 'orderCityToCity'])->name('city-to-city.order');
        Route::get('city-to-city/orders', [OrderController::class, 'cityToCityRequests'])->name('city-to-city.orders');
        Route::get('city-to-city/car-types', [CarTypeController::class, 'cityToCityCarTypes'])->name('city-to-city.car-types');
        Route::put('city-to-city/status/{order_id}', [OrderController::class, 'citytocityOrderStatusUpdate'])->name('city-to-city.status');

        Route::get('freight/car-types', [CarTypeController::class, 'freightCarTypes'])->name('freight.car-types');
        Route::post('freight/order', [OrderController::class, 'freightOrder'])->name('freight.order');
        Route::get('freight/orders', [OrderController::class, 'showFreightOrders'])->name('freight.orders');
        Route::post('freight/status', [OrderController::class, 'freightOrderStatusUpdate'])->name('freight.order.update');


        Route::get('order/{orderid}', [OrderController::class, 'show'])->name('order.show');
        Route::get('my-requests', [OrderController::class, 'myOrderRequests'])->name('my-requests');
        Route::post('rating', [OrderController::class, 'rating'])->name('rating');

        Route::get('payment_methods', [UserController::class, 'paymentMethods'])->name('payment-methods');
        Route::put('user/payment-method', [UserController::class, 'updateMyPayment'])->name('user.paymentMethod');

        Route::post('logout', [RegisterController::class, 'logout'])->name('logout');
        Route::post('/send-message', [ChatController::class, 'sendMessage']);

        Route::get('customer-support/messages', [CustomerMessageController::class, 'index']);
        Route::post('customer-support/messages', [CustomerMessageController::class, 'store']);

    });

    Route::prefix('driver')->group(function () {
        Route::get('cars', [CarTypeController::class, 'getCars']);
        Route::get('car-types', [CarTypeController::class, 'getDriverCarType']);
        Route::get('vehicle-type', [CarTypeController::class, 'getDriverVehicleType'])->name('driver.vehicle-type');
        Route::get('production-years', [CarTypeController::class, 'getProductionYear'])->name('driver.production-year');
        Route::get('driver_type', [DriverController::class, 'getDriverType']);

        Route::post('/', [DriverController::class, 'create'])->name('driver.create');
        Route::post('car-type', [DriverController::class, 'createDriverCarType']);
        Route::post('cnic', [DriverController::class, 'driverCnicUpload']);
        Route::post('selfie-with-cnic-licence', [DriverController::class, 'driverSelfieWIthCnicUpload']);
        Route::post('selelct-car', [DriverController::class, 'driverSelectCar']);
        Route::post('plate-number', [DriverController::class, 'driverNoPlate']);
        Route::post('licence', [DriverController::class, 'driverLicenceUpload']);
        Route::post('vehicle-certification', [DriverController::class, 'driverRegistrationCertificateUpload']);
        Route::post('production-year', [DriverController::class, 'driverProductionYear']);

        // Route::post('proofs', [DriverController::class, 'createDriverProofs'])->name('driver.car-type');
        Route::post('login', [DriverController::class, 'driverLogin'])->name('driver.login');
        Route::post('otp/verify', [DriverController::class, 'verifyOtp'])->name('driver.otp.verify');

        Route::middleware('auth:driver')->group(function () {
            Route::post('order/update/{order_id}', [DriverController::class, 'OrderUpdate']);
            Route::get('order/history', [DriverController::class, 'orderHistory']);
            Route::put('profile', [DriverController::class, 'updateProfile']);
            Route::get('profile', [DriverController::class, 'myProfile']);
            Route::get('update-online-status/{status}', [DriverController::class, 'updateOnlineStatus']);
            Route::get('online-status', [DriverController::class, 'checkOnlineStatus']);
            Route::post('track-location', [DriverController::class, 'updateLocation']);
            Route::post('bids', [DriverController::class, 'bids']);
            Route::post('bids/update', [DriverController::class, 'bidUpdate']);
            Route::post('freight/order', [DriverController::class, 'freightOrder']);
            Route::post('freight/status', [DriverController::class, 'freightOrderStatusUpdate'])->name('freight.order.update');
            Route::post('profile/update', [DriverController::class, 'profileUpdate'])->name('driver.profile.update');
            // Route::post('jazzcash/pay', [JazzCashController::class, 'initiatePayment'])->name('jazzcash.pay');

            // Route::get('/test-location', function () {
            //     event(new \App\Events\LocationUpdated(1, 37.7749, -122.4194));
            //     return 'Event dispatched!';
            // });
            Route::post('/check-balance', [DriverTransactionController::class, 'checkAndUpdateBalance']);
            Route::post('transaction', [JazzCashController::class, 'transaction']);
            Route::post('/reset-driver-gst', [DriverTransactionController::class, 'resetDriverGstAmount']);

            Route::get('/daily-reset', [DriverTransactionController::class, 'dailyReset']);
            Route::get('/wallet-amount-zero', [DriverTransactionController::class, 'walletZero']);
            Route::get('/remaining-time', [DriverTransactionController::class, 'getRemainingTime']);

            Route::get('support-messages', [DriverMessageController::class, 'index']);
            Route::post('support-messages', [DriverMessageController::class, 'store']);

        });
    });
});
Route::get('rides/{rideId}/nearby-drivers', [DriverController::class, 'findNearbyDrivers']);
// Route::post('/update-location', [OrderController::class, 'track']);

// Route::get('send-whatsapp', [UserController::class, 'sentOtp']);
