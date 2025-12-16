<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting pour les endpoints d'authentification
 * Protection contre les attaques par force brute
 */
class RateLimitAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'login'): Response
    {
        $key = $this->resolveRequestSignature($request, $type);
        
        $maxAttempts = match($type) {
            'login' => 5,      // 5 tentatives de login
            'register' => 3,   // 3 inscriptions
            '2fa' => 5,        // 5 tentatives 2FA
            default => 10,
        };
        
        $decayMinutes = match($type) {
            'login' => 1,      // Par minute
            'register' => 60,  // Par heure
            '2fa' => 1,        // Par minute
            default => 1,
        };

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Trop de tentatives. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).',
                'error' => 'TOO_MANY_ATTEMPTS',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Si succès (2xx), réinitialiser le compteur
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    /**
     * Générer une clé unique basée sur IP + email (si fourni)
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $email = $request->input('email', '');
        $ip = $request->ip();
        
        return 'auth_' . $type . '_' . sha1($ip . '|' . $email);
    }
}
