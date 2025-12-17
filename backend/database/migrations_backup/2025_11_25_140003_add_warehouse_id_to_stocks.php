<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // Ajouter warehouse_id si elle n'existe pas
            if (!Schema::hasColumn('stocks', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'warehouse_id')) {
                $table->dropForeignKeyIfExists(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
        });
    }
};
