<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class CreateTenantPos extends Command
{
    protected $signature = 'create:tenant-pos {--count=3 : Number of tenants to create}';

    protected $description = 'Create sample tenants with users and POS/warehouse data';

    public function handle()
    {
        $count = $this->option('count');
        $this->info("Creating $count tenants with POS configurations...\n");

        for ($i = 1; $i <= $count; $i++) {
            $tenantName = "Business {$i}";
            $tenantSlug = "business-{$i}";
            
            // Create tenant
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'domain' => "{$tenantSlug}.localhost",
                'currency' => 'XOF',
                'country' => 'Senegal',
                'phone' => "+221 77 " . str_pad($i * 100, 6, '0', STR_PAD_LEFT),
                'email' => "admin@{$tenantSlug}.local",
                'address' => "Business Address $i, Dakar",
                'business_type' => 'retail',
                'status' => 'active',
                'mode_pos' => 'A',
                'accounting_enabled' => 1,
            ]);

            $this->info("✓ Tenant created: $tenantName (ID: {$tenant->id})");

            // Create admin user
            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => "Admin {$i}",
                'email' => "admin@{$tenantSlug}.local",
                'phone' => "+221 77 " . str_pad($i * 100 + 1, 6, '0', STR_PAD_LEFT),
                'password' => bcrypt('password'),
                'role' => 'owner',
                'status' => 'active',
            ]);

            $this->info("  ├─ Admin user: {$adminUser->email}");

            // Create manager user
            $managerUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => "Manager {$i}",
                'email' => "manager@{$tenantSlug}.local",
                'phone' => "+221 77 " . str_pad($i * 100 + 2, 6, '0', STR_PAD_LEFT),
                'password' => bcrypt('password'),
                'role' => 'manager',
                'status' => 'active',
            ]);

            $this->info("  ├─ Manager user: {$managerUser->email}");

            // Create POS operator user
            $posUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => "POS Operator {$i}",
                'email' => "pos@{$tenantSlug}.local",
                'phone' => "+221 77 " . str_pad($i * 100 + 3, 6, '0', STR_PAD_LEFT),
                'password' => bcrypt('password'),
                'role' => 'caissier',
                'status' => 'active',
            ]);

            $this->info("  ├─ POS Operator: {$posUser->email}");

            // Create warehouse for POS
            $warehouse = Warehouse::create([
                'tenant_id' => $tenant->id,
                'name' => "Main POS - $tenantName",
                'code' => "POS-{$i}",
                'description' => "Point of Sale location for $tenantName",
                'location' => "Dakar Store {$i}",
                'warehouse_type' => 'pos',
                'max_capacity' => 10000,
                'status' => 'active',
            ]);

            $this->info("  └─ POS Warehouse created: {$warehouse->name}\n");
        }

        $this->info("✅ All tenants and POS configurations created successfully!\n");
        
        $this->table(['Tenant', 'Admin Email', 'POS Email', 'Credentials'], [
            ['Business 1', 'admin@business-1.local', 'pos@business-1.local', 'Password: password'],
            ['Business 2', 'admin@business-2.local', 'pos@business-2.local', 'Password: password'],
            ['Business 3', 'admin@business-3.local', 'pos@business-3.local', 'Password: password'],
        ]);
    }
}
