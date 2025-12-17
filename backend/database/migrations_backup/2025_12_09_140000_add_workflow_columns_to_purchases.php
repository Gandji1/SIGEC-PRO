<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'supplier_notes')) {
                $table->text('supplier_notes')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'tracking_number')) {
                $table->string('tracking_number')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'delivery_proof')) {
                $table->string('delivery_proof')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_at', 'confirmed_at', 'shipped_at', 'delivered_at',
                'received_at', 'paid_at', 'supplier_notes', 'tracking_number',
                'delivery_proof', 'expected_delivery_date'
            ]);
        });
    }
};
