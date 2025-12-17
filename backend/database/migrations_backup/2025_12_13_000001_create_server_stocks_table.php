<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le stock délégué aux serveurs (Option B)
 * 
 * Option B: Le gérant délègue un stock aux serveurs qui le vendent
 * et font le point au gérant à la fin de leur service.
 * 
 * Workflow Option B:
 * 1. Gérant délègue du stock à un serveur (server_stocks)
 * 2. Serveur vend depuis son stock délégué
 * 3. Serveur fait le point (reconciliation) avec le gérant
 * 4. Gérant valide le point et récupère le stock restant + argent
 */
return new class extends Migration
{
    public function up(): void
    {
        // Table principale: Stock délégué aux serveurs
        Schema::create('server_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            $table->foreignId('delegated_by')->constrained('users')->onDelete('cascade');
            
            // Quantités
            $table->integer('quantity_delegated')->default(0); // Quantité déléguée initialement
            $table->integer('quantity_sold')->default(0); // Quantité vendue
            $table->integer('quantity_remaining')->default(0); // Quantité restante
            $table->integer('quantity_returned')->default(0); // Quantité retournée au gérant
            $table->integer('quantity_lost')->default(0); // Pertes/casse
            
            // Prix et valeurs
            $table->decimal('unit_price', 12, 2)->default(0); // Prix de vente unitaire
            $table->decimal('unit_cost', 12, 2)->default(0); // Coût unitaire (pour calcul marge)
            $table->decimal('total_sales_amount', 12, 2)->default(0); // Montant total des ventes
            $table->decimal('amount_collected', 12, 2)->default(0); // Argent collecté par le serveur
            
            // Statut de la délégation
            $table->enum('status', [
                'active',      // Stock actif, serveur peut vendre
                'reconciling', // En cours de réconciliation
                'closed',      // Réconciliation terminée
                'cancelled'    // Annulé
            ])->default('active');
            
            // Dates
            $table->timestamp('delegated_at')->useCurrent();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            // Notes et métadonnées
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance
            $table->index(['tenant_id', 'server_id', 'status']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'delegated_at']);
        });

        // Table des mouvements de stock serveur (historique détaillé)
        Schema::create('server_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_stock_id')->constrained('server_stocks')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_order_id')->nullable()->constrained('pos_orders')->onDelete('set null');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            
            // Type de mouvement
            $table->enum('type', [
                'delegation',   // Délégation initiale par le gérant
                'sale',         // Vente par le serveur
                'return',       // Retour au gérant
                'loss',         // Perte/casse déclarée
                'adjustment',   // Ajustement (correction)
                'transfer'      // Transfert entre serveurs
            ]);
            
            // Quantités
            $table->integer('quantity'); // Positif = entrée, Négatif = sortie
            $table->integer('quantity_before')->default(0);
            $table->integer('quantity_after')->default(0);
            
            // Valeurs
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            
            // Référence et notes
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index(['tenant_id', 'server_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });

        // Table des sessions de réconciliation (point de caisse serveur)
        Schema::create('server_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
            
            // Référence unique
            $table->string('reference')->unique();
            
            // Période de la session
            $table->timestamp('session_start')->useCurrent();
            $table->timestamp('session_end')->nullable();
            
            // Totaux calculés
            $table->decimal('total_delegated_value', 12, 2)->default(0); // Valeur totale déléguée
            $table->decimal('total_sales', 12, 2)->default(0); // Total des ventes
            $table->decimal('total_returned_value', 12, 2)->default(0); // Valeur du stock retourné
            $table->decimal('total_losses_value', 12, 2)->default(0); // Valeur des pertes
            $table->decimal('cash_expected', 12, 2)->default(0); // Argent attendu
            $table->decimal('cash_collected', 12, 2)->default(0); // Argent collecté
            $table->decimal('cash_difference', 12, 2)->default(0); // Écart de caisse
            
            // Statut
            $table->enum('status', [
                'open',      // Session ouverte
                'pending',   // En attente de validation gérant
                'validated', // Validé par le gérant
                'disputed',  // Contesté (écart important)
                'closed'     // Clôturé
            ])->default('open');
            
            // Notes
            $table->text('server_notes')->nullable();
            $table->text('manager_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index
            $table->index(['tenant_id', 'server_id', 'status']);
            $table->index(['tenant_id', 'session_start']);
        });

        // Ajouter le champ pos_option au tenant pour distinguer Option A et B
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('pos_option', ['A', 'B'])->default('A')->after('mode_pos');
            // Option A: Serveurs envoient commandes au gérant qui sert et valide
            // Option B: Gérant délègue stock aux serveurs qui vendent et font le point
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('pos_option');
        });
        
        Schema::dropIfExists('server_reconciliations');
        Schema::dropIfExists('server_stock_movements');
        Schema::dropIfExists('server_stocks');
    }
};
