<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter la colonne warehouse_id si elle n'existe pas
        if (!Schema::hasColumn('stocks', 'warehouse_id')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
                $table->decimal('cost_average', 15, 2)->default(0)->after('unit_cost');
            });
        }

        // Migrer les données existantes: associer warehouse string aux warehouse_id
        DB::statement("
            UPDATE stocks 
            SET warehouse_id = (
                SELECT w.id FROM warehouses w 
                WHERE w.tenant_id = stocks.tenant_id 
                AND w.type = stocks.warehouse
                LIMIT 1
            )
            WHERE warehouse_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'cost_average']);
        });
    }
};
