<?php

namespace App\Domains\Payments\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Exception;

class KakiapayAdapter extends PaymentGatewayAdapter
{
    protected string $provider = 'kkiapay';
    private string $base_url = 'https://sandbox.kakiapay.com/api/v2';

    public function __construct($tenant)
    {
        parent::__construct($tenant);
        $this->base_url = $this->environment === 'production'
            ? 'https://api.kakiapay.com/api/v2'
            : 'https://sandbox.kakiapay.com/api/v2';
    }

    /**
     * Initialize payment with Kakiapay
     */
    public function initializePayment(Sale $sale, string $phone, float $amount): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ])->post("{$this->base_url}/transactions/initiate", [
                'amount' => $this->formatAmount($amount),
                'currency' => 'XOF',
                'customer' => [
                    'name' => $sale->customer->name ?? 'Customer',
                    'phone' => $phone,
                    'email' => $sale->customer->email ?? null,
                ],
                'description' => "Sale #{$sale->id}",
                'reference' => 'SALE-' . $sale->id . '-' . time(),
                'callback_url' => route('payments.kakiapay.callback'),
                'metadata' => [
                    'sale_id' => $sale->id,
                    'tenant_id' => $this->tenant->id,
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('Kakiapay initialization failed: ' . $response->body());
            }

            $data = $response->json('data');

            return [
                'success' => true,
                'reference' => $data['reference'],
                'token' => $data['token'],
                'amount' => $amount,
                'currency' => 'XOF',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment with Kakiapay
     */
    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
            ])->get("{$this->base_url}/transactions/{$reference}");

            if ($response->failed()) {
                throw new Exception('Kakiapay verification failed');
            }

            $data = $response->json('data');

            return [
                'success' => in_array($data['status'], ['success', 'completed']),
                'reference' => $reference,
                'status' => $data['status'],
                'amount' => $this->parseAmount($data['amount']),
                'customer_phone' => $data['customer']['phone'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
            ])->get("{$this->base_url}/transactions/{$reference}");

            if ($response->failed()) {
                throw new Exception('Status check failed');
            }

            $data = $response->json('data');

            return [
                'reference' => $reference,
                'status' => $data['status'],
                'amount' => $this->parseAmount($data['amount']),
                'created_at' => $data['created_at'],
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
