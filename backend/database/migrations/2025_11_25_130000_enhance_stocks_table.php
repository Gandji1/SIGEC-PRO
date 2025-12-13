<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes manquantes au stock
        Schema::table('stocks', function (Blueprint $table) {
            // Ajouter warehouse_id si absent
            if (!Schema::hasColumn('stocks', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            }
            // Ajouter cost_average si absent
            if (!Schema::hasColumn('stocks', 'cost_average')) {
                $table->decimal('cost_average', 15, 2)->default(0)->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'warehouse_id')) {
                $table->dropForeignKeyIfExists('stocks_warehouse_id_foreign');
                $table->dropColumn('warehouse_id');
            }
            if (Schema::hasColumn('stocks', 'cost_average')) {
                $table->dropColumn('cost_average');
            }
        });
    }
};
