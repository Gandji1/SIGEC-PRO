<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // Supprimer l'unique global sur 'code'
            $table->dropUnique(['code']);
        });

        // Ajouter un unique composé (tenant_id, code)
        Schema::table('warehouses', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // Revenir à l'unique global
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });
    }
};
