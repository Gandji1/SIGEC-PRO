<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CollaboratorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Lister tous les collaborateurs du tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            $query = User::where('tenant_id', $tenantId);

            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $collaborators = $query->orderBy('name')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $collaborators,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un collaborateur
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'phone' => 'nullable|string',
                'role' => 'required|in:owner,manager,accountant,magasinier_gros,magasinier_detail,caissier,pos_server,auditor',
                'status' => 'nullable|in:active,inactive,suspended',
            ]);

            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            // Générer un mot de passe temporaire
            $tempPassword = \Str::random(12);

            $collaborator = User::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($tempPassword),
                'role' => $validated['role'],
                'status' => $validated['status'] ?? 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Collaborateur créé',
                'data' => $collaborator,
                'temp_password' => $tempPassword, // À envoyer par email
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un collaborateur
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            if ($user->tenant_id !== $tenantId) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|nullable|string',
                'role' => 'sometimes|in:owner,manager,accountant,magasinier_gros,magasinier_detail,caissier,pos_server,auditor',
                'status' => 'sometimes|in:active,inactive,suspended',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Collaborateur mis à jour',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un collaborateur (soft delete)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            if ($user->tenant_id !== $tenantId) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            // Ne pas supprimer le propriétaire
            if ($user->role === 'owner' && $user->id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer le propriétaire'], 422);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Collaborateur supprimé',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe d'un collaborateur
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            if ($user->tenant_id !== $tenantId) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $tempPassword = \Str::random(12);
            $user->update(['password' => Hash::make($tempPassword)]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé',
                'temp_password' => $tempPassword,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtenir les rôles disponibles
     */
    public function roles(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'owner', 'label' => 'Propriétaire', 'color' => 'purple'],
                ['value' => 'manager', 'label' => 'Gérant', 'color' => 'orange'],
                ['value' => 'accountant', 'label' => 'Comptable', 'color' => 'yellow'],
                ['value' => 'magasinier_gros', 'label' => 'Magasinier Gros', 'color' => 'green'],
                ['value' => 'magasinier_detail', 'label' => 'Magasinier Détail', 'color' => 'emerald'],
                ['value' => 'caissier', 'label' => 'Caissier', 'color' => 'blue'],
                ['value' => 'pos_server', 'label' => 'Serveur POS', 'color' => 'cyan'],
                ['value' => 'auditor', 'label' => 'Auditeur', 'color' => 'gray'],
            ],
        ]);
    }
}
