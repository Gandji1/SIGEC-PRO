<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Role;
use App\Traits\ResolveTenantId;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ResolveTenantId;
    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        $users = User::where('tenant_id', $tenantId)->with('roles')->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'string|nullable',
            'role' => 'string|nullable',
            // Affiliations pour les serveurs
            'affiliated_tables' => 'nullable|array',
            'affiliated_tables.*' => 'exists:pos_tables,id',
            'affiliated_pos' => 'nullable|array',
            'affiliated_pos.*' => 'exists:pos,id',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['password'] = bcrypt($validated['password']);

        // Retirer les affiliations des données de création
        $affiliatedTables = $validated['affiliated_tables'] ?? [];
        $affiliatedPos = $validated['affiliated_pos'] ?? [];
        unset($validated['affiliated_tables'], $validated['affiliated_pos']);

        $user = User::create($validated);

        // Synchroniser les affiliations si c'est un serveur
        if (in_array($validated['role'] ?? '', ['pos_server', 'serveur', 'caissier'])) {
            $user->syncAffiliations($affiliatedTables, $affiliatedPos);
        }

        return response()->json([
            'success' => true, 
            'data' => $user->load(['affiliatedTables', 'affiliatedPos'])
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json(['success' => true, 'data' => $user->load('roles')]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'string|nullable',
            'email' => 'email|nullable',
            'phone' => 'string|nullable',
            'role' => 'string|nullable',
            'status' => 'string|nullable',
            'password' => 'string|nullable|min:6',
            // Affiliations pour les serveurs
            'affiliated_tables' => 'nullable|array',
            'affiliated_tables.*' => 'exists:pos_tables,id',
            'affiliated_pos' => 'nullable|array',
            'affiliated_pos.*' => 'exists:pos,id',
        ]);

        // Si password fourni, le hasher
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Gérer les affiliations séparément
        $affiliatedTables = $validated['affiliated_tables'] ?? null;
        $affiliatedPos = $validated['affiliated_pos'] ?? null;
        unset($validated['affiliated_tables'], $validated['affiliated_pos']);

        $user->update(array_filter($validated, fn($v) => $v !== null));

        // Synchroniser les affiliations si fournies
        if ($affiliatedTables !== null || $affiliatedPos !== null) {
            $user->syncAffiliations(
                $affiliatedTables ?? $user->affiliatedTables->pluck('id')->toArray(),
                $affiliatedPos ?? $user->affiliatedPos->pluck('id')->toArray()
            );
        }

        return response()->json([
            'success' => true, 
            'data' => $user->load(['affiliatedTables', 'affiliatedPos'])
        ]);
    }

    /**
     * Réinitialiser le mot de passe d'un utilisateur
     */
    public function resetPassword(User $user)
    {
        $tempPassword = \Str::random(8);
        $user->update(['password' => bcrypt($tempPassword)]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé',
            'temp_password' => $tempPassword,
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['success' => true, 'message' => 'User deleted']);
    }

    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_slug' => 'required|string',
        ]);

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        $role = Role::where('slug', $validated['role_slug'])->first();

        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role not found'], 404);
        }

        $user->roles()->sync([$role->id], false);
        // Also add to user_roles table with tenant_id
        $user->roles()->detach();
        \DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenantId,
        ]);

        return response()->json(['success' => true, 'data' => $user->load('roles')]);
    }

    /**
     * Assigner un utilisateur à un POS
     */
    public function assignPos(Request $request, User $user)
    {
        $validated = $request->validate([
            'pos_id' => 'nullable|exists:pos,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $user->update([
            'assigned_pos_id' => $validated['pos_id'] ?? null,
            'assigned_warehouse_id' => $validated['warehouse_id'] ?? null,
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Assignation mise à jour',
            'data' => $user->load(['assignedPos', 'assignedWarehouse'])
        ]);
    }

    /**
     * Obtenir les serveurs/caissiers assignés à un POS
     */
    public function getByPos(Request $request, $posId)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        
        $users = User::where('tenant_id', $tenantId)
            ->where('assigned_pos_id', $posId)
            ->whereIn('role', ['pos_server', 'caissier'])
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * Obtenir les utilisateurs assignables (serveurs, caissiers, magasiniers)
     */
    public function getAssignable(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        
        $users = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['pos_server', 'caissier', 'magasinier_gros', 'magasinier_detail'])
            ->with(['assignedPos', 'assignedWarehouse'])
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }
}
