<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounting_entry_lines')) {
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
        }

        // Ajouter les colonnes manquantes à accounting_entries
        Schema::table('accounting_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_entries', 'date')) {
                $table->date('date')->nullable()->after('entry_date');
            }
            if (!Schema::hasColumn('accounting_entries', 'journal_type')) {
                $table->string('journal_type')->default('general')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_lines');
    }
};
