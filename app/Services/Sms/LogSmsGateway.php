<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Sends nothing. It writes the message to the application log, which is the default so no
 * SMS is ever sent (and paid for) until an SMS provider is chosen on purpose.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::info('SMS not sent, the log driver is active', ['to' => $to, 'message' => $message]);

        return true;
    }
}
