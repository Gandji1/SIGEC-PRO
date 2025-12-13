<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RBACSeeder extends Seeder
{
    /**
     * Define all permissions
     */
    private function getPermissions()
    {
        return [
            // SUPER ADMIN - Platform Management
            ['name' => 'Manage Tenants', 'slug' => 'tenant.create', 'resource' => 'tenant', 'action' => 'create'],
            ['name' => 'View Tenants', 'slug' => 'tenant.view', 'resource' => 'tenant', 'action' => 'view'],
            ['name' => 'Edit Tenants', 'slug' => 'tenant.edit', 'resource' => 'tenant', 'action' => 'edit'],
            ['name' => 'Delete Tenants', 'slug' => 'tenant.delete', 'resource' => 'tenant', 'action' => 'delete'],
            ['name' => 'Suspend Tenant', 'slug' => 'tenant.suspend', 'resource' => 'tenant', 'action' => 'suspend'],
            
            ['name' => 'Manage Plans', 'slug' => 'plan.create', 'resource' => 'plan', 'action' => 'create'],
            ['name' => 'View Plans', 'slug' => 'plan.view', 'resource' => 'plan', 'action' => 'view'],
            ['name' => 'Edit Plans', 'slug' => 'plan.edit', 'resource' => 'plan', 'action' => 'edit'],
            
            ['name' => 'Configure PSP', 'slug' => 'psp.configure', 'resource' => 'psp', 'action' => 'configure'],
            ['name' => 'View PSP Webhooks', 'slug' => 'psp.webhooks', 'resource' => 'psp', 'action' => 'view_webhooks'],
            
            ['name' => 'System Monitoring', 'slug' => 'system.monitor', 'resource' => 'system', 'action' => 'monitor'],
            ['name' => 'System Logs', 'slug' => 'system.logs', 'resource' => 'system', 'action' => 'logs'],
            ['name' => 'Impersonate User', 'slug' => 'system.impersonate', 'resource' => 'system', 'action' => 'impersonate'],

            // OWNER - Full Tenant Access
            ['name' => 'Manage Users', 'slug' => 'user.create', 'resource' => 'user', 'action' => 'create'],
            ['name' => 'View Users', 'slug' => 'user.view', 'resource' => 'user', 'action' => 'view'],
            ['name' => 'Edit Users', 'slug' => 'user.edit', 'resource' => 'user', 'action' => 'edit'],
            ['name' => 'Delete Users', 'slug' => 'user.delete', 'resource' => 'user', 'action' => 'delete'],
            ['name' => 'Assign Roles', 'slug' => 'user.assign_role', 'resource' => 'user', 'action' => 'assign_role'],

            ['name' => 'Configure Tenant', 'slug' => 'tenant_settings.edit', 'resource' => 'tenant_settings', 'action' => 'edit'],
            ['name' => 'View Tenant Settings', 'slug' => 'tenant_settings.view', 'resource' => 'tenant_settings', 'action' => 'view'],

            // PURCHASES & SUPPLIES
            ['name' => 'Create Purchase', 'slug' => 'purchase.create', 'resource' => 'purchase', 'action' => 'create'],
            ['name' => 'View Purchase', 'slug' => 'purchase.view', 'resource' => 'purchase', 'action' => 'view'],
            ['name' => 'Edit Purchase', 'slug' => 'purchase.edit', 'resource' => 'purchase', 'action' => 'edit'],
            ['name' => 'Receive Purchase', 'slug' => 'purchase.receive', 'resource' => 'purchase', 'action' => 'receive'],
            ['name' => 'Cancel Purchase', 'slug' => 'purchase.cancel', 'resource' => 'purchase', 'action' => 'cancel'],

            ['name' => 'Manage Suppliers', 'slug' => 'supplier.manage', 'resource' => 'supplier', 'action' => 'manage'],
            ['name' => 'View Suppliers', 'slug' => 'supplier.view', 'resource' => 'supplier', 'action' => 'view'],

            // SALES & POS
            ['name' => 'Create Sale', 'slug' => 'sale.create', 'resource' => 'sale', 'action' => 'create'],
            ['name' => 'View Sale', 'slug' => 'sale.view', 'resource' => 'sale', 'action' => 'view'],
            ['name' => 'Complete Sale', 'slug' => 'sale.complete', 'resource' => 'sale', 'action' => 'complete'],
            ['name' => 'Cancel Sale', 'slug' => 'sale.cancel', 'resource' => 'sale', 'action' => 'cancel'],
            ['name' => 'Apply Discount', 'slug' => 'sale.discount', 'resource' => 'sale', 'action' => 'discount'],

            ['name' => 'Access POS', 'slug' => 'pos.access', 'resource' => 'pos', 'action' => 'access'],
            ['name' => 'Close POS Session', 'slug' => 'pos.close_session', 'resource' => 'pos', 'action' => 'close_session'],

            // INVENTORY & STOCK
            ['name' => 'View Stock', 'slug' => 'stock.view', 'resource' => 'stock', 'action' => 'view'],
            ['name' => 'Adjust Stock', 'slug' => 'stock.adjust', 'resource' => 'stock', 'action' => 'adjust'],
            ['name' => 'Transfer Stock', 'slug' => 'stock.transfer', 'resource' => 'stock', 'action' => 'transfer'],
            ['name' => 'Approve Transfer', 'slug' => 'stock.approve_transfer', 'resource' => 'stock', 'action' => 'approve_transfer'],

            ['name' => 'Create Inventory', 'slug' => 'inventory.create', 'resource' => 'inventory', 'action' => 'create'],
            ['name' => 'Complete Inventory', 'slug' => 'inventory.complete', 'resource' => 'inventory', 'action' => 'complete'],
            ['name' => 'View Inventory', 'slug' => 'inventory.view', 'resource' => 'inventory', 'action' => 'view'],

            ['name' => 'Manage Warehouses', 'slug' => 'warehouse.manage', 'resource' => 'warehouse', 'action' => 'manage'],
            ['name' => 'View Warehouse', 'slug' => 'warehouse.view', 'resource' => 'warehouse', 'action' => 'view'],

            // PRODUCTS
            ['name' => 'Manage Products', 'slug' => 'product.manage', 'resource' => 'product', 'action' => 'manage'],
            ['name' => 'View Products', 'slug' => 'product.view', 'resource' => 'product', 'action' => 'view'],
            ['name' => 'Create Product', 'slug' => 'product.create', 'resource' => 'product', 'action' => 'create'],
            ['name' => 'Edit Product', 'slug' => 'product.edit', 'resource' => 'product', 'action' => 'edit'],
            ['name' => 'Delete Product', 'slug' => 'product.delete', 'resource' => 'product', 'action' => 'delete'],

            // ACCOUNTING & FINANCE
            ['name' => 'View Accounting', 'slug' => 'accounting.view', 'resource' => 'accounting', 'action' => 'view'],
            ['name' => 'Edit Accounting Entry', 'slug' => 'accounting.edit', 'resource' => 'accounting', 'action' => 'edit'],
            ['name' => 'Post Entry', 'slug' => 'accounting.post', 'resource' => 'accounting', 'action' => 'post'],
            ['name' => 'View Reports', 'slug' => 'report.view', 'resource' => 'report', 'action' => 'view'],
            ['name' => 'Export Reports', 'slug' => 'report.export', 'resource' => 'report', 'action' => 'export'],

            ['name' => 'Create Expense', 'slug' => 'expense.create', 'resource' => 'expense', 'action' => 'create'],
            ['name' => 'View Expense', 'slug' => 'expense.view', 'resource' => 'expense', 'action' => 'view'],
            ['name' => 'Edit Expense', 'slug' => 'expense.edit', 'resource' => 'expense', 'action' => 'edit'],
            ['name' => 'Delete Expense', 'slug' => 'expense.delete', 'resource' => 'expense', 'action' => 'delete'],

            // PAYMENTS
            ['name' => 'Process Payment', 'slug' => 'payment.process', 'resource' => 'payment', 'action' => 'process'],
            ['name' => 'Verify Payment', 'slug' => 'payment.verify', 'resource' => 'payment', 'action' => 'verify'],
            ['name' => 'View Payment', 'slug' => 'payment.view', 'resource' => 'payment', 'action' => 'view'],
        ];
    }

    /**
     * Define all roles with their permissions
     */
    private function getRolePermissions()
    {
        return [
            'super_admin' => [
                'tenant.create', 'tenant.view', 'tenant.edit', 'tenant.delete', 'tenant.suspend',
                'plan.create', 'plan.view', 'plan.edit',
                'psp.configure', 'psp.webhooks',
                'system.monitor', 'system.logs', 'system.impersonate',
                'user.create', 'user.view', 'user.edit', 'user.delete', 'user.assign_role',
                'tenant_settings.edit', 'tenant_settings.view',
                'purchase.view', 'sale.view', 'stock.view',
                'report.view', 'report.export',
                'accounting.view',
            ],
            'owner' => [
                'user.create', 'user.view', 'user.edit', 'user.delete', 'user.assign_role',
                'tenant_settings.edit', 'tenant_settings.view',
                'purchase.create', 'purchase.view', 'purchase.edit', 'purchase.receive', 'purchase.cancel',
                'supplier.manage', 'supplier.view',
                'sale.create', 'sale.view', 'sale.complete', 'sale.cancel', 'sale.discount',
                'stock.view', 'stock.adjust', 'stock.transfer', 'stock.approve_transfer',
                'inventory.create', 'inventory.complete', 'inventory.view',
                'warehouse.manage', 'warehouse.view',
                'product.manage', 'product.view', 'product.create', 'product.edit', 'product.delete',
                'accounting.view', 'accounting.edit', 'accounting.post',
                'report.view', 'report.export',
                'expense.create', 'expense.view', 'expense.edit', 'expense.delete',
                'payment.process', 'payment.verify', 'payment.view',
            ],
            'manager' => [
                'purchase.create', 'purchase.view', 'purchase.edit', 'purchase.receive', 'purchase.cancel',
                'supplier.view',
                'sale.view', 'sale.complete',
                'stock.view', 'stock.adjust', 'stock.transfer', 'stock.approve_transfer',
                'inventory.view',
                'warehouse.view',
                'product.view',
                'report.view',
                'expense.view',
            ],
            'accountant' => [
                'sale.view', 'purchase.view',
                'accounting.view', 'accounting.edit',
                'report.view', 'report.export',
                'expense.create', 'expense.view', 'expense.edit',
                'payment.view',
            ],
            'warehouse' => [
                'purchase.receive',
                'stock.view', 'stock.adjust', 'stock.transfer',
                'inventory.create', 'inventory.complete', 'inventory.view',
                'warehouse.view',
                'product.view',
            ],
            'cashier' => [
                'sale.view',
                'pos.access', 'pos.close_session',
                'stock.view',
                'payment.process', 'payment.verify', 'payment.view',
            ],
            'pos_server' => [
                'sale.create', 'sale.view', 'sale.discount',
                'pos.access',
                'stock.view',
                'product.view',
                'payment.process',
            ],
            'auditor' => [
                'sale.view', 'purchase.view',
                'stock.view',
                'accounting.view',
                'report.view',
                'report.export',
                'expense.view',
                'payment.view',
            ],
        ];
    }

    public function run(): void
    {
        // Create all permissions
        $permissions = $this->getPermissions();
        $permissionMap = [];

        foreach ($permissions as $perm) {
            $permissionMap[$perm['slug']] = Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // Create all roles with their permissions
        $rolePermissions = $this->getRolePermissions();

        foreach ($rolePermissions as $roleSlug => $perms) {
            $role = Role::firstOrCreate(
                ['slug' => $roleSlug, 'is_system' => true],
                [
                    'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
                    'description' => "System role: {$roleSlug}",
                    'is_system' => true,
                ]
            );

            // Attach permissions to role
            foreach ($perms as $permSlug) {
                if (isset($permissionMap[$permSlug])) {
                    $role->permissions()->syncWithoutDetaching([$permissionMap[$permSlug]->id]);
                }
            }
        }

        $this->command->info('✅ RBAC seeded successfully');
        $this->command->info("Created " . count($permissions) . " permissions");
        $this->command->info("Created " . count($rolePermissions) . " roles");
    }
}
