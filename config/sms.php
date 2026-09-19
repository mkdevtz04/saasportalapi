<?php

return [
    // log   = write messages to the log and send nothing (default)
    // beem  = send through Beem Africa
    'driver' => env('SMS_DRIVER', 'log'),

    // The name customers see as the sender. Providers usually need it registered first.
    'sender_id' => env('SMS_SENDER_ID', 'TRINETPAY'),

    'beem' => [
        'api_key' => env('BEEM_API_KEY'),
        'secret'  => env('BEEM_SECRET'),
    ],
];
