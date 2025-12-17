<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('account_code');
            $table->string('description');
            $table->enum('type', ['debit', 'credit'])->default('debit');
            $table->decimal('amount', 15, 2);
            $table->string('category')->nullable()->comment('sales, purchases, expenses, etc.');
            $table->string('source')->nullable()->comment('sale, purchase, transfer, manual');
            $table->foreignId('source_id')->nullable();
            $table->string('source_type')->nullable();
            $table->date('entry_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'account_code']);
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
