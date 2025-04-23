<?php
// app/Http/Controllers/DriverTransactionController.php

namespace App\Http\Controllers;

use App\Models\DriverTransaction;
use App\Models\Ride;
use App\Models\Order;
use App\Models\Driver;
use DB;
use Illuminate\Support\Facades\Auth;


use Carbon\Carbon;
use Illuminate\Http\Request;

class DriverTransactionController extends Controller
{
    public function checkAndUpdateBalance(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',  // Ensure valid amount
            'gst_amount_to_pay' => 'numeric|min:0'
        ]);

        $driverId = Auth::guard('driver')->user();
        $amount = $request->amount;
        $gstAmountToPay = $request->gst_amount_to_pay;

        // Start database transaction
        DB::beginTransaction();

        try {
            // Get the last transaction of the driver for today
            $todayTransaction = DriverTransaction::where('driver_id', $driverId->id)
                ->whereDate('created_at', Carbon::today())
                ->first();

            $driver = Driver::find($driverId->id);
            $remainingTime = null;
            $now = Carbon::now();

            if ($todayTransaction) {
                // Calculate remaining time if within 24 hours
                $lastUpdated = Carbon::parse($todayTransaction->last_updated);
                $hoursPassed = $now->diffInHours($lastUpdated);

                if ($hoursPassed < 24) {
                    $diff = $lastUpdated->addHours(24)->diff($now);

                    $remainingHours = str_pad($diff->h, 2, "0", STR_PAD_LEFT);
                    $remainingMinutes = str_pad($diff->i, 2, "0", STR_PAD_LEFT);
                    $remainingSeconds = str_pad($diff->s, 2, "0", STR_PAD_LEFT);

                    $remainingTime = "$remainingHours hour $remainingMinutes minute $remainingSeconds second";

                    return response()->json([
                        'status_code' => 403,
                        'message' => 'Balance can only be updated once every 24 hours.',
                        'remaining_time' => $remainingTime,
                    ], 403);
                }
            } else {
                // If no transaction for today, create a new one
                $transaction = DriverTransaction::create([
                    'driver_id' => $driverId->id,
                    'balance' => $amount, // Reset balance to new amount
                    'gst_amount' => $gstAmountToPay, // Reset GST amount
                    'last_updated' => $now,
                ]);

                $driver->update([
                    'wallet' => $amount,
                    'gst_amount_to_pay' => $gstAmountToPay // Update GST amount
                ]);
            }

            // Commit transaction
            DB::commit();

            return response()->json([
                'status_code' => 200,
                'driver' => $driver->first_name,
                'balance' => $driver->wallet,
                'gst_amount_to_pay' => $driver->gst_amount_to_pay,
                'remaining_time' => $remainingTime,
                'message' => 'Balance updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            // Rollback if something goes wrong
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong. Please try again.',
                'exception' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }







    // To reset the transaction for every day (or custom interval like every 3 days)
    public function dailyReset()
    {
        $loggedInDriver = Auth::guard('driver')->user();

        if (!$loggedInDriver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if the driver had any rides in the last 24 hours
        // $lastRide = Order::where('driver_id', $loggedInDriver->id)
        //     ->where('created_at', '>=', Carbon::now()->subDay()) // within 24 hours
        //     ->exists();

        // if (!$lastRide) {
        //     return response()->json(['message' => 'No rides in the last 24 hours. Balance remains unchanged.']);
        // }

        // Reset balance logic
        DriverTransaction::create([
            'driver_id' => $loggedInDriver->id, // Ensure transaction is linked to the driver
            'balance' => 100.00,  // Reset balance
            'last_updated' => Carbon::now(),
        ]);
        $driver = Driver::find($loggedInDriver->id)->update(['wallet' => 100.00]);
        return response()->json(['message' => 'Daily reset completed.', 'status_code' => 200]);
    }

    public function walletZero()
    {
        $loggedInDriver = Auth::guard('driver')->user();

        if (!$loggedInDriver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if the driver had at least one ride in the last 24 hours

        // Reset wallet balance to zero
        $loggedInDriver->wallet = 0;
        $loggedInDriver->save();
        return response()->json(['message' => 'Wallet balance reset to zero due to completed rides.', 'status_code' => 200]);

    }



    public function resetDriverGstAmount(Request $request)
    {
        $driverId = Auth::guard('driver')->user()->id;

        // Start database transaction
        DB::beginTransaction();

        try {
            // Fetch the driver
            $driver = Driver::find($driverId);

            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found.',
                ], 404);
            }

            // Reset GST amount in the driver table
            $driver->gst_amount_to_pay = 0.00;
            $driver->save();

            // Commit transaction
            DB::commit();

            return response()->json([
                'status_code' => 200,
                'driver_id' => $driverId,
                'gst_amount_to_pay' => $driver->gst_amount_to_pay,
                'message' => 'Driver GST amount has been reset to zero.',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaction if error occurs
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong. Please try again.',
                'exception' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    public function getRemainingTime()
{
    $driverId = Auth::guard('driver')->user();
    $todayTransaction = DriverTransaction::where('driver_id', $driverId->id)
        ->whereDate('created_at', Carbon::today())
        ->first();

    $remainingTime = null;
    $now = Carbon::now();

    if ($todayTransaction) {
        $lastUpdated = Carbon::parse($todayTransaction->last_updated);
        $hoursPassed = $now->diffInHours($lastUpdated);

        if ($hoursPassed < 24) {
            $diff = $lastUpdated->addHours(24)->diff($now);

            $remainingHours = str_pad($diff->h, 2, "0", STR_PAD_LEFT);
            $remainingMinutes = str_pad($diff->i, 2, "0", STR_PAD_LEFT);
            $remainingSeconds = str_pad($diff->s, 2, "0", STR_PAD_LEFT);

            $remainingTime = "$remainingHours hour $remainingMinutes minute $remainingSeconds second";
        } else {
            $remainingTime = "00 hour 00 minute 00 second";
        }
    } else {
        $remainingTime = "00 hour 00 minute 00 second";
    }

    return response()->json([
        'status_code' => 200,
        'remaining_time' => $remainingTime,
    ], 200);
}

}
