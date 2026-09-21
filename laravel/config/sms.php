<?php

return [

    'enabled' => filter_var(env('SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'default' => env('SMS_DRIVER', 'none'),

    'allmysms' => [
        'api_key' => env('ALLMYSMS_API_KEY'),
        'sender' => env('ALLMYSMS_SENDER'),
        'url' => env('ALLMYSMS_API_URL'),
    ],

];
