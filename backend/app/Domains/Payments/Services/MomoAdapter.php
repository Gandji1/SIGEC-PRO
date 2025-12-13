<?php

namespace App\Domains\Payments\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Exception;

class MomoAdapter extends PaymentGatewayAdapter
{
    protected string $provider = 'momo';
    private string $base_url = 'https://proxy.momoapi.mtn.com';
    private string $subscription_key = '';
    private string $api_user = '';
    private string $target_environment = 'sandbox';

    public function __construct($tenant)
    {
        parent::__construct($tenant);
        $this->loadMomoCredentials();
    }

    protected function loadMomoCredentials(): void
    {
        // Utiliser TenantPaymentConfig si disponible
        if ($this->config && $this->config->is_enabled) {
            $extraConfig = $this->config->extra_config ?? [];
            $this->subscription_key = $extraConfig['subscription_key'] ?? '';
            $this->api_user = $this->config->api_user ?? ''; // Déchiffré automatiquement
            // secret_key est déjà chargé par parent
        } else {
            // Fallback vers ancienne config
            $settings = $this->tenant->settings ?? [];
            $this->subscription_key = $settings['momo_subscription_key'] ?? config('payments.momo.subscription_key', '');
            $this->api_user = $settings['momo_api_user'] ?? config('payments.momo.api_user', '');
            $this->api_key = $settings['momo_api_key'] ?? config('payments.momo.api_key', '');
        }
        
        $this->target_environment = $this->environment === 'sandbox' ? 'sandbox' : 'mtncameroon';
        $this->base_url = $this->environment === 'sandbox'
            ? 'https://sandbox.momoapi.mtn.com'
            : 'https://proxy.momoapi.mtn.com';
    }

    /**
     * Get access token from MTN MoMo API
     */
    private function getAccessToken(): ?string
    {
        try {
            $response = Http::withBasicAuth($this->api_user, $this->api_key)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $this->subscription_key,
                ])
                ->post("{$this->base_url}/collection/token/");

            if ($response->successful()) {
                return $response->json('access_token');
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Initialize payment with MTN MoMo
     */
    public function initializePayment(Sale $sale, string $phone, float $amount): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new Exception('Failed to get MoMo access token');
            }

            $referenceId = $this->generateIdempotencyKey();
            
            // Format phone number (remove leading 0 or +)
            $phone = preg_replace('/^(\+|0)/', '', $phone);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Reference-Id' => $referenceId,
                'X-Target-Environment' => $this->target_environment,
                'Ocp-Apim-Subscription-Key' => $this->subscription_key,
                'Content-Type' => 'application/json',
            ])->post("{$this->base_url}/collection/v1_0/requesttopay", [
                'amount' => (string) $amount,
                'currency' => 'XOF',
                'externalId' => (string) $sale->id,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => "Paiement vente #{$sale->id}",
                'payeeNote' => "Sale payment from {$this->tenant->name}",
            ]);

            if ($response->status() === 202) {
                return [
                    'success' => true,
                    'reference' => $referenceId,
                    'status' => 'pending',
                    'amount' => $amount,
                    'currency' => 'XOF',
                    'message' => 'Veuillez confirmer le paiement sur votre téléphone',
                ];
            }

            throw new Exception('MoMo request failed: ' . $response->body());
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment with MTN MoMo
     */
    public function verifyPayment(string $reference): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new Exception('Failed to get MoMo access token');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Target-Environment' => $this->target_environment,
                'Ocp-Apim-Subscription-Key' => $this->subscription_key,
            ])->get("{$this->base_url}/collection/v1_0/requesttopay/{$reference}");

            if ($response->failed()) {
                throw new Exception('MoMo verification failed');
            }

            $data = $response->json();
            $status = $data['status'] ?? 'FAILED';

            return [
                'success' => $status === 'SUCCESSFUL',
                'reference' => $reference,
                'status' => strtolower($status),
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'XOF',
                'payer' => $data['payer']['partyId'] ?? null,
                'metadata' => [
                    'sale_id' => $data['externalId'] ?? null,
                ],
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
        return $this->verifyPayment($reference);
    }
}
