<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /**
     * Générer un secret 2FA pour l'utilisateur
     */
    public function enable(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA déjà activé'
            ], 400);
        }

        // Générer un secret Base32 (compatible Google Authenticator)
        $secret = $this->generateBase32Secret();
        
        // Stocker le secret chiffré
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->save();

        // Générer l'URL otpauth pour QR code
        $appName = config('app.name', 'SIGEC');
        $otpauthUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            urlencode($appName),
            urlencode($user->email),
            $secret,
            urlencode($appName)
        );

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_url' => $otpauthUrl,
            'message' => 'Scannez le QR code avec Google Authenticator ou entrez le secret manuellement'
        ]);
    }

    /**
     * Confirmer l'activation du 2FA avec un code
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = auth()->user();
        
        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez d\'abord activer le 2FA'
            ], 400);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        
        if (!$this->verifyTOTP($secret, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide'
            ], 400);
        }

        // Générer les codes de récupération
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '2FA activé avec succès',
            'recovery_codes' => $recoveryCodes
        ]);
    }

    /**
     * Désactiver le 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = auth()->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], 400);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '2FA désactivé'
        ]);
    }

    /**
     * Vérifier un code 2FA lors de la connexion
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'user_id' => 'required|integer'
        ]);

        $user = \App\Models\User::find($request->user_id);
        
        if (!$user || !$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé ou 2FA non activé'
            ], 400);
        }

        $code = $request->code;
        
        // Vérifier si c'est un code de récupération
        if (strlen($code) > 6) {
            return $this->verifyRecoveryCode($user, $code);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        
        if (!$this->verifyTOTP($secret, $code)) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide'
            ], 400);
        }

        // Générer le token d'authentification
        $token = $user->createToken('auth_token_2fa')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Vérification 2FA réussie',
            'user' => $user->load('tenant'),
            'tenant' => $user->tenant,
            'token' => $token
        ]);
    }

    /**
     * Obtenir le statut 2FA de l'utilisateur
     */
    public function status(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    /**
     * Régénérer les codes de récupération
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = auth()->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], 400);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA non activé'
            ], 400);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'success' => true,
            'recovery_codes' => $recoveryCodes
        ]);
    }

    /**
     * Vérifier un code de récupération
     */
    private function verifyRecoveryCode($user, string $code): JsonResponse
    {
        $recoveryCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        
        $index = array_search($code, $recoveryCodes);
        
        if ($index === false) {
            return response()->json([
                'success' => false,
                'message' => 'Code de récupération invalide'
            ], 400);
        }

        // Supprimer le code utilisé
        unset($recoveryCodes[$index]);
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($recoveryCodes)));
        $user->save();

        $token = $user->createToken('auth_token_2fa_recovery')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Code de récupération accepté',
            'user' => $user->load('tenant'),
            'tenant' => $user->tenant,
            'token' => $token,
            'remaining_recovery_codes' => count($recoveryCodes)
        ]);
    }

    /**
     * Générer un secret Base32 (20 caractères)
     */
    private function generateBase32Secret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Générer des codes de récupération
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    /**
     * Vérifier un code TOTP
     */
    private function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            $expectedCode = $this->generateTOTP($secret, $timestamp + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Générer un code TOTP
     */
    private function generateTOTP(string $secret, int $timestamp): string
    {
        // Décoder le secret Base32
        $secretKey = $this->base32Decode($secret);
        
        // Convertir le timestamp en bytes (big-endian)
        $time = pack('N*', 0) . pack('N*', $timestamp);
        
        // Calculer HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Extraire le code dynamique
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Décoder une chaîne Base32
     */
    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);
        
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;
            
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        
        return $output;
    }
}
