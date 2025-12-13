<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code')->unique();
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
            $table->string('barcode')->nullable()->unique();
            $table->string('tax_code')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('track_stock')->default(true);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'category']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
