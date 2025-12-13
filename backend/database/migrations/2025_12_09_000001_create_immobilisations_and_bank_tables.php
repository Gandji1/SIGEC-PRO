<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des immobilisations (SYSCOHADA)
        if (!Schema::hasTable('immobilisations')) {
            Schema::create('immobilisations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->string('designation');
                $table->string('category_code', 10); // Code SYSCOHADA (21, 22, 23, 24, 245, 246...)
                $table->string('category_label')->nullable();
                $table->date('date_acquisition');
                $table->decimal('valeur_acquisition', 15, 2);
                $table->decimal('valeur_residuelle', 15, 2)->default(0);
                $table->integer('duree_vie'); // En années
                $table->string('methode_amortissement')->default('lineaire'); // lineaire, degressif, unites_production
                $table->decimal('cumul_amortissement', 15, 2)->default(0);
                $table->string('numero_serie')->nullable();
                $table->string('fournisseur')->nullable();
                $table->string('localisation')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('active'); // active, ceded, scrapped
                $table->date('date_cession')->nullable();
                $table->decimal('prix_cession', 15, 2)->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'category_code']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // Table des relevés bancaires
        if (!Schema::hasTable('bank_statements')) {
            Schema::create('bank_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('account_id'); // Référence au compte bancaire (chart_of_accounts)
                $table->date('date_releve');
                $table->decimal('solde_releve', 15, 2);
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'account_id', 'date_releve']);
            });
        }

        // Ajouter colonne rapproche aux écritures comptables si pas présente
        if (Schema::hasTable('accounting_entries') && !Schema::hasColumn('accounting_entries', 'rapproche')) {
            Schema::table('accounting_entries', function (Blueprint $table) {
                $table->boolean('rapproche')->default(false)->after('credit');
                $table->date('date_rapprochement')->nullable()->after('rapproche');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('immobilisations');
        Schema::dropIfExists('bank_statements');
        
        if (Schema::hasTable('accounting_entries') && Schema::hasColumn('accounting_entries', 'rapproche')) {
            Schema::table('accounting_entries', function (Blueprint $table) {
                $table->dropColumn(['rapproche', 'date_rapprochement']);
            });
        }
    }
};
