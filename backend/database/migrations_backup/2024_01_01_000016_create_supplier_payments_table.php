<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->setOnDelete('set null');
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['bank_transfer', 'check', 'cash', 'other'])->default('bank_transfer');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('supplier_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
