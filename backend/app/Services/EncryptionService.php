<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Exception;

/**
 * Service de chiffrement pour les clés sensibles (PSP, API keys)
 * Utilise AES-256-CBC via Laravel Crypt
 */
class EncryptionService
{
    /**
     * Chiffrer une valeur sensible
     */
    public static function encrypt(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        try {
            return Crypt::encryptString($value);
        } catch (Exception $e) {
            \Log::error('Encryption failed: ' . $e->getMessage());
            throw new Exception('Failed to encrypt sensitive data');
        }
    }

    /**
     * Déchiffrer une valeur
     */
    public static function decrypt(?string $encryptedValue): ?string
    {
        if (empty($encryptedValue)) {
            return null;
        }
        
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (Exception $e) {
            \Log::error('Decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Masquer une clé pour affichage (ex: sk_live_****1234)
     */
    public static function mask(?string $value, int $visibleChars = 4): string
    {
        if (empty($value)) {
            return '(non configuré)';
        }
        
        $length = strlen($value);
        if ($length <= $visibleChars * 2) {
            return str_repeat('*', $length);
        }
        
        $prefix = substr($value, 0, $visibleChars);
        $suffix = substr($value, -$visibleChars);
        $masked = str_repeat('*', min($length - ($visibleChars * 2), 8));
        
        return $prefix . $masked . $suffix;
    }

    /**
     * Vérifier si une valeur est chiffrée (format Laravel)
     */
    public static function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        
        // Les valeurs chiffrées Laravel commencent par "eyJ"
        return str_starts_with($value, 'eyJ');
    }
}
