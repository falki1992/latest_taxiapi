<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JazzCashController extends Controller
{
    public function initiatePayment(Request $request)
    {
        // Convert amount to paisa (JazzCash expects the amount in paisa)
        $amount = $request->amount * 100;
        $formattedAmount = number_format($amount, 2, '.', ''); // 100.00

        // Set transaction date and expiry
        $dateTime = now()->format('YmdHis');
        $expiryTime = now()->addMinutes(30)->format('YmdHis');

        // Prepare the data for the API call
        $postData = [
            'pp_Version' => '2.0',
            'pp_TxnType' => 'MWALLET', // Mobile wallet transaction
            'pp_Language' => 'EN',
            'pp_MerchantID' => config('jazzcash.merchant_id'),
            'pp_Password' => config('jazzcash.password'),
            'pp_Amount' => $formattedAmount,
            'pp_TxnRefNo' => 'T' . $dateTime,  // Unique transaction reference number
            'pp_Description' => 'Transaction Description',  // Description
            'pp_TxnDateTime' => $dateTime,
            'pp_TxnExpiryDateTime' => $expiryTime,
            'pp_ReturnURL' => config('jazzcash.return_url'),
            'pp_SecureHash' => '',  // Secure hash (to be generated)
        ];

        // Generate SecureHash (HMAC-SHA256)
        $postData['pp_SecureHash'] = $this->generateSecureHash($postData);

        // Log full URL for debugging
        $fullUrl = 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform';  // Update if needed
        Log::info('Request URL: ' . $fullUrl);

        // Send the payment initiation request to JazzCash
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'Laravel/JazzCash Integration',
        ])->asForm()->post($fullUrl, $postData);

        // Log response details for debugging
        Log::info('Response Status: ' . $response->status());
        Log::info('Response Body: ' . $response->body());

        // Check if the response was successful and contains the expected key
        if ($response->successful()) {
            $responseData = $response->json();
            Log::info('JazzCash API Response:', $responseData);

            if (isset($responseData['pp_RedirectURL'])) {
                // Return the redirect URL as a response to the client
                return response()->json([
                    'status' => 'success',
                    'redirect_url' => $responseData['pp_RedirectURL']
                ]);
            } else {
                // Handle missing pp_RedirectURL
                Log::error('Missing pp_RedirectURL in the response.');
                return response()->json(['status' => 'error', 'message' => 'Redirect URL missing'], 400);
            }
        } else {
            // Handle non-successful response
            Log::error('JazzCash API error or unexpected response', [
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Payment initiation failed.'], 500);
        }
    }



    // Helper function to generate the secure hash
    private function generateSecureHash(array $data)
    {
        $salt = config('jazzcash.integrity_salt');
        ksort($data); // Sort the array by keys

        // Generate the hash string from the data
        $hashString = '';
        foreach ($data as $key => $value) {
            $hashString .= ($hashString ? '&' : '') . $value;
        }

        // Generate the HMAC SHA256 hash using the salt as the key
        return hash_hmac('sha256', $hashString, $salt);
    }
    public function callback(Request $request)
    {
        // Handle the callback from JazzCash after payment processing
        $status = $request->input('status');  // 'SUCCESS' or 'FAIL'
        $transaction_id = $request->input('transaction_id');
        $amount = $request->input('amount');
        $result_code = $request->input('result_code'); // '00' indicates success, others mean failure
        $payment_reference = $request->input('payment_reference');

        // Log the response for debugging
        Log::info('JazzCash Payment Callback:', [
            'status' => $status,
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'result_code' => $result_code,
            'payment_reference' => $payment_reference,
        ]);

        // Check if the payment was successful
        if ($status === 'SUCCESS' && $result_code === '00') {
            // Payment was successful, process the transaction
            // For example, update the order status in the database
            // Order::where('payment_reference', $payment_reference)->update(['status' => 'paid']);

            return redirect()->route('payment.success')->with('message', 'Payment was successful!');
        } else {
            // Payment failed
            return redirect()->route('payment.failure')->with('message', 'Payment failed. Please try again.');
        }
    }

    public function transaction(Request $request)
    {
        // Validate incoming request
        $validated = $request->validate([
            'pp_TxnRefNo' => 'required|string|max:50',
            'pp_MerchantID' => 'required|string|max:50',
            'pp_Password' => 'required|string|max:50',
            'pp_SecureHash' => 'required|string|max:255',
            'pp_ResponseCode' => 'string|max:255',
            'pp_ResponseMessage' => 'string|max:255',
            'pp_Status' => 'string|max:255',
        ]);
        // If password is provided, hash it
        $loggedInDriver = Auth::guard('driver')->user()->id;
        // Insert the data into the database
        $validated['driver_id'] = $loggedInDriver;
        $transaction = Transaction::create($validated);
        // Return a response
        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => $transaction
        ], 201);
    }



}
