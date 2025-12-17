<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter des index de performance supplémentaires
 * Optimise les requêtes critiques pour atteindre < 500ms
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index sur users pour les requêtes fréquentes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->hasIndex('users', 'users_tenant_role_index')) {
                    $table->index(['tenant_id', 'role'], 'users_tenant_role_index');
                }
            });
        }

        // Index sur pos_orders pour les requêtes de statut
        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                if (!$this->hasIndex('pos_orders', 'pos_orders_tenant_payment_status_index')) {
                    $table->index(['tenant_id', 'payment_status'], 'pos_orders_tenant_payment_status_index');
                }
                if (!$this->hasIndex('pos_orders', 'pos_orders_tenant_created_by_index')) {
                    $table->index(['tenant_id', 'created_by'], 'pos_orders_tenant_created_by_index');
                }
                if (!$this->hasIndex('pos_orders', 'pos_orders_tenant_pos_id_index')) {
                    $table->index(['tenant_id', 'pos_id'], 'pos_orders_tenant_pos_id_index');
                }
            });
        }

        // Index sur pos_order_items
        if (Schema::hasTable('pos_order_items')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                if (!$this->hasIndex('pos_order_items', 'pos_order_items_order_product_index')) {
                    $table->index(['pos_order_id', 'product_id'], 'pos_order_items_order_product_index');
                }
            });
        }

        // Index sur sale_items
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (!$this->hasIndex('sale_items', 'sale_items_sale_product_index')) {
                    $table->index(['sale_id', 'product_id'], 'sale_items_sale_product_index');
                }
            });
        }

        // Index sur purchase_items
        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                if (!$this->hasIndex('purchase_items', 'purchase_items_purchase_product_index')) {
                    $table->index(['purchase_id', 'product_id'], 'purchase_items_purchase_product_index');
                }
            });
        }

        // Index sur transfer_items
        if (Schema::hasTable('transfer_items')) {
            Schema::table('transfer_items', function (Blueprint $table) {
                if (!$this->hasIndex('transfer_items', 'transfer_items_transfer_product_index')) {
                    $table->index(['transfer_id', 'product_id'], 'transfer_items_transfer_product_index');
                }
            });
        }

        // Index sur cash_movements
        if (Schema::hasTable('cash_movements')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                if (!$this->hasIndex('cash_movements', 'cash_movements_tenant_type_index')) {
                    $table->index(['tenant_id', 'type'], 'cash_movements_tenant_type_index');
                }
                if (!$this->hasIndex('cash_movements', 'cash_movements_tenant_date_index')) {
                    $table->index(['tenant_id', 'created_at'], 'cash_movements_tenant_date_index');
                }
            });
        }

        // Index sur expenses
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (!$this->hasIndex('expenses', 'expenses_tenant_date_index')) {
                    $table->index(['tenant_id', 'created_at'], 'expenses_tenant_date_index');
                }
            });
        }

        // Index sur server_stocks (Option B)
        if (Schema::hasTable('server_stocks')) {
            Schema::table('server_stocks', function (Blueprint $table) {
                if (!$this->hasIndex('server_stocks', 'server_stocks_tenant_user_index')) {
                    $table->index(['tenant_id', 'user_id'], 'server_stocks_tenant_user_index');
                }
                if (!$this->hasIndex('server_stocks', 'server_stocks_tenant_product_index')) {
                    $table->index(['tenant_id', 'product_id'], 'server_stocks_tenant_product_index');
                }
            });
        }
    }

    public function down(): void
    {
        // Suppression optionnelle des index
    }

    /**
     * Vérifie si un index existe déjà
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
            
            // Pour PostgreSQL
            if ($driver === 'pgsql') {
                $result = $connection->select(
                    "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $indexName]
                );
                return count($result) > 0;
            }
            
            // Pour MySQL
            $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes($table);
            return isset($indexes[$indexName]);
        } catch (\Exception $e) {
            return false;
        }
    }
};
