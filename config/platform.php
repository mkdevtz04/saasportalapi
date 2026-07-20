<?php

return [
    'fee_pct'         => (float) env('PLATFORM_FEE_PCT', 5),
    'monthly_fee_tzs' => (int)   env('PLATFORM_MONTHLY_FEE', 15000),
    'trial_days'      => (int)   env('PLATFORM_TRIAL_DAYS', 14),
];
