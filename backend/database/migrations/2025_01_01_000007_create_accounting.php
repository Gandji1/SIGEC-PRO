<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée SIGEC - Partie 7: Accounting
 */
return new class extends Migration
{
    public function up(): void
    {
        // CHART OF ACCOUNTS
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->string('sub_type')->nullable();
            $table->string('category')->nullable();
            $table->string('business_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'account_type']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'business_type']);
        });

        // ACCOUNTING ENTRIES
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference');
            $table->string('account_code');
            $table->string('description');
            $table->enum('type', ['debit', 'credit'])->default('debit');
            $table->decimal('amount', 15, 2);
            $table->string('category')->nullable();
            $table->string('source')->nullable();
            $table->foreignId('source_id')->nullable();
            $table->string('source_type')->nullable();
            $table->date('entry_date');
            $table->date('date')->nullable();
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->string('journal_type')->default('general');
            $table->boolean('rapproche')->default(false);
            $table->date('date_rapprochement')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference', 'type']);
            $table->index(['tenant_id', 'account_code']);
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category']);
        });

        // ACCOUNTING ENTRY LINES
        Schema::create('accounting_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_entry_id')->constrained('accounting_entries')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->onDelete('restrict');
            $table->string('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['accounting_entry_id', 'account_id']);
        });

        // ACCOUNTING PERIODS
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['monthly', 'quarterly', 'annual']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closing', 'closed'])->default('open');
            $table->json('summary')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'type', 'start_date']);
            $table->index(['tenant_id', 'status']);
        });

        // IMMOBILISATIONS
        Schema::create('immobilisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('designation');
            $table->string('category_code', 10);
            $table->string('category_label')->nullable();
            $table->date('date_acquisition');
            $table->decimal('valeur_acquisition', 15, 2);
            $table->decimal('valeur_residuelle', 15, 2)->default(0);
            $table->integer('duree_vie');
            $table->string('methode_amortissement')->default('lineaire');
            $table->decimal('cumul_amortissement', 15, 2)->default(0);
            $table->string('numero_serie')->nullable();
            $table->string('fournisseur')->nullable();
            $table->string('localisation')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->date('date_cession')->nullable();
            $table->decimal('prix_cession', 15, 2)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'category_code']);
            $table->index(['tenant_id', 'status']);
        });

        // AMORTISSEMENT HISTORY
        Schema::create('amortissement_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('immobilisation_id');
            $table->integer('annee');
            $table->decimal('dotation', 15, 2);
            $table->decimal('cumul_avant', 15, 2);
            $table->decimal('cumul_apres', 15, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['immobilisation_id', 'annee']);
            $table->index(['tenant_id', 'annee']);
        });

        // BANK STATEMENTS
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('account_id');
            $table->date('date_releve');
            $table->decimal('solde_releve', 15, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'account_id', 'date_releve']);
        });

        // EXPENSES
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->boolean('is_fixed')->default(false);
            $table->enum('payment_method', ['especes', 'cheque', 'virement', 'credit_card', 'kkiapay', 'fedapay'])->nullable();
            $table->dateTime('expense_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('amortissement_history');
        Schema::dropIfExists('immobilisations');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
