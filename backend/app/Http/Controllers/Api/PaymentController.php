<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\TenantPaymentConfig;
use App\Models\WebhookLog;
use App\Models\BankTransferPayment;
use App\Domains\Payments\Services\FedapayAdapter;
use App\Domains\Payments\Services\KakiapayAdapter;
use App\Domains\Payments\Services\MomoAdapter;
use App\Domains\Payments\Services\BankTransferAdapter;
use App\Services\WebhookSignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Initialize payment
     * POST /api/payments/initialize
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'phone' => 'nullable|string',
            'gateway' => 'required|in:fedapay,kkiapay,momo,bank',
        ]);

        $tenant = auth()->user()->tenant;
        $sale = Sale::where('tenant_id', $tenant->id)
            ->find($request->sale_id);

        if (!$sale) {
            return response()->json(['error' => 'Sale not found'], 404);
        }

        // Vérifier que le provider est configuré
        if ($request->gateway !== 'bank') {
            if (!TenantPaymentConfig::isProviderEnabled($tenant->id, $request->gateway)) {
                return response()->json(['error' => 'Provider non configuré'], 400);
            }
        }

        // Instantiate appropriate adapter
        $adapter = match ($request->gateway) {
            'fedapay' => new FedapayAdapter($tenant),
            'kkiapay' => new KakiapayAdapter($tenant),
            'momo' => new MomoAdapter($tenant),
            'bank' => new BankTransferAdapter($tenant),
        };

        $result = $adapter->initializePayment(
            $sale,
            $request->phone ?? '',
            $sale->total
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    /**
     * Verify payment (webhook or polling)
     * POST /api/payments/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'gateway' => 'required|in:fedapay,kakiapay,momo',
        ]);

        $tenant = auth()->user()->tenant;

        $adapter = match ($request->gateway) {
            'fedapay' => new FedapayAdapter($tenant),
            'kakiapay' => new KakiapayAdapter($tenant),
            'momo' => new MomoAdapter($tenant),
        };

        $result = $adapter->verifyPayment($request->reference);

        if (!$result['success']) {
            return response()->json(['error' => 'Payment verification failed'], 400);
        }

        // Update sale payment record
        $sale = Sale::where('tenant_id', $tenant->id)
            ->where('id', $result['metadata']['sale_id'] ?? null)
            ->first();

        if ($sale) {
            $adapter->recordPayment(
                $sale,
                $request->reference,
                $result['amount'],
                $request->gateway
            );

            $sale->update(['payment_status' => 'paid']);
        }

        return response()->json($result);
    }

    /**
     * Fedapay webhook callback (avec signature et idempotence)
     * POST /payments/fedapay/callback
     */
    public function fedapayCallback(Request $request): JsonResponse
    {
        $data = $request->all();
        $rawPayload = $request->getContent();
        $metadata = $data['metadata'] ?? [];
        
        $reference = $data['token'] ?? $data['id'] ?? null;
        $tenantId = $metadata['tenant_id'] ?? null;

        if (!$reference || !$tenantId) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        // Log webhook
        $webhookLog = WebhookLog::logReceived(
            'fedapay',
            $reference,
            $data,
            $tenantId,
            $request->header('X-Fedapay-Signature'),
            $request->ip()
        );

        // Vérifier idempotence
        if (WebhookLog::isDuplicate('fedapay', $reference)) {
            $webhookLog->markDuplicate();
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $webhookLog->markFailed('Tenant not found');
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Vérifier signature si configurée
        $config = TenantPaymentConfig::getForProvider($tenantId, 'fedapay');
        if ($config && $config->webhook_secret) {
            $signature = $request->header('X-Fedapay-Signature');
            $isValid = WebhookSignatureService::verifyFedapay($rawPayload, $signature ?? '', $config->webhook_secret);
            $webhookLog->setSignatureValid($isValid);
            
            if (!$isValid) {
                $webhookLog->markFailed('Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $adapter = new FedapayAdapter($tenant);
        $result = $adapter->verifyPayment($reference);

        if ($result['success']) {
            $sale = Sale::where('tenant_id', $tenant->id)
                ->find($metadata['sale_id']);

            if ($sale) {
                $adapter->recordPayment($sale, $reference, $result['amount'], 'fedapay');
                $sale->update(['payment_status' => 'paid']);
                $this->recordPaymentCashMovement($sale, 'fedapay', $result['amount']);
            }
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    /**
     * Kkiapay webhook callback (avec signature et idempotence)
     * POST /payments/kkiapay/callback
     */
    public function kkiapayCallback(Request $request): JsonResponse
    {
        $data = $request->all();
        $rawPayload = $request->getContent();
        $metadata = $data['metadata'] ?? [];
        
        $reference = $data['transactionId'] ?? $data['reference'] ?? null;
        $tenantId = $metadata['tenant_id'] ?? null;

        if (!$reference || !$tenantId) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        // Log webhook
        $webhookLog = WebhookLog::logReceived(
            'kkiapay',
            $reference,
            $data,
            $tenantId,
            $request->header('X-Kkiapay-Signature'),
            $request->ip()
        );

        // Vérifier idempotence
        if (WebhookLog::isDuplicate('kkiapay', $reference)) {
            $webhookLog->markDuplicate();
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $webhookLog->markFailed('Tenant not found');
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Vérifier signature si configurée
        $config = TenantPaymentConfig::getForProvider($tenantId, 'kkiapay');
        if ($config && $config->webhook_secret) {
            $signature = $request->header('X-Kkiapay-Signature');
            $isValid = WebhookSignatureService::verifyKkiapay($rawPayload, $signature ?? '', $config->webhook_secret);
            $webhookLog->setSignatureValid($isValid);
            
            if (!$isValid) {
                $webhookLog->markFailed('Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $adapter = new KakiapayAdapter($tenant);
        $result = $adapter->verifyPayment($reference);

        if ($result['success']) {
            $sale = Sale::where('tenant_id', $tenant->id)
                ->find($metadata['sale_id']);

            if ($sale) {
                $adapter->recordPayment($sale, $reference, $result['amount'], 'kkiapay');
                $sale->update(['payment_status' => 'paid']);
                $this->recordPaymentCashMovement($sale, 'kkiapay', $result['amount']);
            }
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    /**
     * MoMo webhook callback (avec idempotence)
     * POST /payments/momo/callback
     */
    public function momoCallback(Request $request): JsonResponse
    {
        $data = $request->all();
        
        $reference = $data['externalId'] ?? $data['referenceId'] ?? null;
        $saleId = $data['externalId'] ?? null;

        if (!$reference) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        // Log webhook
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

        // Trouver la vente via externalId
        $sale = Sale::find($saleId);
        if (!$sale) {
            $webhookLog->markFailed('Sale not found');
            return response()->json(['error' => 'Sale not found'], 404);
        }

        $webhookLog->update(['tenant_id' => $sale->tenant_id]);

        $status = $data['status'] ?? 'FAILED';
        
        if ($status === 'SUCCESSFUL') {
            $adapter = new MomoAdapter($sale->tenant);
            $amount = (float) ($data['amount'] ?? $sale->total);
            
            $adapter->recordPayment($sale, $reference, $amount, 'momo');
            $sale->update(['payment_status' => 'paid']);
            $this->recordPaymentCashMovement($sale, 'momo', $amount);
        }

        $webhookLog->markProcessed();
        return response()->json(['success' => true]);
    }

    /**
     * Confirm bank transfer manually
     * POST /api/payments/bank/confirm
     */
    public function confirmBankTransfer(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'bank_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;
        $adapter = new BankTransferAdapter($tenant);
        
        $result = $adapter->confirmPayment(
            $request->reference,
            $request->bank_reference,
            $request->notes
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        // Mettre à jour la vente associée
        $payment = BankTransferPayment::where('reference', $request->reference)->first();
        if ($payment && $payment->type === 'sale') {
            $sale = Sale::find($payment->related_id);
            if ($sale) {
                $adapter->recordPayment($sale, $request->reference, (float) $payment->amount, 'bank');
                $sale->update(['payment_status' => 'paid']);
                $this->recordPaymentCashMovement($sale, 'bank', (float) $payment->amount);
            }
        }

        return response()->json($result);
    }

    /**
     * Get pending bank transfers
     * GET /api/payments/bank/pending
     */
    public function pendingBankTransfers(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $adapter = new BankTransferAdapter($tenant);
        
        return response()->json([
            'success' => true,
            'data' => $adapter->getPendingPayments(),
        ]);
    }

    /**
     * Get payment status
     * GET /api/payments/{reference}/status
     */
    public function status(string $reference, Request $request): JsonResponse
    {
        $request->validate([
            'gateway' => 'required|in:fedapay,kakiapay,momo',
        ]);

        $tenant = auth()->user()->tenant;

        $adapter = match ($request->gateway) {
            'fedapay' => new FedapayAdapter($tenant),
            'kakiapay' => new KakiapayAdapter($tenant),
            'momo' => new MomoAdapter($tenant),
        };

        $result = $adapter->getTransactionStatus($reference);

        return response()->json($result);
    }

    /**
     * Record cash movement for electronic payment
     */
    private function recordPaymentCashMovement(Sale $sale, string $gateway, float $amount): void
    {
        try {
            $session = CashRegisterSession::getOpenSession($sale->tenant_id);
            CashMovement::record(
                $sale->tenant_id,
                $sale->user_id,
                'in',
                'sale',
                $amount,
                "Paiement {$gateway} - Vente {$sale->reference}",
                $session?->id,
                null,
                $gateway,
                $sale->id,
                'sale'
            );
        } catch (\Exception $e) {
            \Log::warning("Cash movement recording failed for payment: " . $e->getMessage());
        }
    }
}
