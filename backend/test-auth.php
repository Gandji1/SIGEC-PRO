<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate a request with auth token
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer 7|buq7YlFT5vJXtKaHmhPNzzgs4CKnWQRGwPOgyou479c86462';
$_SERVER['HTTP_HOST'] = 'localhost:8000';

try {
    $user = auth()->guard('sanctum')->user();
    if ($user) {
        echo json_encode(['user' => $user->email, 'tenant_id' => $user->tenant_id]);
    } else {
        echo json_encode(['error' => 'No user authenticated']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
