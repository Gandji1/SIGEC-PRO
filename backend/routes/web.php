<?php

use Illuminate\Support\Facades\Route;

// Serve SPA frontend ONLY for UI routes (not /api)
Route::get('/', function () {
    return response()->file(public_path('index.html'));
})->name('home');

// Catch-all route for SPA (redirect all non-API routes to index.html)
Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '^(?!api)(?!up).*$')->name('spa');


