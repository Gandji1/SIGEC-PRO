<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('amortissement_history')) {
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
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amortissement_history');
    }
};
