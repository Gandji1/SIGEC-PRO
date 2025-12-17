<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 12: Index de performance
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index supplémentaires pour les performances
        
        // Sales
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                // Index composites pour les rapports
                $table->index(['tenant_id', 'completed_at'], 'sales_tenant_completed_idx');
            });
        }

        // Stock movements
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['tenant_id', 'type', 'created_at'], 'stock_mvt_tenant_type_date_idx');
            });
        }

        // POS Orders
        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->index(['tenant_id', 'paid_at'], 'pos_orders_tenant_paid_idx');
            });
        }

        // Products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'category'], 'products_tenant_status_cat_idx');
            });
        }

        // Stocks
        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->index(['tenant_id', 'warehouse_id', 'quantity'], 'stocks_tenant_wh_qty_idx');
            });
        }

        // Accounting entries
        if (Schema::hasTable('accounting_entries')) {
            Schema::table('accounting_entries', function (Blueprint $table) {
                $table->index(['tenant_id', 'date', 'status'], 'acc_entries_tenant_date_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Remove indexes
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex('sales_tenant_completed_idx');
            });
        }

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex('stock_mvt_tenant_type_date_idx');
            });
        }

        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropIndex('pos_orders_tenant_paid_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_tenant_status_cat_idx');
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropIndex('stocks_tenant_wh_qty_idx');
            });
        }

        if (Schema::hasTable('accounting_entries')) {
            Schema::table('accounting_entries', function (Blueprint $table) {
                $table->dropIndex('acc_entries_tenant_date_status_idx');
            });
        }
    }
};
