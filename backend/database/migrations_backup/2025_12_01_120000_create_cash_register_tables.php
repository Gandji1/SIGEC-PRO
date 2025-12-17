<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sessions de caisse (ouverture/fermeture)
        if (!Schema::hasTable('cash_register_sessions')) {
            Schema::create('cash_register_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
                $table->foreignId('opened_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->decimal('opening_balance', 12, 2)->default(0);
                $table->decimal('closing_balance', 12, 2)->nullable();
                $table->decimal('expected_balance', 12, 2)->nullable();
                $table->decimal('difference', 12, 2)->nullable();
                $table->decimal('cash_sales', 12, 2)->default(0);
                $table->decimal('card_sales', 12, 2)->default(0);
                $table->decimal('mobile_sales', 12, 2)->default(0);
                $table->decimal('other_sales', 12, 2)->default(0);
                $table->decimal('cash_out', 12, 2)->default(0); // Sorties de caisse
                $table->decimal('cash_in', 12, 2)->default(0);  // Entrées manuelles
                $table->integer('transactions_count')->default(0);
                $table->enum('status', ['open', 'closed', 'validated'])->default('open');
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'opened_at']);
            });
        }

        // Mouvements de caisse
        if (!Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('session_id')->nullable()->constrained('cash_register_sessions')->onDelete('set null');
                $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('type', ['in', 'out']);
                $table->enum('category', [
                    'sale',           // Vente
                    'refund',         // Remboursement
                    'expense',        // Charge/Dépense
                    'deposit',        // Dépôt manuel
                    'withdrawal',     // Retrait manuel
                    'transfer_in',    // Transfert entrant (remise gérant)
                    'transfer_out',   // Transfert sortant (remise gérant)
                    'adjustment',     // Ajustement
                    'opening',        // Ouverture caisse
                    'closing',        // Fermeture caisse
                ]);
                $table->string('reference')->nullable();
                $table->string('description');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method')->nullable(); // cash, card, momo, fedapay, kkiapay
                $table->foreignId('related_id')->nullable(); // ID de la vente, dépense, etc.
                $table->string('related_type')->nullable(); // sale, expense, pos_order, etc.
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'type']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['session_id']);
            });
        }

        // Remises de fonds (caissier -> gérant)
        if (!Schema::hasTable('cash_remittances')) {
            Schema::create('cash_remittances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('session_id')->nullable()->constrained('cash_register_sessions')->onDelete('set null');
                $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
                $table->foreignId('from_user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('to_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->decimal('amount', 12, 2);
                $table->enum('status', ['pending', 'received', 'validated', 'rejected'])->default('pending');
                $table->string('reference');
                $table->text('notes')->nullable();
                $table->timestamp('remitted_at');
                $table->timestamp('received_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'remitted_at']);
            });
        }

        // Ajouter colonnes aux system_settings pour clés PSP SuperAdmin
        if (Schema::hasTable('system_settings')) {
            // Les clés PSP seront stockées comme settings avec group = 'payment_gateways'
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_remittances');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
