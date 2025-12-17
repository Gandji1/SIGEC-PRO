<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 1: Tables de base
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cache Laravel
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // Plans d'abonnement système
        Schema::create('system_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->integer('max_users')->default(5);
            $table->integer('max_pos')->default(1);
            $table->integer('max_products')->default(100);
            $table->integer('max_warehouses')->default(1);
            $table->integer('storage_limit_mb')->default(500);
            $table->json('features')->nullable();
            $table->integer('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Modules système
        Schema::create('system_modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('extra_price', 12, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Paramètres système
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        // Plans d'abonnement (legacy)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('billing_period')->default('monthly');
            $table->integer('trial_days')->default(14);
            $table->integer('max_users')->nullable();
            $table->integer('max_warehouses')->default(3);
            $table->integer('max_tenants')->default(1);
            $table->boolean('has_accounting')->default(true);
            $table->boolean('has_exports')->default(true);
            $table->boolean('has_api')->default(true);
            $table->boolean('has_backup')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('slug');
        });

        // RBAC - Permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->string('resource')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });

        // RBAC - Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // RBAC - Role Permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('system_modules');
        Schema::dropIfExists('system_subscription_plans');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
