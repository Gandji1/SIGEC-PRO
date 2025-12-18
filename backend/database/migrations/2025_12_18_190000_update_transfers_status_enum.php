<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration pour ajouter les statuts manquants à la table transfers
 * Statuts ajoutés: received, validated
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pour SQLite, on doit recréer la table car ALTER COLUMN n'est pas supporté
        // On utilise une approche différente: supprimer la contrainte CHECK
        
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: recréer la table avec les nouveaux statuts
            DB::statement('PRAGMA foreign_keys=off;');
            
            // Créer une table temporaire
            DB::statement('CREATE TABLE transfers_temp AS SELECT * FROM transfers;');
            
            // Supprimer l'ancienne table
            Schema::dropIfExists('transfers');
            
            // Recréer la table avec tous les statuts nécessaires
            Schema::create('transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('stock_request_id')->nullable();
                $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('reference');
                $table->string('from_warehouse')->nullable()->default('main');
                $table->string('to_warehouse')->nullable();
                // Ajout de tous les statuts nécessaires: received, validated
                $table->enum('status', ['draft', 'pending', 'approved', 'confirmed', 'completed', 'received', 'validated', 'cancelled'])->default('draft');
                $table->decimal('total_items', 10, 2)->default(0);
                $table->decimal('total_value', 14, 2)->default(0);
                $table->timestamp('transferred_at')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('executed_by')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'reference']);
                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'status']);
            });
            
            // Restaurer les données
            DB::statement('INSERT INTO transfers SELECT * FROM transfers_temp;');
            
            // Supprimer la table temporaire
            DB::statement('DROP TABLE transfers_temp;');
            
            DB::statement('PRAGMA foreign_keys=on;');
        } else {
            // MySQL/PostgreSQL: modifier la colonne directement
            DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'confirmed', 'completed', 'received', 'validated', 'cancelled') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        // Revenir aux anciens statuts (attention: perte de données si received/validated utilisés)
        if (DB::getDriverName() === 'sqlite') {
            // Pour SQLite, on ne peut pas facilement revenir en arrière
            // On laisse la table telle quelle
        } else {
            DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'confirmed', 'completed', 'cancelled') DEFAULT 'draft'");
        }
    }
};
