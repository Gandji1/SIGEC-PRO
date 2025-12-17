<?php

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
