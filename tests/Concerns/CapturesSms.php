<?php

namespace Tests\Concerns;

use App\Contracts\SmsGateway;

trait CapturesSms
{
    /** An SMS gateway that remembers what it was asked to send. */
    protected function captureSms(bool $accept = true): object
    {
        $fake = new class ($accept) implements SmsGateway {
            public array $sent = [];

            public function __construct(private bool $accept)
            {
            }

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return $this->accept;
            }
        };

        $this->app->instance(SmsGateway::class, $fake);

        return $fake;
    }
}
