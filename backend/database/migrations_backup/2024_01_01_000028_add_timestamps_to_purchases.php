<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('quantity_received');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->timestamp('received_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'received_at']);
        });
    }
};
