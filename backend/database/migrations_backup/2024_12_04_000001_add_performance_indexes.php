<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter des index de performance
 * Optimise les requêtes fréquentes sur les colonnes filtrées
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index sur products
        Schema::table('products', function (Blueprint $table) {
            // Index composites pour les requêtes courantes
            if (!$this->hasIndex('products', 'products_tenant_status_index')) {
                $table->index(['tenant_id', 'status'], 'products_tenant_status_index');
            }
            if (!$this->hasIndex('products', 'products_tenant_category_index')) {
                $table->index(['tenant_id', 'category'], 'products_tenant_category_index');
            }
        });

        // Index sur sales
        Schema::table('sales', function (Blueprint $table) {
            if (!$this->hasIndex('sales', 'sales_tenant_date_index')) {
                $table->index(['tenant_id', 'created_at'], 'sales_tenant_date_index');
            }
            if (!$this->hasIndex('sales', 'sales_tenant_status_index')) {
                $table->index(['tenant_id', 'status'], 'sales_tenant_status_index');
            }
        });

        // Index sur purchases
        Schema::table('purchases', function (Blueprint $table) {
            if (!$this->hasIndex('purchases', 'purchases_tenant_date_index')) {
                $table->index(['tenant_id', 'created_at'], 'purchases_tenant_date_index');
            }
            if (!$this->hasIndex('purchases', 'purchases_tenant_status_index')) {
                $table->index(['tenant_id', 'status'], 'purchases_tenant_status_index');
            }
        });

        // Index sur stocks
        Schema::table('stocks', function (Blueprint $table) {
            if (!$this->hasIndex('stocks', 'stocks_tenant_product_index')) {
                $table->index(['tenant_id', 'product_id'], 'stocks_tenant_product_index');
            }
            if (!$this->hasIndex('stocks', 'stocks_tenant_warehouse_index')) {
                $table->index(['tenant_id', 'warehouse_id'], 'stocks_tenant_warehouse_index');
            }
        });

        // Index sur stock_movements
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (!$this->hasIndex('stock_movements', 'stock_movements_tenant_date_index')) {
                    $table->index(['tenant_id', 'created_at'], 'stock_movements_tenant_date_index');
                }
                if (!$this->hasIndex('stock_movements', 'stock_movements_product_index')) {
                    $table->index(['product_id', 'created_at'], 'stock_movements_product_index');
                }
            });
        }

        // Index sur customers
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->hasIndex('customers', 'customers_tenant_name_index')) {
                $table->index(['tenant_id', 'name'], 'customers_tenant_name_index');
            }
        });

        // Index sur suppliers
        Schema::table('suppliers', function (Blueprint $table) {
            if (!$this->hasIndex('suppliers', 'suppliers_tenant_name_index')) {
                $table->index(['tenant_id', 'name'], 'suppliers_tenant_name_index');
            }
        });

        // Index sur pos_orders
        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                if (!$this->hasIndex('pos_orders', 'pos_orders_tenant_status_index')) {
                    $table->index(['tenant_id', 'status'], 'pos_orders_tenant_status_index');
                }
                if (!$this->hasIndex('pos_orders', 'pos_orders_tenant_date_index')) {
                    $table->index(['tenant_id', 'created_at'], 'pos_orders_tenant_date_index');
                }
            });
        }

        // Index sur accounting_entries
        if (Schema::hasTable('accounting_entries')) {
            Schema::table('accounting_entries', function (Blueprint $table) {
                if (!$this->hasIndex('accounting_entries', 'accounting_entries_tenant_date_index')) {
                    $table->index(['tenant_id', 'entry_date'], 'accounting_entries_tenant_date_index');
                }
            });
        }
    }

    public function down(): void
    {
        // Suppression des index (optionnel)
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_status_index');
            $table->dropIndex('products_tenant_category_index');
        });
        // ... autres suppressions
    }

    /**
     * Vérifie si un index existe déjà (compatible SQLite)
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();
            
            if ($driver === 'sqlite') {
                $indexes = $connection->select("PRAGMA index_list('{$table}')");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) {
                        return true;
                    }
                }
                return false;
            }
            
            // Pour MySQL/PostgreSQL
            $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes($table);
            return isset($indexes[$indexName]);
        } catch (\Exception $e) {
            // En cas d'erreur, on suppose que l'index n'existe pas
            return false;
        }
    }
};
