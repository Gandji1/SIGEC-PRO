<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('from_warehouse_id')->nullable();
            $table->unsignedBigInteger('to_warehouse_id')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->enum('type', ['purchase', 'transfer', 'sale', 'adjustment', 'inventory', 'return'])->default('transfer');
            $table->string('reference')->nullable(); // PO#, Sale#, Transfer#, etc.
            $table->unsignedBigInteger('user_id')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            // Indexes for performance
            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
