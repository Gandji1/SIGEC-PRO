<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 11: Tables diverses
 */
return new class extends Migration
{
    public function up(): void
    {
        // AUDIT LOGS
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('model')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->enum('level', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->string('type')->default('action');
            $table->json('changes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['model', 'model_id']);
        });

        // INVOICES
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('invoice_number');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->dateTime('issued_at');
            $table->dateTime('due_at');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('description')->nullable();
            $table->json('items')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        // EXPORTS
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('type');
            $table->string('format');
            $table->string('filename');
            $table->string('path');
            $table->string('url')->nullable();
            $table->integer('size')->nullable();
            $table->dateTime('url_expires_at')->nullable();
            $table->dateTime('from_date');
            $table->dateTime('to_date');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        // DELIVERY NOTES
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->onDelete('set null');
            $table->string('note_number');
            $table->text('description')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('delivered_at')->nullable();
            $table->enum('status', ['draft', 'issued', 'delivered', 'cancelled'])->default('draft');
            $table->foreignId('issued_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('delivered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'note_number']);
        });

        // PROCUREMENT DOCUMENTS
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('document_number');
            $table->enum('type', ['purchase_order', 'quotation', 'invoice', 'receipt'])->default('purchase_order');
            $table->text('description')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('due_date')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->enum('status', ['draft', 'issued', 'approved', 'received', 'cancelled'])->default('draft');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->onDelete('set null');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('issued_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'document_number']);
        });

        // TRANSFER BONDS
        Schema::create('transfer_bonds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->string('bond_number');
            $table->text('description')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('executed_at')->nullable();
            $table->enum('status', ['draft', 'issued', 'received', 'cancelled'])->default('draft');
            $table->foreignId('issued_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'bond_number']);
        });

        // NOTIFICATIONS
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('priority')->default('normal');
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'read']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        // PROMOTIONS
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'buy_x_get_y']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_purchase', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->json('applicable_products')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'start_date', 'end_date']);
        });

        // PRODUCT RETURNS
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('pos_order_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed', 'refunded'])->default('pending');
            $table->enum('return_type', ['refund', 'exchange', 'credit'])->default('refund');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        // PRODUCT RETURN ITEMS
        Schema::create('product_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_return_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->enum('condition', ['good', 'damaged', 'defective'])->default('good');
            $table->boolean('restock')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_return_items');
        Schema::dropIfExists('product_returns');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('transfer_bonds');
        Schema::dropIfExists('procurement_documents');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('audit_logs');
    }
};
