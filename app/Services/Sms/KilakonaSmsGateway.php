<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * KilaKona SMS (https://messaging.kilakona.co.tz), a Tanzanian provider.
 *
 * The request below is a best guess. KilaKona's API reference sits behind their dashboard
 * login, so unlike the Beem gateway this was not written from provider documentation.
 * Check the endpoint, the two header names and the three body fields against their reference
 * before relying on it, and send one test message. Everything that would need correcting is
 * in send(), and the address itself comes from KILAKONA_ENDPOINT.
 */
class KilakonaSmsGateway implements SmsGateway
{
    public function __construct(
        private string $apiKey,
        private string $secret,
        private string $senderId,
        private string $endpoint,
    ) {
    }

    public function send(string $to, string $message): bool
    {
        if ($this->endpoint === '') {
            Log::error('KilaKona is the chosen SMS provider but KILAKONA_ENDPOINT is empty, so nothing was sent.');

            return false;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->withHeaders([
                    'api_key'    => $this->apiKey,
                    'api_secret' => $this->secret,
                ])
                ->post($this->endpoint, [
                    'senderName'      => $this->senderId,
                    'recipientNumber' => $to,
                    'message'         => $message,
                ]);

            if ($response->successful() && $this->accepted($response->json())) {
                return true;
            }

            Log::warning('KilaKona rejected an SMS', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (Throwable $e) {
            Log::error('KilaKona SMS request failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Providers word success differently, and this one's wording is not confirmed. A reply that
     * plainly says it failed is treated as a failure; any other 2xx is taken as accepted, which
     * is what a 2xx from an SMS API almost always means. Erring the other way would make the
     * job retry and send the same message again.
     */
    private function accepted(mixed $body): bool
    {
        if (! is_array($body)) {
            return true;
        }

        foreach (['success', 'successful', 'status'] as $key) {
            if (array_key_exists($key, $body)) {
                return in_array($body[$key], [true, 'true', 1, '1', 'success', 'SUCCESS', 'ok', 'OK', 200, '200'], true);
            }
        }

        return true;
    }
}
