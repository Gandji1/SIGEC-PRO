<?php

namespace App\Services;

use App\Models\User;

class AuthorizationService
{
    /**
     * Check if user has permission
     */
    public static function can(User $user, string $permission): bool
    {
        // Super admin has all permissions
        if ($user->role === 'super_admin') {
            return true;
        }

        // Check role-based permissions
        if ($user->role && method_exists(self::class, "can_{$user->role}")) {
            return call_user_func([self::class, "can_{$user->role}"], $permission);
        }

        return false;
    }

    /**
     * Check if user owns resource (tenant)
     */
    public static function owns(User $user, $resource): bool
    {
        if (!$resource) {
            return false;
        }

        // Check if resource has tenant_id and matches user's tenant
        if (property_exists($resource, 'tenant_id')) {
            return $resource->tenant_id === $user->tenant_id;
        }

        return false;
    }

    // OWNER permissions
    private static function can_owner(string $permission): bool
    {
        $ownerPerms = [
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
        ];
        return in_array($permission, $ownerPerms);
    }

    // MANAGER permissions
    private static function can_manager(string $permission): bool
    {
        $managerPerms = [
            'purchase.create', 'purchase.view', 'purchase.edit', 'purchase.receive', 'purchase.cancel',
            'supplier.view',
            'sale.view', 'sale.complete',
            'stock.view', 'stock.adjust', 'stock.transfer', 'stock.approve_transfer',
            'inventory.view',
            'warehouse.view',
            'product.view',
            'report.view',
            'expense.view',
        ];
        return in_array($permission, $managerPerms);
    }

    // ACCOUNTANT permissions
    private static function can_accountant(string $permission): bool
    {
        $accountantPerms = [
            'sale.view', 'purchase.view',
            'accounting.view', 'accounting.edit',
            'report.view', 'report.export',
            'expense.create', 'expense.view', 'expense.edit',
            'payment.view',
        ];
        return in_array($permission, $accountantPerms);
    }

    // WAREHOUSE permissions
    private static function can_warehouse(string $permission): bool
    {
        $warehousePerms = [
            'purchase.receive',
            'stock.view', 'stock.adjust', 'stock.transfer',
            'inventory.create', 'inventory.complete', 'inventory.view',
            'warehouse.view',
            'product.view',
        ];
        return in_array($permission, $warehousePerms);
    }

    // CASHIER permissions
    private static function can_cashier(string $permission): bool
    {
        $cashierPerms = [
            'sale.view',
            'pos.access', 'pos.close_session',
            'stock.view',
            'payment.process', 'payment.verify', 'payment.view',
        ];
        return in_array($permission, $cashierPerms);
    }

    // POS SERVER permissions
    private static function can_pos_server(string $permission): bool
    {
        $posPerms = [
            'sale.create', 'sale.view', 'sale.discount',
            'pos.access',
            'stock.view',
            'product.view',
            'payment.process',
        ];
        return in_array($permission, $posPerms);
    }

    // AUDITOR permissions (read-only)
    private static function can_auditor(string $permission): bool
    {
        $auditorPerms = [
            'sale.view', 'purchase.view',
            'stock.view',
            'accounting.view',
            'report.view',
            'report.export',
            'expense.view',
            'payment.view',
        ];
        return in_array($permission, $auditorPerms);
    }
}
