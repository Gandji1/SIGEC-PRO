<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corriger les contraintes unique pour être par tenant
     * Les codes produits, entrepôts, etc. doivent être uniques PAR TENANT, pas globalement
     */
    public function up(): void
    {
        // Pour SQLite, on doit recréer les index
        // Pour MySQL/PostgreSQL, on peut modifier directement
        
        $driver = DB::connection()->getDriverName();
        
        // ========================================
        // PRODUCTS - code unique par tenant
        // ========================================
        if ($driver === 'sqlite') {
            // SQLite: supprimer l'ancien index et créer le nouveau
            try {
                DB::statement('DROP INDEX IF EXISTS products_code_unique');
                DB::statement('DROP INDEX IF EXISTS products_barcode_unique');
            } catch (\Exception $e) {
                // Index peut ne pas exister
            }
        } else {
            // MySQL/PostgreSQL
            Schema::table('products', function (Blueprint $table) {
                try {
                    $table->dropUnique(['code']);
                    $table->dropUnique(['barcode']);
                } catch (\Exception $e) {
                    // Index peut ne pas exister
                }
            });
        }
        
        // Créer les nouveaux index composites (tenant_id + code)
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'products_tenant_code_unique');
            $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
        });

        // ========================================
        // WAREHOUSES - code unique par tenant
        // ========================================
        if (Schema::hasTable('warehouses')) {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('DROP INDEX IF EXISTS warehouses_code_unique');
                } catch (\Exception $e) {}
            } else {
                Schema::table('warehouses', function (Blueprint $table) {
                    try { $table->dropUnique(['code']); } catch (\Exception $e) {}
                });
            }
            
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'warehouses_tenant_code_unique');
            });
        }

        // ========================================
        // POS - code unique par tenant
        // ========================================
        if (Schema::hasTable('pos')) {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('DROP INDEX IF EXISTS pos_code_unique');
                } catch (\Exception $e) {}
            } else {
                Schema::table('pos', function (Blueprint $table) {
                    try { $table->dropUnique(['code']); } catch (\Exception $e) {}
                });
            }
            
            // Vérifier si l'index n'existe pas déjà
            try {
                Schema::table('pos', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'code'], 'pos_tenant_code_unique');
                });
            } catch (\Exception $e) {}
        }

        // ========================================
        // PROMOTIONS - code unique par tenant
        // ========================================
        if (Schema::hasTable('promotions')) {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('DROP INDEX IF EXISTS promotions_code_unique');
                } catch (\Exception $e) {}
            } else {
                Schema::table('promotions', function (Blueprint $table) {
                    try { $table->dropUnique(['code']); } catch (\Exception $e) {}
                });
            }
            
            try {
                Schema::table('promotions', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'code'], 'promotions_tenant_code_unique');
                });
            } catch (\Exception $e) {}
        }

        // ========================================
        // CUSTOMERS - email unique par tenant (pas globalement)
        // ========================================
        if (Schema::hasTable('customers')) {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('DROP INDEX IF EXISTS customers_email_unique');
                } catch (\Exception $e) {}
            } else {
                Schema::table('customers', function (Blueprint $table) {
                    try { $table->dropUnique(['email']); } catch (\Exception $e) {}
                });
            }
            
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'email'], 'customers_tenant_email_unique');
                });
            } catch (\Exception $e) {}
        }

        // ========================================
        // SUPPLIERS - email unique par tenant
        // ========================================
        if (Schema::hasTable('suppliers')) {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('DROP INDEX IF EXISTS suppliers_email_unique');
                } catch (\Exception $e) {}
            } else {
                Schema::table('suppliers', function (Blueprint $table) {
                    try { $table->dropUnique(['email']); } catch (\Exception $e) {}
                });
            }
            
            try {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'email'], 'suppliers_tenant_email_unique');
                });
            } catch (\Exception $e) {}
        }
    }

    public function down(): void
    {
        // Restaurer les contraintes globales (non recommandé)
        // Cette migration ne devrait pas être annulée en production
    }
};
