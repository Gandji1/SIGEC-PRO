<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('assigned_pos_id')->nullable()->after('last_login_at')
                ->constrained('pos')->nullOnDelete();
            $table->foreignId('assigned_warehouse_id')->nullable()->after('assigned_pos_id')
                ->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['assigned_pos_id']);
            $table->dropForeign(['assigned_warehouse_id']);
            $table->dropColumn(['assigned_pos_id', 'assigned_warehouse_id']);
        });
    }
};
