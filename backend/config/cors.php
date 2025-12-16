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
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => env('APP_ENV') === 'production' 
        ? ['/^https:\/\/.*\.sigec\.app$/'] // Sous-domaines en production
        : [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Tenant-ID', 'Accept'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 86400, // 24 heures
    'supports_credentials' => env('APP_ENV') === 'production',
];
