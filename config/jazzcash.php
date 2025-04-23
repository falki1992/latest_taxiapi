<?php

return [
    'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
    'password' => env('JAZZCASH_PASSWORD'),
    'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
    'return_url' => env('JAZZCASH_RETURN_URL'),
    'base_url' => 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/',
];
