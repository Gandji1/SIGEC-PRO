<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add CORS middleware to API group
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class, // Headers sécurité + détection attaques
            \App\Http\Middleware\TenantResolver::class, // Isolation multi-tenant
        ]);

        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'module' => \App\Http\Middleware\CheckTenantModule::class,
            'plan_limit' => \App\Http\Middleware\CheckPlanLimits::class,
            'tenant' => \App\Http\Middleware\TenantResolver::class,
            'throttle.auth' => \App\Http\Middleware\RateLimitAuth::class,
        ]);

        $middleware->validateCsrfTokens(except: [

        'api/*',

    ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Retourner JSON pour les erreurs d'authentification API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Non authentifié',
                    'error' => 'Unauthenticated',
                ], 401);
            }
        });
        
        // Retourner JSON pour les erreurs de validation
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Données invalides',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        
        // Retourner JSON pour les erreurs 404
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ressource non trouvée',
                ], 404);
            }
        });
    })->create();
