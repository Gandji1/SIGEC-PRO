<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DatabaseSeeder - PRODUCTION READY
 * 
 * Ce seeder ne crée QUE le SuperAdmin nécessaire pour l'accès initial.
 * Toutes les autres données (plans, tenants, produits, etc.) doivent être
 * créées par les utilisateurs via l'interface.
 * 
 * RÈGLE: Le code fournit les règles, les utilisateurs fournissent les données.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // =============================================
        // SEUL ÉLÉMENT CRÉÉ: LE SUPER ADMIN
        // =============================================
        // Le SuperAdmin est nécessaire pour l'accès initial à la plateforme.
        // Il pourra ensuite créer les plans d'abonnement, les tenants, etc.
        // via l'interface d'administration.
        
        // Créer le tenant système pour le SuperAdmin
        $superAdminTenant = Tenant::updateOrCreate(
            ['slug' => 'system-admin'],
            [
                'name' => 'System Administration',
                'domain' => 'admin.sigec.local',
                'currency' => 'XOF',
                'status' => 'active',
                'business_type' => 'other',
                'mode_pos' => 'A',
                'accounting_enabled' => false,
                'subscription_expires_at' => now()->addYears(100), // Jamais expire
            ]
        );

        // Créer le SuperAdmin
        User::updateOrCreate(
            ['email' => 'super@demo.local'],
            [
                'tenant_id' => $superAdminTenant->id,
                'name' => 'Super Admin',
                'password' => Hash::make('demo12345'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        $this->command->info('✅ Super Admin créé: super@demo.local / demo12345');
        $this->command->info('');
        $this->command->info('📋 PROCHAINES ÉTAPES (via l\'interface):');
        $this->command->info('   1. Connectez-vous en tant que SuperAdmin');
        $this->command->info('   2. Créez les plans d\'abonnement');
        $this->command->info('   3. Créez les tenants (entreprises)');
        $this->command->info('   4. Les tenants créeront leurs propres données');
        $this->command->info('');

        // Appeler le seeder RBAC pour les permissions système
        $this->call(RBACSeeder::class);
    }
}
