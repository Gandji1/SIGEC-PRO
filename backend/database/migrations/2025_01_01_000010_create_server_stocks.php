<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 10: Server Stocks (Option B)
 */
return new class extends Migration
{
    public function up(): void
    {
        // SERVER STOCKS
        Schema::create('server_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            $table->foreignId('delegated_by')->constrained('users')->onDelete('cascade');
            $table->integer('quantity_delegated')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('quantity_remaining')->default(0);
            $table->integer('quantity_returned')->default(0);
            $table->integer('quantity_lost')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_sales_amount', 12, 2)->default(0);
            $table->decimal('amount_collected', 12, 2)->default(0);
            $table->enum('status', ['active', 'reconciling', 'closed', 'cancelled'])->default('active');
            $table->timestamp('delegated_at')->useCurrent();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'server_id', 'status']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'delegated_at']);
        });

        // SERVER STOCK MOVEMENTS
        Schema::create('server_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_stock_id')->constrained('server_stocks')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_order_id')->nullable()->constrained('pos_orders')->onDelete('set null');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['delegation', 'sale', 'return', 'loss', 'adjustment', 'transfer']);
            $table->integer('quantity');
            $table->integer('quantity_before')->default(0);
            $table->integer('quantity_after')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'server_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });

        // SERVER RECONCILIATIONS
        Schema::create('server_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            $table->string('reference')->unique();
            $table->timestamp('session_start')->useCurrent();
            $table->timestamp('session_end')->nullable();
            $table->decimal('total_delegated_value', 12, 2)->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_returned_value', 12, 2)->default(0);
            $table->decimal('total_losses_value', 12, 2)->default(0);
            $table->decimal('cash_expected', 12, 2)->default(0);
            $table->decimal('cash_collected', 12, 2)->default(0);
            $table->decimal('cash_difference', 12, 2)->default(0);
            $table->enum('status', ['open', 'pending', 'validated', 'disputed', 'closed'])->default('open');
            $table->text('server_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'server_id', 'status']);
            $table->index(['tenant_id', 'session_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_reconciliations');
        Schema::dropIfExists('server_stock_movements');
        Schema::dropIfExists('server_stocks');
    }
};
