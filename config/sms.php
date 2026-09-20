<?php

return [
    // log      = write messages to the log and send nothing (default)
    // beem     = send through Beem Africa
    // kilakona = send through KilaKona
    'driver' => env('SMS_DRIVER', 'log'),

    // The name customers see as the sender. Providers usually need it registered first.
    'sender_id' => env('SMS_SENDER_ID', 'TRINETPAY'),

    'beem' => [
        'api_key' => env('BEEM_API_KEY'),
        'secret'  => env('BEEM_SECRET'),
    ],

    'kilakona' => [
        'api_key' => env('KILAKONA_API_KEY'),
        'secret'  => env('KILAKONA_API_SECRET'),

        // No default on purpose: KilaKona's API reference is behind their dashboard login, so
        // the address has to come from whoever set the account up. Sending to a guessed
        // address would fail quietly, and this way it says so instead.
        'endpoint' => env('KILAKONA_ENDPOINT'),
    ],
];
