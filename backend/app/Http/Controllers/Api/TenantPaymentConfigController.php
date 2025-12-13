<?php

namespace App\Http\Controllers\Api;

use App\Models\TenantPaymentConfig;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Contrôleur pour la configuration des paiements par tenant
 * Permet aux tenants de configurer leurs propres clés PSP
 */
class TenantPaymentConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:owner,admin');
    }

    /**
     * Liste des configurations PSP du tenant (clés masquées)
     */
    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $configs = TenantPaymentConfig::where('tenant_id', $tenantId)
            ->get()
            ->map(fn($c) => $c->toSafeArray());

        // Ajouter les providers non configurés
        $providers = ['fedapay', 'kkiapay', 'momo', 'bank'];
        $configuredProviders = $configs->pluck('provider')->toArray();
        
        foreach ($providers as $provider) {
            if (!in_array($provider, $configuredProviders)) {
                $configs->push([
                    'provider' => $provider,
                    'environment' => 'sandbox',
                    'is_enabled' => false,
                    'has_secret_key' => false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Obtenir la config d'un provider spécifique
     */
    public function show(string $provider): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $config = TenantPaymentConfig::getForProvider($tenantId, $provider);
        
        if (!$config) {
            return response()->json([
                'success' => true,
                'data' => [
                    'provider' => $provider,
                    'environment' => 'sandbox',
                    'is_enabled' => false,
                    'has_secret_key' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $config->toSafeArray(),
        ]);
    }

    /**
     * Configurer un provider PSP
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:fedapay,kkiapay,momo,bank',
            'environment' => 'required|in:sandbox,production',
            'is_enabled' => 'boolean',
            'public_key' => 'nullable|string|max:500',
            'secret_key' => 'nullable|string|max:500',
            'api_user' => 'nullable|string|max:500',      // Pour MoMo
            'webhook_secret' => 'nullable|string|max:500',
            'extra_config' => 'nullable|array',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $config = TenantPaymentConfig::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $validated['provider'],
            ],
            [
                'environment' => $validated['environment'],
                'is_enabled' => $validated['is_enabled'] ?? false,
                'public_key' => $validated['public_key'] ?? null,
                'extra_config' => $validated['extra_config'] ?? null,
            ]
        );

        // Mettre à jour les clés secrètes seulement si fournies
        if (!empty($validated['secret_key'])) {
            $config->secret_key = $validated['secret_key'];
        }
        if (!empty($validated['api_user'])) {
            $config->api_user = $validated['api_user'];
        }
        if (!empty($validated['webhook_secret'])) {
            $config->webhook_secret = $validated['webhook_secret'];
        }
        $config->save();

        \Log::channel('audit')->info('Tenant PSP config updated', [
            'tenant_id' => $tenantId,
            'provider' => $validated['provider'],
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration enregistrée',
            'data' => $config->toSafeArray(),
        ]);
    }

    /**
     * Activer/Désactiver un provider
     */
    public function toggle(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        
        $config = TenantPaymentConfig::getForProvider($tenantId, $provider);
        
        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => 'Provider non configuré',
            ], 400);
        }

        // Vérifier que les clés sont configurées avant d'activer
        if ($validated['enabled'] && empty($config->secret_key_encrypted)) {
            return response()->json([
                'success' => false,
                'error' => 'Veuillez configurer les clés avant d\'activer',
            ], 400);
        }

        $config->update(['is_enabled' => $validated['enabled']]);

        return response()->json([
            'success' => true,
            'message' => $validated['enabled'] ? 'Provider activé' : 'Provider désactivé',
        ]);
    }

    /**
     * Tester la connexion à un provider (sandbox)
     */
    public function test(string $provider): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $config = TenantPaymentConfig::getForProvider($tenantId, $provider);

        if (!$config || empty($config->secret_key)) {
            return response()->json([
                'success' => false,
                'error' => 'Provider non configuré',
            ], 400);
        }

        try {
            $result = match ($provider) {
                'fedapay' => $this->testFedapay($config),
                'kkiapay' => $this->testKkiapay($config),
                'momo' => $this->testMomo($config),
                'bank' => ['success' => true, 'message' => 'Virement bancaire - pas de test API'],
                default => ['success' => false, 'error' => 'Provider inconnu'],
            };

            $config->update([
                'last_test_at' => now(),
                'test_status' => $result['success'] ? 'success' : 'failed',
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            $config->update([
                'last_test_at' => now(),
                'test_status' => 'failed',
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logs webhook du tenant
     */
    public function webhookLogs(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $provider = $request->query('provider');

        $query = \App\Models\WebhookLog::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(50);

        if ($provider) {
            $query->where('provider', $provider);
        }

        $logs = $query->get()->map(fn($log) => [
            'id' => $log->id,
            'provider' => $log->provider,
            'event_type' => $log->event_type,
            'reference' => $log->reference,
            'status' => $log->status,
            'signature_valid' => $log->signature_valid,
            'created_at' => $log->created_at->toIso8601String(),
            'processed_at' => $log->processed_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    // ========================================
    // MÉTHODES DE TEST PRIVÉES
    // ========================================

    private function testFedapay(TenantPaymentConfig $config): array
    {
        $baseUrl = $config->environment === 'sandbox'
            ? 'https://sandbox-api.fedapay.com/v1'
            : 'https://api.fedapay.com/v1';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config->secret_key,
        ])->get("{$baseUrl}/accounts");

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Connexion Fedapay réussie'];
        }

        return ['success' => false, 'error' => 'Échec connexion: ' . $response->status()];
    }

    private function testKkiapay(TenantPaymentConfig $config): array
    {
        $response = Http::withHeaders([
            'x-private-key' => $config->secret_key,
        ])->get('https://api.kkiapay.me/api/v1/transactions?limit=1');

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Connexion Kkiapay réussie'];
        }

        return ['success' => false, 'error' => 'Échec connexion: ' . $response->status()];
    }

    private function testMomo(TenantPaymentConfig $config): array
    {
        $baseUrl = $config->environment === 'sandbox'
            ? 'https://sandbox.momoapi.mtn.com'
            : 'https://proxy.momoapi.mtn.com';

        $extraConfig = $config->extra_config ?? [];
        $subscriptionKey = $extraConfig['subscription_key'] ?? '';

        if (empty($config->api_user) || empty($subscriptionKey)) {
            return ['success' => false, 'error' => 'API User ou Subscription Key manquant'];
        }

        $response = Http::withBasicAuth($config->api_user, $config->secret_key)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->post("{$baseUrl}/collection/token/");

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Connexion MoMo réussie'];
        }

        return ['success' => false, 'error' => 'Échec connexion: ' . $response->status()];
    }
}
