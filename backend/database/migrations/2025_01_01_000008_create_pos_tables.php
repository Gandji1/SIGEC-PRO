<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 8: POS (Point of Sale)
 */
return new class extends Migration
{
    public function up(): void
    {
        // POS
        Schema::create('pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('location')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->string('ip_address')->nullable();
            $table->string('device_id')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        // POS TABLES
        Schema::create('pos_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            $table->string('number');
            $table->integer('capacity')->default(4);
            $table->string('zone')->nullable();
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
            $table->foreignId('current_order_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        // PAYMENT METHODS
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['especes', 'kkiapay', 'fedapay', 'virement', 'cheque', 'credit_card', 'other'])->default('especes');
            $table->boolean('is_active')->default(true);
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'type']);
        });

        // POS ORDERS
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('promotion_id')->nullable();
            $table->string('promotion_code')->nullable();
            $table->string('reference');
            $table->string('table_number')->nullable();
            $table->enum('status', ['pending', 'preparing', 'served', 'paid', 'validated', 'cancelled'])->default('pending');
            $table->enum('preparation_status', ['pending', 'approved', 'preparing', 'ready', 'served'])->default('pending');
            $table->enum('payment_status', ['pending', 'processing', 'confirmed', 'failed'])->default('pending');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('external_payment_ref')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('served_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'pos_id', 'status']);
            $table->index(['tenant_id', 'preparation_status']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index(['tenant_id', 'created_at']);
        });

        // POS ORDER ITEMS
        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity_ordered');
            $table->integer('quantity_served')->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['pos_order_id', 'product_id']);
        });

        // POS REMISES
        Schema::create('pos_remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pos_order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('served_by')->constrained('users')->onDelete('restrict');
            $table->string('reference');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->json('items');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'pos_order_id']);
        });

        // USER POS TABLES
        Schema::create('user_pos_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_table_id')->constrained('pos_tables')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'pos_table_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // USER POS AFFILIATIONS
        Schema::create('user_pos_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->constrained('pos')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'pos_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pos_affiliations');
        Schema::dropIfExists('user_pos_tables');
        Schema::dropIfExists('pos_remises');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('pos_tables');
        Schema::dropIfExists('pos');
    }
};
