<?php

return [
    'currency' => 'ZAR',

    'ozow' => [
        'enabled' => env('OZOW_ENABLED', false),
        'endpoint' => env('OZOW_ENDPOINT', 'https://pay.ozow.com'),
        'site_code' => env('OZOW_SITE_CODE'),
        'private_key' => env('OZOW_PRIVATE_KEY'),
        'test_mode' => env('OZOW_TEST_MODE', false),
    ],

    'payfast' => [
        'enabled' => env('PAYFAST_ENABLED', false),
        'merchant_id' => env('PAYFAST_MERCHANT_ID'),
        'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
        'passphrase' => env('PAYFAST_PASSPHRASE'),
        'sandbox' => env('PAYFAST_SANDBOX', true),
        'process_url' => env('PAYFAST_PROCESS_URL'),
        'validate_url' => env('PAYFAST_VALIDATE_URL'),
    ],
];
