<?php

namespace App\Domains\Tenants\Services;

use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class TenantService
{
    /**
     * Créer un tenant complet avec sa structure interne
     * 
     * Option A (Bars, restaurants): gros + détail + POS (detail warehouse)
     * Option B (Multi-sites): gros + détail + POS warehouse séparé
     */
    public function createWithStructure(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            // 1. Créer le tenant
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => str()->slug($data['name']),
                'domain' => $data['domain'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'currency' => $data['currency'] ?? 'XOF',
                'country' => $data['country'] ?? 'BJ',
                'mode_pos' => $data['mode_pos'] ?? 'B',
                'accounting_enabled' => true,
                'accounting_setup_complete' => false,
                'status' => 'active',
                'business_type' => $data['business_type'] ?? 'retail',
                // Config défaut
                'tva_rate' => $data['tva_rate'] ?? 18.00,
                'default_markup' => $data['markup'] ?? 30.00,
                'stock_policy' => $data['stock_policy'] ?? 'cmp',
                'allow_credit' => $data['allow_credit'] ?? false,
                'credit_limit' => $data['credit_limit'] ?? 0,
            ]);

            // 2. Créer les warehouses selon le mode
            $this->createWarehousesForMode($tenant);

            // 3. Initialiser les permissions et rôles pour ce tenant
            $this->initializeRolesAndPermissions($tenant);

            return $tenant;
        });
    }

    /**
     * Créer les warehouses selon le mode d'opération
     */
    private function createWarehousesForMode(Tenant $tenant): void
    {
        $mode = $tenant->mode_pos;

        // Mode A: Magasin detail + POS liés au detail
        // Mode B: Magasin gros + Magasin detail + POS warehouse séparé

        if ($mode === 'A') {
            // Mode A: simple (bar/buvette/resto simple)
            // 1 warehouse detail qui gère les POS
            Warehouse::create([
                'tenant_id' => $tenant->id,
                'code' => 'WH-DETAIL',
                'name' => 'Magasin Détail',
                'type' => 'detail',
                'location' => $tenant->address ?? 'Siège',
                'is_active' => true,
            ]);

            // POS warehouse (peut être lié à plusieurs POS)
            Warehouse::create([
                'tenant_id' => $tenant->id,
                'code' => 'WH-POS',
                'name' => 'POS',
                'type' => 'pos',
                'location' => 'Point de vente',
                'is_active' => true,
            ]);
        } else {
            // Mode B: structure complète
            // Magasin gros
            Warehouse::create([
                'tenant_id' => $tenant->id,
                'code' => 'WH-GROS',
                'name' => 'Magasin Gros',
                'type' => 'gros',
                'location' => $tenant->address ?? 'Siège',
                'is_active' => true,
            ]);

            // Magasin détail
            Warehouse::create([
                'tenant_id' => $tenant->id,
                'code' => 'WH-DETAIL',
                'name' => 'Magasin Détail',
                'type' => 'detail',
                'location' => $tenant->address ?? 'Détail',
                'is_active' => true,
            ]);

            // POS warehouse
            Warehouse::create([
                'tenant_id' => $tenant->id,
                'code' => 'WH-POS',
                'name' => 'POS',
                'type' => 'pos',
                'location' => 'Point de vente',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Initialiser les permissions et rôles pour ce tenant
     */
    private function initializeRolesAndPermissions(Tenant $tenant): void
    {
        $roles = [
            ['name' => 'owner', 'description' => 'Propriétaire/Gestionnaire', 'permissions' => 'all'],
            ['name' => 'manager', 'description' => 'Directeur', 'permissions' => ['all_except_delete']],
            ['name' => 'accountant', 'description' => 'Comptable', 'permissions' => ['accounting', 'reports']],
            ['name' => 'magasinier_gros', 'description' => 'Magasinier Gros', 'permissions' => ['warehouse_gros']],
            ['name' => 'magasinier_detail', 'description' => 'Magasinier Détail', 'permissions' => ['warehouse_detail']],
            ['name' => 'caissier', 'description' => 'Caissier', 'permissions' => ['cash', 'sales']],
            ['name' => 'pos_server', 'description' => 'Serveur POS', 'permissions' => ['pos', 'sales']],
            ['name' => 'auditor', 'description' => 'Auditeur', 'permissions' => ['reports', 'view_all']],
        ];

        foreach ($roles as $roleData) {
            Role::create([
                'tenant_id' => $tenant->id,
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'metadata' => ['permissions_pattern' => $roleData['permissions']],
            ]);
        }
    }

    /**
     * Obtenir la structure warehouses d'un tenant
     */
    public function getTenantStructure(Tenant $tenant): array
    {
        return [
            'mode' => $tenant->mode_pos,
            'warehouses' => $tenant->warehouses()
                ->where('is_active', true)
                ->get(['id', 'code', 'name', 'type', 'location'])
                ->toArray(),
            'configuration' => [
                'tva_rate' => $tenant->tva_rate,
                'default_markup' => $tenant->default_markup,
                'stock_policy' => $tenant->stock_policy,
                'currency' => $tenant->currency,
            ],
        ];
    }

    /**
     * Vérifier intégrité structure d'un tenant
     */
    public function validateStructure(Tenant $tenant): bool
    {
        $warehouses = $tenant->warehouses()
            ->where('is_active', true)
            ->get();

        // Mode A doit avoir: detail + pos
        if ($tenant->mode_pos === 'A') {
            $hasDetail = $warehouses->where('type', 'detail')->exists();
            $hasPos = $warehouses->where('type', 'pos')->exists();
            return $hasDetail && $hasPos;
        }

        // Mode B doit avoir: gros + detail + pos
        if ($tenant->mode_pos === 'B') {
            $hasGros = $warehouses->where('type', 'gros')->exists();
            $hasDetail = $warehouses->where('type', 'detail')->exists();
            $hasPos = $warehouses->where('type', 'pos')->exists();
            return $hasGros && $hasDetail && $hasPos;
        }

        return false;
    }
}
