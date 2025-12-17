<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des notifications
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('type'); // order_created, stock_low, payment_pending, etc.
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->string('priority')->default('normal'); // low, normal, high, urgent
                $table->boolean('read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id', 'read']);
                $table->index(['tenant_id', 'type', 'created_at']);
            });
        }

        // 2. Table des promotions/remises
        if (!Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', ['percentage', 'fixed', 'buy_x_get_y']);
                $table->decimal('value', 10, 2); // % ou montant fixe
                $table->decimal('min_purchase', 12, 2)->nullable();
                $table->decimal('max_discount', 12, 2)->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_count')->default(0);
                $table->json('applicable_products')->nullable(); // IDs produits ou null = tous
                $table->json('applicable_categories')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->index(['tenant_id', 'is_active', 'start_date', 'end_date']);
            });
        }

        // 3. Table historique des prix
        if (!Schema::hasTable('price_history')) {
            Schema::create('price_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->enum('price_type', ['purchase', 'selling']);
                $table->decimal('old_price', 12, 2);
                $table->decimal('new_price', 12, 2);
                $table->string('reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->index(['tenant_id', 'product_id', 'created_at']);
            });
        }

        // 4. Table des retours produits
        if (!Schema::hasTable('product_returns')) {
            Schema::create('product_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->string('reference')->unique();
                $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('pos_order_id')->nullable();
                $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
                $table->enum('status', ['pending', 'approved', 'rejected', 'processed', 'refunded'])->default('pending');
                $table->enum('return_type', ['refund', 'exchange', 'credit'])->default('refund');
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('refund_amount', 12, 2)->default(0);
                $table->text('reason');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status', 'created_at']);
            });
        }

        // 5. Table des items de retour
        if (!Schema::hasTable('product_return_items')) {
            Schema::create('product_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_return_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained();
                $table->integer('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->enum('condition', ['good', 'damaged', 'defective'])->default('good');
                $table->boolean('restock')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 6. Table des clôtures comptables
        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->string('name'); // "Janvier 2025", "Q1 2025", "Exercice 2025"
                $table->enum('type', ['monthly', 'quarterly', 'annual']);
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['open', 'closing', 'closed'])->default('open');
                $table->json('summary')->nullable(); // Résumé des totaux à la clôture
                $table->foreignId('closed_by')->nullable()->constrained('users');
                $table->timestamp('closed_at')->nullable();
                $table->text('closing_notes')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'type', 'start_date']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 7. Ajouter colonnes manquantes aux tables existantes
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'promotion_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('promotion_id')->nullable()->after('customer_id');
                $table->string('promotion_code')->nullable()->after('promotion_id');
            });
        }

        if (Schema::hasTable('pos_orders') && !Schema::hasColumn('pos_orders', 'promotion_id')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->foreignId('promotion_id')->nullable()->after('customer_id');
                $table->string('promotion_code')->nullable()->after('promotion_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_return_items');
        Schema::dropIfExists('product_returns');
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('accounting_periods');

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['promotion_id', 'promotion_code']);
            });
        }

        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn(['promotion_id', 'promotion_code']);
            });
        }
    }
};
