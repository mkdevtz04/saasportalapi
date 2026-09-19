<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Beem Africa SMS (https://beem.africa), a provider that covers Tanzanian networks.
 *
 * Written from the provider documentation and covered by automated tests with a faked
 * HTTP layer only. It has not been run against the real service, so send one test
 * message before relying on it, and compare the request with the current Beem API docs.
 */
class BeemSmsGateway implements SmsGateway
{
    public function __construct(
        private string $apiKey,
        private string $secret,
        private string $senderId,
        private string $endpoint = 'https://apisms.beem.africa/v1/send',
    ) {
    }

    public function send(string $to, string $message): bool
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secret)
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint, [
                    'source_addr'   => $this->senderId,
                    'schedule_time' => '',
                    'encoding'      => 0,
                    'message'       => $message,
                    'recipients'    => [['recipient_id' => 1, 'dest_addr' => $to]],
                ]);

            if ($response->successful() && ($response->json('successful') ?? false)) {
                return true;
            }

            Log::warning('Beem rejected an SMS', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (Throwable $e) {
            Log::error('Beem SMS request failed', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
