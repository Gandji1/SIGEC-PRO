<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::all();
        return response()->json(['success' => true, 'data' => $tenants]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:tenants',
            'domain' => 'required|string|unique:tenants',
            'business_type' => 'required|string',
        ]);

        $tenant = Tenant::create($validated);
        return response()->json(['success' => true, 'data' => $tenant], 201);
    }

    public function show(Tenant $tenant)
    {
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'string',
            'business_type' => 'string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'currency' => 'nullable|string',
            'tva_rate' => 'nullable|numeric|min:0',
            'default_markup' => 'nullable|numeric|min:0',
            'stock_policy' => 'nullable|in:fifo,lifo,cmp',
        ]);

        $tenant->update($validated);
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return response()->json(['success' => true, 'message' => 'Tenant deleted']);
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active']);
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    /**
     * Upload tenant logo
     */
    public function uploadLogo(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Supprimer ancien logo
        if ($tenant->logo && Storage::exists($tenant->logo)) {
            Storage::delete($tenant->logo);
        }

        // Sauvegarder nouveau logo
        $path = $request->file('logo')->store('tenants', 'public');
        $tenant->update(['logo' => $path]);

        return response()->json([
            'success' => true,
            'logo_url' => Storage::url($path),
            'tenant' => $tenant,
        ]);
    }

    /**
     * Delete tenant logo
     */
    public function deleteLogo(Tenant $tenant)
    {
        if ($tenant->logo && Storage::exists($tenant->logo)) {
            Storage::delete($tenant->logo);
        }

        $tenant->update(['logo' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Logo deleted',
        ]);
    }

    /**
     * Get current user's tenant settings
     */
    public function getSettings(Request $request)
    {
        $user = auth()->guard('sanctum')->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'currency' => $tenant->currency ?? 'XOF',
                'timezone' => $tenant->settings['timezone'] ?? 'Africa/Porto-Novo',
                'language' => $tenant->settings['language'] ?? 'fr',
                'auto_invoice' => $tenant->settings['auto_invoice'] ?? true,
                'stock_movement_log' => $tenant->settings['stock_movement_log'] ?? true,
                'tva_rate' => $tenant->tva_rate ?? 18,
                'default_markup' => $tenant->default_markup ?? 30,
                'stock_policy' => $tenant->stock_policy ?? 'fifo',
            ]
        ]);
    }

    /**
     * Update current user's tenant settings
     */
    public function updateSettings(Request $request)
    {
        $user = auth()->guard('sanctum')->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'currency' => 'nullable|string|in:XOF,XAF,EUR,USD',
            'timezone' => 'nullable|string',
            'language' => 'nullable|string|in:fr,en',
            'auto_invoice' => 'nullable|boolean',
            'stock_movement_log' => 'nullable|boolean',
            'tva_rate' => 'nullable|numeric|min:0|max:100',
            'default_markup' => 'nullable|numeric|min:0',
            'stock_policy' => 'nullable|string|in:fifo,lifo,cmp',
        ]);

        // Update main fields
        $tenant->name = $validated['company_name'] ?? $tenant->name;
        $tenant->email = $validated['email'] ?? $tenant->email;
        $tenant->phone = $validated['phone'] ?? $tenant->phone;
        $tenant->address = $validated['address'] ?? $tenant->address;
        $tenant->currency = $validated['currency'] ?? $tenant->currency;
        $tenant->tva_rate = $validated['tva_rate'] ?? $tenant->tva_rate;
        $tenant->default_markup = $validated['default_markup'] ?? $tenant->default_markup;
        $tenant->stock_policy = $validated['stock_policy'] ?? $tenant->stock_policy;

        // Update settings JSON
        $settings = $tenant->settings ?? [];
        if (isset($validated['timezone'])) $settings['timezone'] = $validated['timezone'];
        if (isset($validated['language'])) $settings['language'] = $validated['language'];
        if (isset($validated['auto_invoice'])) $settings['auto_invoice'] = $validated['auto_invoice'];
        if (isset($validated['stock_movement_log'])) $settings['stock_movement_log'] = $validated['stock_movement_log'];
        $tenant->settings = $settings;

        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Paramètres sauvegardés',
            'data' => $tenant
        ]);
    }

    /**
     * Get PSP settings for current tenant
     */
    public function getPspSettings(Request $request)
    {
        $user = auth()->guard('sanctum')->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        $settings = $tenant->settings ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'fedapay_enabled' => $settings['fedapay_enabled'] ?? false,
                'fedapay_api_key' => $settings['fedapay_api_key'] ?? '',
                'fedapay_environment' => $settings['fedapay_environment'] ?? 'sandbox',
                'kakiapay_enabled' => $settings['kakiapay_enabled'] ?? false,
                'kakiapay_api_key' => $settings['kakiapay_api_key'] ?? '',
                'kakiapay_environment' => $settings['kakiapay_environment'] ?? 'sandbox',
            ]
        ]);
    }

    /**
     * Update PSP settings for current tenant
     */
    public function updatePspSettings(Request $request)
    {
        $user = auth()->guard('sanctum')->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant non trouvé'], 404);
        }

        $validated = $request->validate([
            'fedapay_enabled' => 'nullable|boolean',
            'fedapay_api_key' => 'nullable|string',
            'fedapay_environment' => 'nullable|string|in:sandbox,live',
            'kakiapay_enabled' => 'nullable|boolean',
            'kakiapay_api_key' => 'nullable|string',
            'kakiapay_environment' => 'nullable|string|in:sandbox,production',
        ]);

        $settings = $tenant->settings ?? [];
        
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        $tenant->settings = $settings;
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Paramètres PSP sauvegardés',
        ]);
    }
}
