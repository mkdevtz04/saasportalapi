<?php

return [
    // How a new router is connected:
    //   agent  = the router calls the platform every few seconds and creates customers' hotspot users
    //            itself. Needs nothing but this app: no RADIUS server, no open port, any RouterOS version.
    //   radius = customers log in through a FreeRADIUS server (see deploy/freeradius). Instant, but
    //            needs that server and RADIUS_HOST / RADIUS_SECRET.
    //   api    = the platform logs in to the router over its API. Only works when the platform can reach
    //            the router, which a router behind NAT is not.
    'default_mode' => env('ROUTER_DEFAULT_MODE', 'agent'),

    // How often an agent-mode router checks in. A customer waits about this long after paying.
    // A RouterOS interval such as 10s or 1m.
    'agent_interval' => env('ROUTER_AGENT_POLL', '10s'),

    // A customer is told "ready" this many seconds after the router picked up their access command,
    // which is the time the router needs to create the user.
    'ready_after_seconds' => 3,
];
