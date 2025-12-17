<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('pos_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'preparing', 'served', 'paid', 'validated', 'cancelled'])->default('pending');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('served_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('pos_id')->references('id')->on('pos')->onDelete('set null');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('served_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('validated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'pos_id', 'status']);
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity_ordered');
            $table->integer('quantity_served')->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('pos_order_id')->references('id')->on('pos_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->index(['pos_order_id', 'product_id']);
        });

        // Table pour les remises (servir)
        Schema::create('pos_remises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('pos_order_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('served_by');
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->json('items'); // [{product_id, quantity_served, unit_cost}]
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('pos_order_id')->references('id')->on('pos_orders')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('served_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['tenant_id', 'pos_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_remises');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
    }
};
