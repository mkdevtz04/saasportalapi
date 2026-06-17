<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PalmPesaService
{
    protected $baseUrl;
    protected $apiKey;
    protected $userId;

    public function __construct()
    {
        $this->baseUrl = config('services.palmpesa.base_url');
        $this->apiKey  = config('services.palmpesa.key');
        $this->userId  = config('services.palmpesa.user_id');
    }

    public function initiatePayment(array $data)
    {
        $transactionId = 'TN' . strtoupper(uniqid());

        $payload = [
            'user_id'        => $this->userId,
            'name'           => $data['name'],
            'email'          => $data['email'] ?? 'customer@trinetpay.online',
            'phone'          => $this->formatPhone($data['phone']),
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
        ])->withoutVerifying()->post($this->baseUrl . '/api/pay-via-mobile', $payload);

        Log::info('PalmPesa Response', $response->json() ?? []);

        if (!$response->successful()) {
            throw new \Exception($response->json()['message'] ?? 'Payment failed');
        }

        return [
            'transaction_id' => $transactionId,
            'order_id'       => $response->json()['order_id'] ?? null,
            'response'       => $response->json(),
        ];
    }

    public function checkStatus(string $orderId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->withoutVerifying()->post($this->baseUrl . '/api/order-status', [
            'order_id' => $orderId,
        ]);

        return $response->json();
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && $phone[0] === '0') {
            return '255' . substr($phone, 1);
        }
        return $phone;
    }
}