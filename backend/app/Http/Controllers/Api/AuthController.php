<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_name' => 'required|unique:tenants,name|string',
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'mode_pos' => 'required|in:A,B',
            'currency' => 'nullable|string|size:3',
            'country' => 'nullable|string',
            'tax_id' => 'nullable|string',
        ]);

        // Créer le tenant
        $tenant = Tenant::create([
            'name' => $validated['tenant_name'],
            'slug' => str()->slug($validated['tenant_name']),
            'status' => 'active',
            'mode_pos' => $validated['mode_pos'],
            'currency' => $validated['currency'] ?? 'XOF',
            'country' => $validated['country'] ?? 'BJ',
            'tax_id' => $validated['tax_id'] ?? null,
            'accounting_enabled' => true,
        ]);

        // Créer les warehouses par défaut selon le mode
        if ($validated['mode_pos'] === 'A') {
            // Mode A: détail + POS (pas gros)
            Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-DETAIL', 'name' => 'Détail', 'type' => 'detail']);
            Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-POS', 'name' => 'POS', 'type' => 'pos']);
        } else {
            // Mode B: gros + détail + POS
            Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-GROS', 'name' => 'Gros', 'type' => 'gros']);
            Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-DETAIL', 'name' => 'Détail', 'type' => 'detail']);
            Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-POS', 'name' => 'POS', 'type' => 'pos']);
        }

        // Créer l'utilisateur owner
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
            'status' => 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Tenant créé avec succès (Mode ' . $validated['mode_pos'] . ')',
            'user' => $user,
            'tenant' => $tenant,
            'warehouses' => $tenant->warehouses,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with('tenant')
            ->where('email', $validated['email'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['Account suspended'],
            ]);
        }

        if (!$user->tenant->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Tenant account not active'],
            ]);
        }

        // Vérifier si 2FA est activé
        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'user_id' => $user->id,
                'message' => 'Veuillez entrer votre code 2FA',
            ]);
        }

        $user->updateLastLogin();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'requires_2fa' => false,
            'message' => 'Login successful',
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token,
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->guard('sanctum')->user()->load('tenant');

        return response()->json([
            'success' => true,
            'user' => $user,
            'tenant' => $user->tenant,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->guard('sanctum')->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->guard('sanctum')->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Invalid password'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['success' => true, 'message' => 'Password changed successfully']);
    }
}
