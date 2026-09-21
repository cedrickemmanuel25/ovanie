<?php

$mode = strtolower((string) env('PAYDUNYA_MODE', 'test'));
$keyPrefix = $mode === 'live' ? 'PAYDUNYA_LIVE_' : 'PAYDUNYA_TEST_';

return [
    'enabled' => filter_var(env('PAYDUNYA_ENABLED', false), FILTER_VALIDATE_BOOL),
    'master_key' => env($keyPrefix.'MASTER_KEY', env('PAYDUNYA_MASTER_KEY')),
    'private_key' => env($keyPrefix.'PRIVATE_KEY', env('PAYDUNYA_PRIVATE_KEY')),
    'public_key' => env($keyPrefix.'PUBLIC_KEY', env('PAYDUNYA_PUBLIC_KEY')),
    'token' => env($keyPrefix.'TOKEN', env('PAYDUNYA_TOKEN')),
    'mode' => $mode,
    'ca_bundle' => env('PAYDUNYA_CA_BUNDLE'),
    'webhook_secret' => env('PAYDUNYA_WEBHOOK_SECRET'),
    'allow_unsigned_webhooks_in_testing' => filter_var(env('PAYDUNYA_ALLOW_UNSIGNED_WEBHOOKS_IN_TESTING', false), FILTER_VALIDATE_BOOL),
    'paid_statuses' => array_filter(explode(',', env('PAYDUNYA_PAID_STATUSES', 'completed,success,paid'))),

    // Compte client fictif utilisé uniquement lorsque PAYDUNYA_MODE=test.
    'softpay_test_phone' => env('PAYDUNYA_SOFTPAY_TEST_PHONE'),
    'softpay_test_email' => env('PAYDUNYA_SOFTPAY_TEST_EMAIL'),
    'softpay_test_password' => env('PAYDUNYA_SOFTPAY_TEST_PASSWORD'),

    'store_name' => env('PAYDUNYA_STORE_NAME', 'OVANIE'),
    'store_tagline' => env('PAYDUNYA_STORE_TAGLINE', 'Marketplace Ovanie'),
    'store_phone' => env('PAYDUNYA_STORE_PHONE'),
    'store_address' => env('PAYDUNYA_STORE_ADDRESS'),
    'store_website' => env('PAYDUNYA_STORE_WEBSITE'),
    'store_logo' => env('PAYDUNYA_STORE_LOGO'),
];
