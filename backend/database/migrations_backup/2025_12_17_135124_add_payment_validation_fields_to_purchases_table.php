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
        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('payment_validated_by_supplier')->default(false)->after('delivery_proof');
            $table->timestamp('payment_validated_at')->nullable()->after('payment_validated_by_supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['payment_validated_by_supplier', 'payment_validated_at']);
        });
    }
};
