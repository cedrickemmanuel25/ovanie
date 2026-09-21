<?php
return [
    'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
    'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    'currency' => env('FLUTTERWAVE_CURRENCY', 'XOF'),
    'payment_url' => 'https://api.flutterwave.com/v3/payments',
];