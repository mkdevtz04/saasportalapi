<?php

return [
    // Percentage kept by the platform from every tenant withdrawal.
    // Portal payments and voucher sales carry no fee. Falls back to the old
    // PLATFORM_FEE_PCT variable so an existing .env keeps its value.
    'withdrawal_fee_pct' => (float) env('PLATFORM_WITHDRAWAL_FEE_PCT', env('PLATFORM_FEE_PCT', 5)),
];
