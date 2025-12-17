<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de sécurité avancée
 * Ajoute des headers de sécurité et détecte les outils d'attaque
 */
class SecurityHeaders
{
    /**
     * User-agents d'outils d'attaque connus
     */
    private array $maliciousUserAgents = [
        'sqlmap',
        'nikto',
        'nmap',
        'hydra',
        'w3af',
        'acunetix',
        'nessus',
        'openvas',
        'burpsuite',
        'dirbuster',
        'gobuster',
        'wfuzz',
        'masscan',
        'zap',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Détecter les outils d'attaque via User-Agent
        $userAgent = strtolower($request->userAgent() ?? '');
        foreach ($this->maliciousUserAgents as $malicious) {
            if (str_contains($userAgent, $malicious)) {
                \Log::channel('security')->warning('Malicious tool detected', [
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                ]);
                
                // Retourner 403 sans révéler la raison exacte
                abort(403, 'Access denied');
            }
        }

        // Détecter les tentatives SQLi basiques dans l'URL
        $url = $request->fullUrl();
        if (preg_match('/(\bunion\b.*\bselect\b|\bor\b\s+1\s*=\s*1|\band\b\s+1\s*=\s*1|--\s*$|;\s*drop\s+table)/i', $url)) {
            \Log::channel('security')->warning('SQL injection attempt detected', [
                'ip' => $request->ip(),
                'url' => $url,
            ]);
            abort(403, 'Access denied');
        }

        $response = $next($request);

        // Ajouter les headers de sécurité
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // HSTS uniquement en production avec HTTPS
        if (config('app.env') === 'production' && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy basique
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://js.stripe.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.fedapay.com https://sandbox-api.fedapay.com https://api.kkiapay.me https://sandbox.momoapi.mtn.com https://api.sigec.artbenshow.com");

        return $response;
    }
}
