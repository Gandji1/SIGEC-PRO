<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['especes', 'kkiapay', 'fedapay', 'virement', 'cheque', 'credit_card', 'other'])->default('especes');
            $table->boolean('is_active')->default(true);
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
