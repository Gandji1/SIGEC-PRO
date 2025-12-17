<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Ajouter les foreign keys pour warehouse_id
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('user_id');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('from_warehouse_id');
            
            // Ajouter les timestamps pour tracking
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('requested_at');
            $table->timestamp('approved_at')->nullable()->after('requested_by');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            $table->timestamp('executed_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['from_warehouse_id', 'to_warehouse_id', 'requested_by', 'approved_by']);
            $table->dropColumn(['from_warehouse_id', 'to_warehouse_id', 'requested_at', 'requested_by', 'approved_at', 'approved_by', 'executed_at']);
        });
    }
};
