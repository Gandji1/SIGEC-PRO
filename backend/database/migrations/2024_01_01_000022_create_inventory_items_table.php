<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->integer('counted_qty'); // Quantité comptée
            $table->integer('system_qty'); // Quantité système
            $table->integer('variance'); // Différence
            $table->decimal('variance_value', 12, 2); // Valeur de la différence
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
