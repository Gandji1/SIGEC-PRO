<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('billing_period')->default('monthly'); // monthly, yearly
            $table->integer('trial_days')->default(14);
            $table->integer('max_users')->nullable(); // null = unlimited
            $table->integer('max_warehouses')->default(3);
            $table->integer('max_tenants')->default(1);
            $table->boolean('has_accounting')->default(true);
            $table->boolean('has_exports')->default(true);
            $table->boolean('has_api')->default(true);
            $table->boolean('has_backup')->default(false);
            $table->json('features')->nullable(); // JSON array of features
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
