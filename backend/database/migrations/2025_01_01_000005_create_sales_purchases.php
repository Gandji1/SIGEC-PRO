<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 5: Sales et Purchases
 */
return new class extends Migration
{
    public function up(): void
    {
        // SALES
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable();
            $table->string('promotion_code')->nullable();
            $table->string('reference');
            $table->string('mode')->default('manual');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change', 15, 2)->default(0);
            $table->decimal('cogs', 15, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->enum('status', ['draft', 'completed', 'cancelled', 'returned'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        // SALE ITEMS
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->string('unit')->default('pcs');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        // SALE PAYMENTS
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reference');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'other'])->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->index('sale_id');
            $table->index('received_at');
        });

        // CUSTOMER PAYMENTS
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reference');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'other'])->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->index('customer_id');
            $table->index('received_at');
        });

        // PURCHASES
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('reference');
            $table->string('supplier_name');
            $table->string('supplier_phone')->nullable();
            $table->string('supplier_email')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('payment_method')->default('transfer');
            $table->enum('status', ['draft', 'pending_approval', 'submitted', 'confirmed', 'shipped', 'delivered', 'received', 'paid', 'cancelled'])->default('draft');
            $table->enum('workflow_status', ['draft', 'pending_approval', 'approved', 'rejected', 'ordered', 'received', 'completed'])->default('draft');
            // Workflow users
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            // Workflow timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            // Payment validation
            $table->boolean('payment_validated')->default(false);
            $table->foreignId('payment_validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_validated_at')->nullable();
            $table->boolean('payment_validated_by_supplier')->default(false);
            // Dates
            $table->date('expected_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->date('received_date')->nullable();
            // Supplier info
            $table->text('supplier_notes')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('delivery_proof')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });

        // PURCHASE ITEMS
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity_ordered');
            $table->integer('quantity')->nullable();
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('unit')->default('pcs');
            $table->timestamp('received_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'purchase_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        // SUPPLIER PAYMENTS
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->setOnDelete('set null');
            $table->string('reference');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['bank_transfer', 'check', 'cash', 'other'])->default('bank_transfer');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->index('supplier_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
