<?php

/**
 * Configuration CORS - Sécurisée selon l'environnement
 * 
 * En production: restreint aux domaines autorisés
 * En développement: permissif pour faciliter le dev
 */

$allowedOrigins = env('APP_ENV') === 'production' 
    ? array_filter([
        env('FRONTEND_URL', 'https://sigec.app'),
        env('APP_URL'),
    ])
    : ['*'];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'register', 'login'],
    'allowed_methods' => ['*'],
    // En dev on autorise tout (frontend Vercel + local)
    'allowed_origins' => ['https://sigec.artbenshow.com'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
