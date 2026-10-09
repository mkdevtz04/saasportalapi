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

    // How long a customer's access command keeps being handed to the router after the first time.
    //
    // The platform marks a command delivered when it writes the reply, but it cannot know the reply
    // arrived: the router fetches over the internet, and a fetch that times out mid-transfer loses
    // it. Handed out once, that command is gone, and a customer who has paid can never log in.
    // So it keeps being handed out for this long. Creating a hotspot user is safe to repeat — the
    // router removes any existing one first — and the extra copies cost a few bytes per poll.
    'redeliver_seconds' => env('ROUTER_REDELIVER_SECONDS', 120),

    // How long to keep trying to cut off an expired customer on a router the platform has to
    // reach itself, which is only the old API mode. Agent and RADIUS routers need nothing from
    // this: the first is queued and waits however long the router is away, the second is rows.
    //
    // After this long the transaction is closed off anyway. A router unreachable for a day has
    // either been switched off, in which case nobody is online through it, or has been rebooted,
    // which clears its hotspot users. Retrying for ever would mean a connection attempt per
    // expired customer per minute against a router that is never coming back.
    'expiry_retry_hours' => env('ROUTER_EXPIRY_RETRY_HOURS', 24),
];
