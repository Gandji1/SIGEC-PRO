<?php

namespace App\Domains\Payments\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Exception;

class FedapayAdapter extends PaymentGatewayAdapter
{
    protected string $provider = 'fedapay';
    private string $base_url = 'https://api.fedapay.com/v1';

    public function __construct($tenant)
    {
        parent::__construct($tenant);
        $this->base_url = $this->environment === 'sandbox'
            ? 'https://sandbox-api.fedapay.com/v1'
            : 'https://api.fedapay.com/v1';
    }

    /**
     * Initialize payment with Fedapay
     */
    public function initializePayment(Sale $sale, string $phone, float $amount): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ])->post("{$this->base_url}/transactions", [
                'description' => "Sale #{$sale->id}",
                'amount' => $this->formatAmount($amount),
                'currency' => 'XOF', // West African CFA franc
                'callback_url' => route('payments.fedapay.callback'),
                'customer' => [
                    'firstname' => $sale->customer->name ?? 'Customer',
                    'phone' => $phone,
                ],
                'metadata' => [
                    'sale_id' => $sale->id,
                    'tenant_id' => $this->tenant->id,
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('Fedapay initialization failed: ' . $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'reference' => $data['data']['token'] ?? $data['data']['id'],
                'status_url' => $data['data']['url'] ?? null,
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
     * Verify payment with Fedapay
     */
    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
            ])->get("{$this->base_url}/transactions/{$reference}");

            if ($response->failed()) {
                throw new Exception('Fedapay verification failed');
            }

            $data = $response->json('data');

            return [
                'success' => $data['status'] === 'approved',
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
                'updated_at' => $data['updated_at'],
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
