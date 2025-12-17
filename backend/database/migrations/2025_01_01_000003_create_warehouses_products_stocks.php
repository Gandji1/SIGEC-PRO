<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 3: Warehouses, Products, Stocks
 */
return new class extends Migration
{
    public function up(): void
    {
        // WAREHOUSES
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['gros', 'detail', 'pos'])->default('detail');
            $table->string('location')->nullable();
            $table->decimal('max_capacity', 12, 2)->nullable();
            $table->decimal('capacity', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });

        // PRODUCTS
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('margin_percent', 5, 2)->nullable();
            $table->string('unit')->default('pcs');
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->nullable();
            $table->text('image')->nullable();
            $table->string('barcode')->nullable();
            $table->string('tax_code')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('track_stock')->default(true);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'category']);
            $table->index('status');
        });

        // STOCKS
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('warehouse')->default('main');
            $table->integer('quantity')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('cost_average', 15, 2)->default(0);
            $table->integer('min_quantity')->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->unsignedBigInteger('last_inventory_by')->nullable();
            $table->timestamp('last_inventory_at')->nullable();
            $table->integer('sdu_theorique')->nullable();
            $table->integer('stock_physique')->nullable();
            $table->integer('ecart')->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'product_id', 'warehouse']);
            $table->index(['tenant_id', 'warehouse']);
            $table->index('quantity');
        });

        // STOCK MOVEMENTS
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->enum('type', ['purchase', 'transfer', 'sale', 'adjustment', 'inventory', 'return'])->default('transfer');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
        });

        // LOW STOCK ALERTS
        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->integer('current_quantity');
            $table->integer('threshold_quantity');
            $table->integer('reorder_quantity')->default(0);
            $table->enum('status', ['active', 'resolved', 'ignored'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // PRICE HISTORY
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('price_type', ['purchase', 'selling']);
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('low_stock_alerts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warehouses');
    }
};
