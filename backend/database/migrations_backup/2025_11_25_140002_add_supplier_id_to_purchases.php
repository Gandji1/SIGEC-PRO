<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Ajouter supplier_id après user_id si la colonne n'existe pas
            if (!Schema::hasColumn('purchases', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('user_id')->constrained('suppliers')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'supplier_id')) {
                $table->dropForeignKeyIfExists(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
        });
    }
};
