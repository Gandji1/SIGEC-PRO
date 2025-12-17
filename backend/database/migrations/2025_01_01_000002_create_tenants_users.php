<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 2: Tenants et Users
 */
return new class extends Migration
{
    public function up(): void
    {
        // TENANTS
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->text('logo')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable()->unique();
            $table->string('registration_number')->nullable()->unique();
            $table->json('settings')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('business_type', ['retail', 'wholesale', 'service', 'manufacturing', 'restaurant', 'pharmacy', 'health', 'education', 'other'])->default('other');
            $table->boolean('accounting_setup_complete')->default(false);
            $table->enum('mode_pos', ['A', 'B'])->default('A');
            $table->enum('pos_option', ['A', 'B'])->default('A');
            $table->boolean('accounting_enabled')->default(true);
            $table->foreignId('plan_id')->nullable();
            $table->integer('storage_used_mb')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('slug');
        });

        // System Subscriptions
        Schema::create('system_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('system_subscription_plans');
            $table->enum('status', ['active', 'trial', 'expired', 'cancelled', 'suspended'])->default('trial');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        // System Payments
        Schema::create('system_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained('system_subscriptions');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        // System Tenant Modules
        Schema::create('system_tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained('system_modules');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });

        // System Logs
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->string('type')->default('system');
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['level', 'type', 'created_at']);
            $table->index('tenant_id');
        });

        // System Alerts
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['info', 'warning', 'error', 'maintenance', 'announcement']);
            $table->string('title');
            $table->text('message');
            $table->boolean('is_global')->default(false);
            $table->boolean('is_dismissible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_global', 'starts_at', 'ends_at']);
        });

        // System Webhooks
        Schema::create('system_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('url');
            $table->json('events');
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        // Subscriptions (legacy)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('plan_id');
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->enum('status', ['active', 'trialing', 'past_due', 'canceled', 'suspended'])->default('trialing');
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->decimal('next_billing_amount', 12, 2)->nullable();
            $table->integer('billing_retry_count')->default(0);
            $table->dateTime('next_retry_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('restrict');
            $table->index(['status', 'current_period_end']);
        });

        // USERS
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->text('avatar')->nullable();
            $table->string('role')->default('staff');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret', 255)->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->unsignedBigInteger('assigned_pos_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'role']);
            $table->index('status');
        });

        // User Roles
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unique(['role_id', 'user_id', 'tenant_id']);
        });

        // Personal Access Tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 80)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Email Verification Tokens
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('system_webhooks');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('system_tenant_modules');
        Schema::dropIfExists('system_payments');
        Schema::dropIfExists('system_subscriptions');
        Schema::dropIfExists('tenants');
    }
};
