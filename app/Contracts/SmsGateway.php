<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send one text message. Returns true when the provider accepted it.
     * Must never throw: a failed SMS must not break the caller.
     *
     * @param string $to  international format without a plus sign, e.g. 255712345678
     */
    public function send(string $to, string $message): bool;
}
