<?php

namespace App\Services;

use App\Support\Phone;
use App\Support\TenantUrls;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PalmPesaService
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $userId,
    ) {}

    /**
     * Platform-wide instance using the single merchant account from config.
     * All tenant payments flow through here; revenue is split at the app layer.
     */
    public static function platform(): self
    {
        return new self(
            config('services.palmpesa.base_url'),
            config('services.palmpesa.key'),
            config('services.palmpesa.user_id'),
        );
    }

    public function initiatePayment(array $data): array
    {
        $transactionId = 'TN' . strtoupper(uniqid());

        $payload = [
            'user_id'        => $this->userId,
            'name'           => $data['name'],
            'email'          => $data['email'] ?? 'customer@' . (TenantUrls::isLocal() ? 'example.com' : TenantUrls::baseHost()),
            'phone'          => Phone::international($data['phone']),
            'amount'         => $data['amount'],
            'transaction_id' => $transactionId,
            'address'        => 'Tanzania',
            'postcode'       => '00000',
            'callback_url'   => url('/api/payment/callback'),
        ];

        Log::info('PalmPesa Request', $payload);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($this->baseUrl . '/api/pay-via-mobile', $payload);

        Log::info('PalmPesa Response', $response->json() ?? []);

        if (! $response->successful()) {
            throw new \Exception($response->json()['message'] ?? 'Payment initiation failed');
        }

        return [
            'palmpesa_txn_id' => $transactionId,
            'order_id'        => $response->json()['order_id'] ?? null,
        ];
    }

    public function checkStatus(string $orderId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($this->baseUrl . '/api/order-status', [
            'order_id' => $orderId,
        ]);

        return $response->json() ?? [];
    }
}
