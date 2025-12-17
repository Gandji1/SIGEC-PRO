<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 6: Transfers et Inventory
 */
return new class extends Migration
{
    public function up(): void
    {
        // TRANSFERS
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('stock_request_id')->nullable();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('reference');
            $table->string('from_warehouse')->default('main');
            $table->string('to_warehouse');
            $table->enum('status', ['draft', 'confirmed', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_items', 10, 2)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->timestamp('transferred_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });

        // TRANSFER ITEMS
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'transfer_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        // STOCK REQUESTS
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->onDelete('set null');
            $table->enum('status', ['draft', 'requested', 'approved', 'rejected', 'transferred', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->date('needed_by_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
        });

        // STOCK REQUEST ITEMS
        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained('stock_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['stock_request_id', 'product_id']);
        });

        // INVENTORIES
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('reference');
            $table->enum('status', ['draft', 'in_progress', 'completed', 'validated'])->default('draft');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
        });

        // INVENTORY ITEMS
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('counted_qty');
            $table->integer('system_qty');
            $table->integer('variance');
            $table->decimal('variance_value', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
    }
};
