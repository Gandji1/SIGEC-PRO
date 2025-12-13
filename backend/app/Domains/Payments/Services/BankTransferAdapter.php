<?php

namespace App\Domains\Payments\Services;

use App\Models\Sale;
use App\Models\BankTransferPayment;
use Exception;

/**
 * Adapter pour les paiements par virement bancaire
 * Flow: Création paiement pending -> Client effectue virement -> Admin valide manuellement
 */
class BankTransferAdapter extends PaymentGatewayAdapter
{
    protected string $provider = 'bank';

    /**
     * Initialize bank transfer payment (creates pending record)
     */
    public function initializePayment(Sale $sale, string $phone, float $amount): array
    {
        try {
            // Générer une référence unique pour le virement
            $reference = $this->generateBankReference($sale);

            // Créer l'enregistrement de paiement en attente
            $payment = BankTransferPayment::create([
                'tenant_id' => $this->tenant->id,
                'reference' => $reference,
                'type' => 'sale',
                'related_id' => $sale->id,
                'amount' => $amount,
                'currency' => 'XOF',
                'status' => 'pending',
                'expires_at' => now()->addDays(7), // Expire dans 7 jours
            ]);

            // Récupérer les infos bancaires du tenant
            $bankInfo = $this->getBankInfo();

            return [
                'success' => true,
                'reference' => $reference,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'currency' => 'XOF',
                'bank_info' => $bankInfo,
                'instructions' => "Effectuez un virement de {$amount} XOF avec la référence: {$reference}",
                'expires_at' => $payment->expires_at->toIso8601String(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify bank transfer (check if manually confirmed)
     */
    public function verifyPayment(string $reference): array
    {
        $payment = BankTransferPayment::where('reference', $reference)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        if (!$payment) {
            return [
                'success' => false,
                'error' => 'Paiement non trouvé',
            ];
        }

        return [
            'success' => $payment->status === 'confirmed',
            'reference' => $reference,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
            'metadata' => [
                'sale_id' => $payment->related_id,
            ],
        ];
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus(string $reference): array
    {
        $payment = BankTransferPayment::where('reference', $reference)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        if (!$payment) {
            return ['error' => 'Paiement non trouvé'];
        }

        return [
            'reference' => $reference,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'created_at' => $payment->created_at->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
        ];
    }

    /**
     * Confirm bank transfer manually (admin action)
     */
    public function confirmPayment(string $reference, ?string $bankReference = null, ?string $notes = null): array
    {
        $payment = BankTransferPayment::where('reference', $reference)
            ->where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return [
                'success' => false,
                'error' => 'Paiement non trouvé ou déjà traité',
            ];
        }

        $payment->update([
            'status' => 'confirmed',
            'bank_reference' => $bankReference,
            'notes' => $notes,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'reference' => $reference,
            'status' => 'confirmed',
        ];
    }

    /**
     * Cancel pending bank transfer
     */
    public function cancelPayment(string $reference, ?string $reason = null): array
    {
        $payment = BankTransferPayment::where('reference', $reference)
            ->where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return [
                'success' => false,
                'error' => 'Paiement non trouvé ou déjà traité',
            ];
        }

        $payment->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        return [
            'success' => true,
            'reference' => $reference,
            'status' => 'cancelled',
        ];
    }

    /**
     * Get pending bank transfers for tenant
     */
    public function getPendingPayments(): array
    {
        return BankTransferPayment::where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'type' => $p->type,
                'related_id' => $p->related_id,
                'amount' => (float) $p->amount,
                'created_at' => $p->created_at->toIso8601String(),
                'expires_at' => $p->expires_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Generate unique bank reference
     */
    private function generateBankReference(Sale $sale): string
    {
        $prefix = strtoupper(substr($this->tenant->slug ?? 'VIR', 0, 3));
        $date = now()->format('ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "{$prefix}-{$date}-{$sale->id}-{$random}";
    }

    /**
     * Get bank info from tenant config
     */
    private function getBankInfo(): array
    {
        if ($this->config) {
            $extra = $this->config->extra_config ?? [];
            return [
                'bank_name' => $extra['bank_name'] ?? '',
                'account_name' => $extra['account_name'] ?? $this->tenant->name,
                'account_number' => $extra['account_number'] ?? '',
                'iban' => $extra['iban'] ?? '',
                'bic' => $extra['bic'] ?? '',
            ];
        }

        // Fallback
        $settings = $this->tenant->settings ?? [];
        return [
            'bank_name' => $settings['bank_name'] ?? '',
            'account_name' => $this->tenant->name,
            'account_number' => $settings['bank_account'] ?? '',
            'iban' => $settings['bank_iban'] ?? '',
            'bic' => $settings['bank_bic'] ?? '',
        ];
    }
}
