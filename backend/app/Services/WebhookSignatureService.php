<?php

namespace App\Services;

use Exception;

/**
 * Service de vérification des signatures webhook
 */
class WebhookSignatureService
{
    /**
     * Vérifier signature Fedapay
     * Fedapay utilise HMAC-SHA256 avec le webhook secret
     */
    public static function verifyFedapay(string $payload, string $signature, string $secret): bool
    {
        if (empty($secret)) {
            \Log::warning('Fedapay webhook secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Vérifier signature Kkiapay
     * Kkiapay utilise x-kkiapay-signature header
     */
    public static function verifyKkiapay(string $payload, string $signature, string $secret): bool
    {
        if (empty($secret)) {
            \Log::warning('Kkiapay webhook secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Vérifier signature MTN MoMo
     * MoMo utilise callback avec X-Callback-Signature
     */
    public static function verifyMomo(string $payload, string $signature, string $subscriptionKey): bool
    {
        if (empty($subscriptionKey)) {
            \Log::warning('MoMo subscription key not configured');
            return false;
        }

        // MoMo utilise une signature basée sur le subscription key
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $subscriptionKey, true));
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Vérifier signature générique
     */
    public static function verify(string $provider, string $payload, ?string $signature, string $secret): bool
    {
        if (empty($signature)) {
            \Log::warning("No signature provided for {$provider} webhook");
            return false;
        }

        return match ($provider) {
            'fedapay' => self::verifyFedapay($payload, $signature, $secret),
            'kkiapay' => self::verifyKkiapay($payload, $signature, $secret),
            'momo' => self::verifyMomo($payload, $signature, $secret),
            default => false,
        };
    }

    /**
     * Extraire la signature du header selon le provider
     */
    public static function extractSignature(string $provider, array $headers): ?string
    {
        return match ($provider) {
            'fedapay' => $headers['x-fedapay-signature'][0] ?? $headers['X-Fedapay-Signature'][0] ?? null,
            'kkiapay' => $headers['x-kkiapay-signature'][0] ?? $headers['X-Kkiapay-Signature'][0] ?? null,
            'momo' => $headers['x-callback-signature'][0] ?? $headers['X-Callback-Signature'][0] ?? null,
            default => null,
        };
    }
}
