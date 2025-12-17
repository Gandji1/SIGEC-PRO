<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique(); // Ex: 1010, 2100, 4100
            $table->string('name'); // Ex: Caisse, Comptes Clients
            $table->text('description')->nullable();
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->string('sub_type')->nullable(); // cash, checking, ar, ap, sales, etc
            $table->string('category')->nullable(); // operational, financial, tax
            $table->string('business_type')->nullable(); // retail, service, manufacturing
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0); // Pour tri
            $table->timestamps();
            $table->softDeletes();

            // Indices
            $table->index(['tenant_id', 'account_type']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'business_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
