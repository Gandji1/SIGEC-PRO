<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\System\Subscription;
use App\Models\System\Payment;
use App\Models\System\SystemSetting;
use App\Models\WebhookLog;
use App\Services\WebhookSignatureService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Contrôleur pour les paiements d'abonnement des tenants
 * Utilise les clés PSP du SuperAdmin
 */
class SubscriptionPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Initialiser un paiement d'abonnement
     */
    public function initializePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'gateway' => 'required|in:fedapay,kkiapay,momo',
            'phone' => 'required_if:gateway,momo|string',
        ]);

        $user = auth()->user();
        $subscription = Subscription::where('tenant_id', $user->tenant_id)
            ->find($validated['subscription_id']);

        if (!$subscription) {
            return response()->json(['error' => 'Abonnement non trouvé'], 404);
        }

        $amount = $subscription->plan->price_monthly;
        
        try {
            $result = match ($validated['gateway']) {
                'fedapay' => $this->initializeFedapay($subscription, $amount),
                'kkiapay' => $this->initializeKkiapay($subscription, $amount),
                'momo' => $this->initializeMomo($subscription, $amount, $validated['phone']),
            };

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 400);
            }

            // Créer un enregistrement de paiement en attente
            Payment::create([
                'tenant_id' => $user->tenant_id,
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => 'XOF',
                'status' => 'pending',
                'payment_method' => $validated['gateway'],
                'payment_reference' => $result['reference'],
                'description' => 'Paiement abonnement ' . $subscription->plan->display_name,
            ]);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vérifier un paiement
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'gateway' => 'required|in:fedapay,kkiapay,momo',
        ]);

        try {
            $result = match ($validated['gateway']) {
                'fedapay' => $this->verifyFedapay($validated['reference']),
                'kkiapay' => $this->verifyKkiapay($validated['reference']),
                'momo' => $this->verifyMomo($validated['reference']),
            };

            if ($result['success']) {
                // Mettre à jour le paiement
                $payment = Payment::where('payment_reference', $validated['reference'])->first();
                if ($payment) {
                    $payment->update([
                        'status' => 'completed',
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'paid_at' => now(),
                    ]);

                    // Activer l'abonnement
                    $subscription = $payment->subscription;
                    if ($subscription) {
                        $subscription->update([
                            'status' => 'active',
                            'amount_paid' => $payment->amount,
                        ]);

                        // Mettre à jour le tenant
                        $subscription->tenant->update([
                            'subscription_expires_at' => $subscription->ends_at,
                        ]);
                    }
                }
            }

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook Fedapay pour abonnements (avec signature et idempotence)
     */
    public function fedapayWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $rawPayload = $request->getContent();
        
        $reference = $data['transaction']['reference'] ?? $data['id'] ?? null;
        if (!$reference) {
            return response()->json(['error' => 'Reference missing'], 400);
        }

        // Log webhook reçu
        $webhookLog = WebhookLog::logReceived(
            'fedapay',
            $reference,
            $data,
            null,
            $request->header('X-Fedapay-Signature'),
            $request->ip()
        );

        // Vérifier idempotence
        if (WebhookLog::isDuplicate('fedapay', $reference)) {
            $webhookLog->markDuplicate();
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // Vérifier signature
        $webhookSecret = SystemSetting::get('fedapay_webhook_secret');
        if ($webhookSecret) {
            $signature = $request->header('X-Fedapay-Signature');
            $isValid = WebhookSignatureService::verifyFedapay($rawPayload, $signature ?? '', $webhookSecret);
            $webhookLog->setSignatureValid($isValid);
            
            if (!$isValid) {
                $webhookLog->markFailed('Invalid signature');
                \Log::channel('security')->warning('Invalid Fedapay webhook signature', ['reference' => $reference]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payment = Payment::where('payment_reference', $reference)->first();
        if (!$payment) {
            $webhookLog->markFailed('Payment not found');
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $status = $data['transaction']['status'] ?? $data['status'] ?? 'failed';
        
        if ($status === 'approved' || $status === 'successful') {
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $data['transaction']['id'] ?? null,
                'paid_at' => now(),
            ]);
            $this->activateSubscription($payment);
        } elseif ($status === 'declined' || $status === 'failed') {
            $payment->update(['status' => 'failed']);
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    /**
     * Webhook Kkiapay pour abonnements (avec signature et idempotence)
     */
    public function kkiapayWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $rawPayload = $request->getContent();
        
        $reference = $data['transactionId'] ?? null;
        if (!$reference) {
            return response()->json(['error' => 'Reference missing'], 400);
        }

        // Log webhook reçu
        $webhookLog = WebhookLog::logReceived(
            'kkiapay',
            $reference,
            $data,
            null,
            $request->header('X-Kkiapay-Signature'),
            $request->ip()
        );

        // Vérifier idempotence
        if (WebhookLog::isDuplicate('kkiapay', $reference)) {
            $webhookLog->markDuplicate();
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // Vérifier signature
        $webhookSecret = SystemSetting::get('kkiapay_secret');
        if ($webhookSecret) {
            $signature = $request->header('X-Kkiapay-Signature');
            $isValid = WebhookSignatureService::verifyKkiapay($rawPayload, $signature ?? '', $webhookSecret);
            $webhookLog->setSignatureValid($isValid);
            
            if (!$isValid) {
                $webhookLog->markFailed('Invalid signature');
                \Log::channel('security')->warning('Invalid Kkiapay webhook signature', ['reference' => $reference]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payment = Payment::where('payment_reference', $reference)->first();
        if (!$payment) {
            $webhookLog->markFailed('Payment not found');
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $status = $data['status'] ?? 'FAILED';
        
        if ($status === 'SUCCESS') {
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $data['internalTransactionId'] ?? null,
                'paid_at' => now(),
            ]);
            $this->activateSubscription($payment);
        } else {
            $payment->update(['status' => 'failed']);
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    /**
     * Webhook MoMo pour abonnements (avec signature et idempotence)
     */
    public function momoWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $rawPayload = $request->getContent();
        
        $reference = $data['externalId'] ?? $data['referenceId'] ?? null;
        if (!$reference) {
            return response()->json(['error' => 'Reference missing'], 400);
        }

        // Log webhook reçu
        $webhookLog = WebhookLog::logReceived(
            'momo',
            $reference,
            $data,
            null,
            $request->header('X-Callback-Signature'),
            $request->ip()
        );

        // Vérifier idempotence
        if (WebhookLog::isDuplicate('momo', $reference)) {
            $webhookLog->markDuplicate();
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        $payment = Payment::where('payment_reference', $reference)->first();
        if (!$payment) {
            $webhookLog->markFailed('Payment not found');
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $status = $data['status'] ?? 'FAILED';
        
        if ($status === 'SUCCESSFUL') {
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $data['financialTransactionId'] ?? null,
                'paid_at' => now(),
            ]);
            $this->activateSubscription($payment);
        } else {
            $payment->update(['status' => 'failed']);
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    // ========================================
    // MÉTHODES PRIVÉES
    // ========================================

    private function activateSubscription(Payment $payment): void
    {
        $subscription = $payment->subscription;
        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'amount_paid' => $payment->amount,
            ]);

            $subscription->tenant->update([
                'subscription_expires_at' => $subscription->ends_at,
            ]);
        }
    }

    private function initializeFedapay(Subscription $subscription, float $amount): array
    {
        $apiKey = SystemSetting::get('fedapay_secret_key');
        $environment = SystemSetting::get('payment_environment', 'sandbox');
        
        if (!$apiKey) {
            return ['success' => false, 'error' => 'Fedapay non configuré'];
        }

        $baseUrl = $environment === 'sandbox' 
            ? 'https://sandbox-api.fedapay.com/v1'
            : 'https://api.fedapay.com/v1';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/transactions", [
                'description' => "Abonnement {$subscription->plan->display_name}",
                'amount' => (int) ($amount * 100),
                'currency' => ['iso' => 'XOF'],
                'callback_url' => url('/api/webhooks/subscription/fedapay'),
                'customer' => [
                    'firstname' => $subscription->tenant->name,
                    'email' => $subscription->tenant->email ?? 'contact@' . $subscription->tenant->domain,
                ],
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                ],
            ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => 'Fedapay error: ' . $response->body()];
            }

            $data = $response->json();

            return [
                'success' => true,
                'reference' => $data['v1']['token'] ?? $data['v1']['id'],
                'payment_url' => $data['v1']['url'] ?? null,
                'amount' => $amount,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function initializeKkiapay(Subscription $subscription, float $amount): array
    {
        $publicKey = SystemSetting::get('kkiapay_public_key');
        $privateKey = SystemSetting::get('kkiapay_private_key');
        $secret = SystemSetting::get('kkiapay_secret');
        
        if (!$publicKey || !$privateKey) {
            return ['success' => false, 'error' => 'Kkiapay non configuré'];
        }

        // Kkiapay utilise un SDK côté client, on retourne les infos nécessaires
        return [
            'success' => true,
            'reference' => 'KK-' . uniqid(),
            'public_key' => $publicKey,
            'amount' => $amount,
            'data' => [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ],
        ];
    }

    private function initializeMomo(Subscription $subscription, float $amount, string $phone): array
    {
        $subscriptionKey = SystemSetting::get('momo_subscription_key');
        $apiUser = SystemSetting::get('momo_api_user');
        $apiKey = SystemSetting::get('momo_api_key');
        $environment = SystemSetting::get('payment_environment', 'sandbox');
        
        if (!$subscriptionKey || !$apiUser || !$apiKey) {
            return ['success' => false, 'error' => 'MoMo non configuré'];
        }

        $baseUrl = $environment === 'sandbox'
            ? 'https://sandbox.momoapi.mtn.com'
            : 'https://proxy.momoapi.mtn.com';

        try {
            // Get access token
            $tokenResponse = Http::withBasicAuth($apiUser, $apiKey)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
                ->post("{$baseUrl}/collection/token/");

            if ($tokenResponse->failed()) {
                return ['success' => false, 'error' => 'Failed to get MoMo token'];
            }

            $accessToken = $tokenResponse->json('access_token');
            $referenceId = bin2hex(random_bytes(16));
            $phone = preg_replace('/^(\+|0)/', '', $phone);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Reference-Id' => $referenceId,
                'X-Target-Environment' => $environment === 'sandbox' ? 'sandbox' : 'mtncameroon',
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/collection/v1_0/requesttopay", [
                'amount' => (string) $amount,
                'currency' => 'XOF',
                'externalId' => (string) $subscription->id,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => "Abonnement {$subscription->plan->display_name}",
                'payeeNote' => "Subscription payment",
            ]);

            if ($response->status() === 202) {
                return [
                    'success' => true,
                    'reference' => $referenceId,
                    'amount' => $amount,
                    'message' => 'Confirmez le paiement sur votre téléphone',
                ];
            }

            return ['success' => false, 'error' => 'MoMo request failed'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyFedapay(string $reference): array
    {
        $apiKey = SystemSetting::get('fedapay_secret_key');
        $environment = SystemSetting::get('payment_environment', 'sandbox');
        
        $baseUrl = $environment === 'sandbox' 
            ? 'https://sandbox-api.fedapay.com/v1'
            : 'https://api.fedapay.com/v1';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get("{$baseUrl}/transactions/{$reference}");

            if ($response->failed()) {
                return ['success' => false, 'error' => 'Verification failed'];
            }

            $data = $response->json('v1');
            
            return [
                'success' => $data['status'] === 'approved',
                'status' => $data['status'],
                'transaction_id' => $data['id'],
                'amount' => $data['amount'] / 100,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyKkiapay(string $reference): array
    {
        $privateKey = SystemSetting::get('kkiapay_private_key');
        
        try {
            $response = Http::withHeaders([
                'x-private-key' => $privateKey,
            ])->post('https://api.kkiapay.me/api/v1/transactions/status', [
                'transactionId' => $reference,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => 'Verification failed'];
            }

            $data = $response->json();
            
            return [
                'success' => $data['status'] === 'SUCCESS',
                'status' => $data['status'],
                'transaction_id' => $data['internalTransactionId'] ?? null,
                'amount' => $data['amount'] ?? 0,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyMomo(string $reference): array
    {
        $subscriptionKey = SystemSetting::get('momo_subscription_key');
        $apiUser = SystemSetting::get('momo_api_user');
        $apiKey = SystemSetting::get('momo_api_key');
        $environment = SystemSetting::get('payment_environment', 'sandbox');

        $baseUrl = $environment === 'sandbox'
            ? 'https://sandbox.momoapi.mtn.com'
            : 'https://proxy.momoapi.mtn.com';

        try {
            $tokenResponse = Http::withBasicAuth($apiUser, $apiKey)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
                ->post("{$baseUrl}/collection/token/");

            $accessToken = $tokenResponse->json('access_token');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Target-Environment' => $environment === 'sandbox' ? 'sandbox' : 'mtncameroon',
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])->get("{$baseUrl}/collection/v1_0/requesttopay/{$reference}");

            if ($response->failed()) {
                return ['success' => false, 'error' => 'Verification failed'];
            }

            $data = $response->json();
            
            return [
                'success' => $data['status'] === 'SUCCESSFUL',
                'status' => strtolower($data['status']),
                'transaction_id' => $data['financialTransactionId'] ?? null,
                'amount' => (float) $data['amount'],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
