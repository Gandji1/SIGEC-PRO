<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('warehouse')->default('main');
            $table->integer('quantity')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->timestamp('last_counted_at')->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'product_id', 'warehouse']);
            $table->index(['tenant_id', 'warehouse']);
            $table->index('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
