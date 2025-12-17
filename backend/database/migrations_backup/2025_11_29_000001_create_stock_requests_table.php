<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('from_warehouse_id'); // detail demande
            $table->unsignedBigInteger('to_warehouse_id');   // gros fournit
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('transfer_id')->nullable(); // lien vers transfer cree
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

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'from_warehouse_id']);
            $table->index(['tenant_id', 'to_warehouse_id']);
        });

        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_request_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_request_id')->references('id')->on('stock_requests')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->index(['stock_request_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
