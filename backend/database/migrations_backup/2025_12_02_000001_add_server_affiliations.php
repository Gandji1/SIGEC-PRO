<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter les affiliations multiples serveur → tables/POS
 * Un serveur peut être affilié à plusieurs tables et plusieurs POS
 */
return new class extends Migration
{
    public function up(): void
    {
        // Table pivot pour affilier les serveurs aux tables
        Schema::create('user_pos_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_table_id')->constrained('pos_tables')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'pos_table_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Table pivot pour affilier les serveurs aux POS
        Schema::create('user_pos_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->constrained('pos')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'pos_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Ajouter des colonnes pour le workflow de commande POS
        Schema::table('pos_orders', function (Blueprint $table) {
            // Statut de préparation
            if (!Schema::hasColumn('pos_orders', 'preparation_status')) {
                $table->enum('preparation_status', ['pending', 'approved', 'preparing', 'ready', 'served'])
                    ->default('pending')->after('status');
            }
            
            // Statut de paiement
            if (!Schema::hasColumn('pos_orders', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'processing', 'confirmed', 'failed'])
                    ->default('pending')->after('preparation_status');
            }
            
            // Gérant qui a approuvé
            if (!Schema::hasColumn('pos_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('validated_by')
                    ->constrained('users')->nullOnDelete();
            }
            
            // Date d'approbation
            if (!Schema::hasColumn('pos_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            // Référence de paiement externe (pour les PSP)
            if (!Schema::hasColumn('pos_orders', 'external_payment_ref')) {
                $table->string('external_payment_ref')->nullable()->after('payment_reference');
            }
        });

        // Ajouter des index pour les performances
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'preparation_status']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Index sur les tables critiques pour les performances
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasIndex('sales', 'sales_tenant_created_idx')) {
                $table->index(['tenant_id', 'created_at'], 'sales_tenant_created_idx');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_movements', 'stock_movements_tenant_created_idx')) {
                $table->index(['tenant_id', 'created_at'], 'stock_movements_tenant_created_idx');
            }
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            if (!Schema::hasIndex('cash_movements', 'cash_movements_tenant_created_idx')) {
                $table->index(['tenant_id', 'created_at'], 'cash_movements_tenant_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pos_tables');
        Schema::dropIfExists('user_pos_affiliations');
        
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn([
                'preparation_status', 
                'payment_status', 
                'approved_by', 
                'approved_at',
                'external_payment_ref'
            ]);
        });
    }
};
