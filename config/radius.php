<?php

return [
    // Public IP address of the FreeRADIUS server. RouterOS accepts an IP for a RADIUS
    // server on every version, so use an address rather than a host name.
    'host' => env('RADIUS_HOST', ''),

    // Shared secret written into every router setup script. Use a long random value.
    'secret' => env('RADIUS_SECRET', ''),

    'auth_port' => (int) env('RADIUS_AUTH_PORT', 1812),
    'acct_port' => (int) env('RADIUS_ACCT_PORT', 1813),

    // How often the router polls the platform, as a RouterOS interval.
    'agent_interval' => env('ROUTER_AGENT_INTERVAL', '1m'),

    // A router that has not polled for this many minutes is shown as offline.
    'offline_after_minutes' => (int) env('ROUTER_OFFLINE_AFTER', 5),

    // The owner is emailed and texted once a router has been silent this long, so a short blip
    // does not raise an alarm.
    'alert_after_minutes' => (int) env('ROUTER_ALERT_AFTER', 10),
];
