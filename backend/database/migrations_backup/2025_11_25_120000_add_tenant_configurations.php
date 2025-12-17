<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Configuration financière
            $table->decimal('tva_rate', 5, 2)->default(18.00)->comment('TVA percentage');
            $table->decimal('default_markup', 5, 2)->default(30.00)->comment('Default markup percentage');
            
            // Politiques
            $table->enum('stock_policy', ['fifo', 'lifo', 'cmp'])->default('cmp')->comment('Stock valuation method');
            $table->json('payment_methods')->nullable()->comment('Active payment methods: especes, kkiapay, fedapay, virement');
            $table->json('pos_configuration')->nullable()->comment('POS specific settings');
            
            // Statuts d'activation
            $table->boolean('kkiapay_enabled')->default(false);
            $table->boolean('fedapay_enabled')->default(false);
            $table->boolean('allow_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->nullable();
            
            // Métadonnées
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'tva_rate',
                'default_markup',
                'stock_policy',
                'payment_methods',
                'pos_configuration',
                'kkiapay_enabled',
                'fedapay_enabled',
                'allow_credit',
                'credit_limit',
                'metadata',
            ]);
        });
    }
};
