<?php

return [
    // Percentage kept by the platform from every tenant withdrawal.
    // Portal payments and voucher sales carry no fee. Falls back to the old
    // PLATFORM_FEE_PCT variable so an existing .env keeps its value.
    'withdrawal_fee_pct' => (float) env('PLATFORM_WITHDRAWAL_FEE_PCT', env('PLATFORM_FEE_PCT', 5)),

    // Where the platform admin is texted about things only a person can settle, such as an ISP
    // asking to withdraw. Left empty, nothing is sent and the admin panel is the only notice.
    'alert_phone' => env('PLATFORM_ALERT_PHONE'),
];
