<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'cost_of_goods_sold')) {
                $table->decimal('cost_of_goods_sold', 15, 2)->default(0)->comment('Total cost at CMP for GL entry');
            }
            if (!Schema::hasColumn('sales', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumnIfExists('cost_of_goods_sold');
            $table->dropForeignKeyIfExists('sales_warehouse_id_foreign');
            $table->dropColumnIfExists('warehouse_id');
        });
    }
};
