<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration pour ajouter les index de performance critiques
 * Ces index accélèrent les requêtes les plus fréquentes de l'application
 */
return new class extends Migration
{
    /**
     * Créer un index seulement s'il n'existe pas
     */
    private function createIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} (" . implode(', ', $columns) . ")");
        } elseif ($driver === 'mysql') {
            $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            if (empty($exists)) {
                Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                    $t->index($columns, $indexName);
                });
            }
        } else {
            // SQLite - vérifier si l'index existe
            $exists = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$indexName]);
            if (empty($exists)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                        $t->index($columns, $indexName);
                    });
                } catch (\Exception $e) {
                    // Index existe déjà, ignorer
                }
            }
        }
    }

    public function up(): void
    {
        // Index pour les ventes - requêtes dashboard
        $this->createIndexIfNotExists('sales', ['tenant_id', 'status', 'completed_at'], 'idx_sales_tenant_status_completed');
        $this->createIndexIfNotExists('sales', ['tenant_id', 'payment_method', 'completed_at'], 'idx_sales_tenant_payment_date');

        // Index pour les achats
        $this->createIndexIfNotExists('purchases', ['tenant_id', 'status', 'received_date'], 'idx_purchases_tenant_status_received');
        $this->createIndexIfNotExists('purchases', ['tenant_id', 'status', 'created_at'], 'idx_purchases_tenant_status_created');

        // Index pour les stocks - requêtes les plus critiques
        $this->createIndexIfNotExists('stocks', ['tenant_id', 'warehouse_id', 'quantity'], 'idx_stocks_tenant_warehouse_qty');
        $this->createIndexIfNotExists('stocks', ['tenant_id', 'product_id', 'warehouse_id'], 'idx_stocks_tenant_product_warehouse');
        $this->createIndexIfNotExists('stocks', ['tenant_id', 'quantity'], 'idx_stocks_tenant_quantity');

        // Index pour les mouvements de stock
        $this->createIndexIfNotExists('stock_movements', ['tenant_id', 'created_at'], 'idx_movements_tenant_created');
        $this->createIndexIfNotExists('stock_movements', ['tenant_id', 'from_warehouse_id', 'created_at'], 'idx_movements_from_warehouse');
        $this->createIndexIfNotExists('stock_movements', ['tenant_id', 'to_warehouse_id', 'created_at'], 'idx_movements_to_warehouse');

        // Index pour les demandes de stock
        $this->createIndexIfNotExists('stock_requests', ['tenant_id', 'status'], 'idx_requests_tenant_status');
        $this->createIndexIfNotExists('stock_requests', ['tenant_id', 'from_warehouse_id', 'status'], 'idx_requests_from_warehouse');

        // Index pour les transferts
        $this->createIndexIfNotExists('transfers', ['tenant_id', 'status'], 'idx_transfers_tenant_status');
        $this->createIndexIfNotExists('transfers', ['tenant_id', 'to_warehouse_id', 'status'], 'idx_transfers_to_warehouse');

        // Index pour les commandes POS
        $this->createIndexIfNotExists('pos_orders', ['tenant_id', 'status', 'created_at'], 'idx_pos_orders_tenant_status_date');

        // Index pour les utilisateurs
        $this->createIndexIfNotExists('users', ['tenant_id', 'last_login_at'], 'idx_users_tenant_login');
        $this->createIndexIfNotExists('users', ['tenant_id', 'role'], 'idx_users_tenant_role');

        // Index pour les produits
        $this->createIndexIfNotExists('products', ['tenant_id', 'status'], 'idx_products_tenant_status');
        $this->createIndexIfNotExists('products', ['tenant_id', 'category'], 'idx_products_tenant_category');

        // Index pour les clients
        $this->createIndexIfNotExists('customers', ['tenant_id', 'created_at'], 'idx_customers_tenant_created');

        // Index pour les entrepôts
        $this->createIndexIfNotExists('warehouses', ['tenant_id', 'type'], 'idx_warehouses_tenant_type');
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_tenant_status_completed');
            $table->dropIndex('idx_sales_tenant_payment_date');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('idx_purchases_tenant_status_received');
            $table->dropIndex('idx_purchases_tenant_status_created');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('idx_stocks_tenant_warehouse_qty');
            $table->dropIndex('idx_stocks_tenant_product_warehouse');
            $table->dropIndex('idx_stocks_tenant_quantity');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_movements_tenant_created');
            $table->dropIndex('idx_movements_from_warehouse');
            $table->dropIndex('idx_movements_to_warehouse');
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropIndex('idx_requests_tenant_status');
            $table->dropIndex('idx_requests_from_warehouse');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex('idx_transfers_tenant_status');
            $table->dropIndex('idx_transfers_to_warehouse');
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropIndex('idx_pos_orders_tenant_status_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_login');
            $table->dropIndex('idx_users_tenant_role');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_tenant_status');
            $table->dropIndex('idx_products_tenant_category');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_tenant_created');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex('idx_warehouses_tenant_type');
        });
    }
};
