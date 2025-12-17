<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'other'])->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('sale_id');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
