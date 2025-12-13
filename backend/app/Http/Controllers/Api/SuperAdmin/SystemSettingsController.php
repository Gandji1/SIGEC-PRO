<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\System\SystemSetting;
use App\Models\System\Module;
use App\Models\System\TenantModule;
use App\Models\System\SystemLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemSettingsController extends Controller
{
    /**
     * Récupérer tous les paramètres
     */
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');

        $query = SystemSetting::query();
        if ($group) {
            $query->where('group', $group);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        // Grouper par catégorie
        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->mapWithKeys(fn($s) => [$s->key => [
                'value' => $this->castValue($s->value, $s->type),
                'type' => $s->type,
                'description' => $s->description,
            ]]);
        });

        return response()->json(['data' => $grouped]);
    }

    /**
     * Récupérer un paramètre
     */
    public function show(string $key): JsonResponse
    {
        $setting = SystemSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['error' => 'Paramètre non trouvé'], 404);
        }

        return response()->json([
            'data' => [
                'key' => $setting->key,
                'value' => $this->castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Mettre à jour un ou plusieurs paramètres
     */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $data) {
            $value = is_array($data) ? ($data['value'] ?? $data) : $data;
            $type = is_array($data) ? ($data['type'] ?? 'string') : 'string';
            $group = is_array($data) ? ($data['group'] ?? 'general') : 'general';
            $description = is_array($data) ? ($data['description'] ?? null) : null;

            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                    'type' => $type,
                    'group' => $group,
                    'description' => $description,
                ]
            );
        }

        SystemLog::log('Paramètres système mis à jour', 'info', 'system', null, ['keys' => array_keys($settings)]);

        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour',
        ]);
    }

    /**
     * Initialiser les paramètres par défaut
     */
    public function initDefaults(): JsonResponse
    {
        $defaults = [
            // Général
            ['key' => 'platform_name', 'value' => 'SIGEC', 'type' => 'string', 'group' => 'general', 'description' => 'Nom de la plateforme'],
            ['key' => 'platform_email', 'value' => 'support@sigec.com', 'type' => 'string', 'group' => 'general', 'description' => 'Email support'],
            ['key' => 'platform_phone', 'value' => '+229 00 00 00 00', 'type' => 'string', 'group' => 'general', 'description' => 'Téléphone support'],
            ['key' => 'default_currency', 'value' => 'XOF', 'type' => 'string', 'group' => 'general', 'description' => 'Devise par défaut'],
            ['key' => 'default_tva_rate', 'value' => '18', 'type' => 'float', 'group' => 'general', 'description' => 'Taux TVA par défaut'],
            ['key' => 'default_timezone', 'value' => 'Africa/Porto-Novo', 'type' => 'string', 'group' => 'general', 'description' => 'Fuseau horaire par défaut'],

            // Email
            ['key' => 'smtp_host', 'value' => '', 'type' => 'string', 'group' => 'email', 'description' => 'Serveur SMTP'],
            ['key' => 'smtp_port', 'value' => '587', 'type' => 'integer', 'group' => 'email', 'description' => 'Port SMTP'],
            ['key' => 'smtp_username', 'value' => '', 'type' => 'string', 'group' => 'email', 'description' => 'Utilisateur SMTP'],
            ['key' => 'smtp_password', 'value' => '', 'type' => 'string', 'group' => 'email', 'description' => 'Mot de passe SMTP'],
            ['key' => 'mail_from_address', 'value' => 'noreply@sigec.com', 'type' => 'string', 'group' => 'email', 'description' => 'Adresse expéditeur'],
            ['key' => 'mail_from_name', 'value' => 'SIGEC', 'type' => 'string', 'group' => 'email', 'description' => 'Nom expéditeur'],

            // SMS
            ['key' => 'sms_provider', 'value' => '', 'type' => 'string', 'group' => 'sms', 'description' => 'Fournisseur SMS'],
            ['key' => 'sms_api_key', 'value' => '', 'type' => 'string', 'group' => 'sms', 'description' => 'Clé API SMS'],
            ['key' => 'sms_sender_id', 'value' => 'SIGEC', 'type' => 'string', 'group' => 'sms', 'description' => 'ID expéditeur SMS'],

            // Paiement - Environnement
            ['key' => 'payment_environment', 'value' => 'sandbox', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Environnement (sandbox/production)'],
            
            // Fedapay
            ['key' => 'fedapay_public_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Fedapay clé publique'],
            ['key' => 'fedapay_secret_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Fedapay clé secrète'],
            
            // Kkiapay
            ['key' => 'kkiapay_public_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Kkiapay clé publique'],
            ['key' => 'kkiapay_private_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Kkiapay clé privée'],
            ['key' => 'kkiapay_secret', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Kkiapay secret'],
            
            // MTN MoMo
            ['key' => 'momo_subscription_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'MoMo Subscription Key'],
            ['key' => 'momo_api_user', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'MoMo API User'],
            ['key' => 'momo_api_key', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'MoMo API Key'],
            
            // PayPal
            ['key' => 'paypal_client_id', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'PayPal Client ID'],
            ['key' => 'paypal_secret', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'PayPal Secret'],
            
            // Virement bancaire
            ['key' => 'bank_name', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'Nom de la banque'],
            ['key' => 'bank_iban', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'IBAN'],
            ['key' => 'bank_bic', 'value' => '', 'type' => 'string', 'group' => 'payment_gateways', 'description' => 'BIC/SWIFT'],

            // Sécurité
            ['key' => 'password_min_length', 'value' => '8', 'type' => 'integer', 'group' => 'security', 'description' => 'Longueur min mot de passe'],
            ['key' => 'session_lifetime_minutes', 'value' => '120', 'type' => 'integer', 'group' => 'security', 'description' => 'Durée session (minutes)'],
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'description' => 'Tentatives connexion max'],
            ['key' => 'lockout_duration_minutes', 'value' => '15', 'type' => 'integer', 'group' => 'security', 'description' => 'Durée blocage (minutes)'],

            // Backup
            ['key' => 'backup_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'backup', 'description' => 'Backup automatique activé'],
            ['key' => 'backup_frequency', 'value' => 'daily', 'type' => 'string', 'group' => 'backup', 'description' => 'Fréquence backup'],
            ['key' => 'backup_retention_days', 'value' => '30', 'type' => 'integer', 'group' => 'backup', 'description' => 'Rétention backup (jours)'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Paramètres par défaut initialisés',
        ]);
    }

    /**
     * Liste des modules
     */
    public function modules(): JsonResponse
    {
        $modules = Module::orderBy('sort_order')->get();

        return response()->json(['data' => $modules]);
    }

    /**
     * Créer/Modifier un module
     */
    public function saveModule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:system_modules,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_core' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'extra_price' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $module = Module::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
    }

    /**
     * Initialiser les modules par défaut
     */
    public function initModules(): JsonResponse
    {
        $modules = [
            ['code' => 'pos', 'name' => 'Point de Vente', 'description' => 'Caisse et ventes', 'icon' => 'ShoppingCart', 'is_core' => true, 'sort_order' => 1],
            ['code' => 'stock', 'name' => 'Gestion Stock', 'description' => 'Inventaire et mouvements', 'icon' => 'Package', 'is_core' => true, 'sort_order' => 2],
            ['code' => 'accounting', 'name' => 'Comptabilité', 'description' => 'États financiers', 'icon' => 'Calculator', 'is_core' => false, 'sort_order' => 3],
            ['code' => 'invoicing', 'name' => 'Facturation', 'description' => 'Factures et devis', 'icon' => 'FileText', 'is_core' => false, 'sort_order' => 4],
            ['code' => 'hr', 'name' => 'Ressources Humaines', 'description' => 'Gestion du personnel', 'icon' => 'Users', 'is_core' => false, 'extra_price' => 5000, 'sort_order' => 5],
            ['code' => 'suppliers', 'name' => 'Fournisseurs', 'description' => 'Gestion fournisseurs', 'icon' => 'Truck', 'is_core' => true, 'sort_order' => 6],
            ['code' => 'customers', 'name' => 'Clients', 'description' => 'Gestion clients', 'icon' => 'UserCheck', 'is_core' => true, 'sort_order' => 7],
            ['code' => 'reports', 'name' => 'Rapports', 'description' => 'Rapports et analyses', 'icon' => 'BarChart', 'is_core' => true, 'sort_order' => 8],
            ['code' => 'export', 'name' => 'Export', 'description' => 'Export Excel/PDF', 'icon' => 'Download', 'is_core' => false, 'sort_order' => 9],
            ['code' => 'multi_warehouse', 'name' => 'Multi-Entrepôts', 'description' => 'Plusieurs entrepôts', 'icon' => 'Building', 'is_core' => false, 'extra_price' => 10000, 'sort_order' => 10],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(
                ['code' => $module['code']],
                $module
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Modules initialisés',
        ]);
    }

    /**
     * Modules d'un tenant
     */
    public function tenantModules(Tenant $tenant): JsonResponse
    {
        $allModules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $enabledModules = TenantModule::where('tenant_id', $tenant->id)
            ->where('is_enabled', true)
            ->pluck('module_id')
            ->toArray();

        $modules = $allModules->map(function ($module) use ($enabledModules) {
            return [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'icon' => $module->icon,
                'is_core' => $module->is_core,
                'extra_price' => $module->extra_price,
                'is_enabled' => in_array($module->id, $enabledModules) || $module->is_core,
            ];
        });

        return response()->json(['data' => $modules]);
    }

    /**
     * Activer/Désactiver un module pour un tenant
     */
    public function toggleTenantModule(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:system_modules,id',
            'enabled' => 'required|boolean',
        ]);

        $module = Module::find($validated['module_id']);

        // Les modules core ne peuvent pas être désactivés
        if ($module->is_core && !$validated['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Les modules essentiels ne peuvent pas être désactivés',
            ], 400);
        }

        TenantModule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_id' => $module->id],
            [
                'is_enabled' => $validated['enabled'],
                'enabled_at' => $validated['enabled'] ? now() : null,
                'disabled_at' => !$validated['enabled'] ? now() : null,
            ]
        );

        SystemLog::log(
            $validated['enabled'] ? "Module activé: {$module->name}" : "Module désactivé: {$module->name}",
            'info',
            'tenant',
            null,
            ['tenant_id' => $tenant->id, 'module_id' => $module->id]
        );

        return response()->json([
            'success' => true,
            'message' => $validated['enabled'] ? 'Module activé' : 'Module désactivé',
        ]);
    }

    protected function castValue($value, string $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }
}
