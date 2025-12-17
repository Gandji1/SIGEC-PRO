<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables système pour le Super Admin
     */
    public function up(): void
    {
        // Plans d'abonnement
        if (!Schema::hasTable('system_subscription_plans')) {
            Schema::create('system_subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // starter, business, enterprise
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
                $table->json('features')->nullable(); // modules inclus
                $table->integer('trial_days')->default(14);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Abonnements des tenants
        if (!Schema::hasTable('system_subscriptions')) {
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
        }

        // Paiements
        if (!Schema::hasTable('system_payments')) {
            Schema::create('system_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('subscription_id')->nullable()->constrained('system_subscriptions');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('XOF');
                $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
                $table->string('payment_method'); // momo, card, bank_transfer
                $table->string('payment_reference')->nullable();
                $table->string('transaction_id')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'status']);
            });
        }

        // Paramètres globaux de la plateforme
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string'); // string, boolean, integer, json
                $table->string('group')->default('general'); // general, email, sms, payment, security
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->timestamps();
            });
        }

        // Modules disponibles
        if (!Schema::hasTable('system_modules')) {
            Schema::create('system_modules', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique(); // pos, stock, accounting, hr, etc.
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->boolean('is_core')->default(false); // modules obligatoires
                $table->boolean('is_active')->default(true);
                $table->decimal('extra_price', 12, 2)->default(0); // prix supplémentaire
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Modules activés par tenant
        if (!Schema::hasTable('system_tenant_modules')) {
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
        }

        // Logs système (extension de audit_logs)
        if (!Schema::hasTable('system_logs')) {
            Schema::create('system_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
                $table->string('type')->default('system'); // auth, tenant, payment, system, api, job
                $table->string('action');
                $table->text('message')->nullable();
                $table->json('context')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();
                
                $table->index(['level', 'type', 'created_at']);
                $table->index('tenant_id');
            });
        }

        // Alertes système
        if (!Schema::hasTable('system_alerts')) {
            Schema::create('system_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
                $table->enum('type', ['info', 'warning', 'error', 'maintenance', 'announcement']);
                $table->string('title');
                $table->text('message');
                $table->boolean('is_global')->default(false); // pour tous les tenants
                $table->boolean('is_dismissible')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                
                $table->index(['is_global', 'starts_at', 'ends_at']);
            });
        }

        // Webhooks
        if (!Schema::hasTable('system_webhooks')) {
            Schema::create('system_webhooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('url');
                $table->json('events'); // ['tenant.created', 'payment.completed', etc.]
                $table->string('secret')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('retry_count')->default(0);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }

        // Ajouter colonnes au tenant si manquantes
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'plan_id')) {
                    $table->foreignId('plan_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('tenants', 'storage_used_mb')) {
                    $table->integer('storage_used_mb')->default(0)->after('plan_id');
                }
                if (!Schema::hasColumn('tenants', 'last_activity_at')) {
                    $table->timestamp('last_activity_at')->nullable()->after('storage_used_mb');
                }
                if (!Schema::hasColumn('tenants', 'subscription_expires_at')) {
                    $table->timestamp('subscription_expires_at')->nullable()->after('last_activity_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_webhooks');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('system_tenant_modules');
        Schema::dropIfExists('system_modules');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('system_payments');
        Schema::dropIfExists('system_subscriptions');
        Schema::dropIfExists('system_subscription_plans');
        
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (Schema::hasColumn('tenants', 'plan_id')) {
                    $table->dropColumn('plan_id');
                }
                if (Schema::hasColumn('tenants', 'storage_used_mb')) {
                    $table->dropColumn('storage_used_mb');
                }
                if (Schema::hasColumn('tenants', 'last_activity_at')) {
                    $table->dropColumn('last_activity_at');
                }
            });
        }
    }
};
