<?php

return [
    // Addresses of the reverse proxy or load balancer in front of the app, comma separated,
    // or * to trust whatever connects. Leave empty when PHP is reached directly by browsers.
    //
    // This matters: without it every visitor appears to come from the proxy address, so the
    // login and voucher rate limits would count all customers as one person and the stored
    // customer IP addresses would be wrong. Set it whenever nginx, Cloudflare or a load
    // balancer sits in front of the app.
    'proxies' => env('TRUSTED_PROXIES'),
];
