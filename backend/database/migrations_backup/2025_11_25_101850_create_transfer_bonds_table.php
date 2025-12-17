<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfer_bonds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('transfer_id');
            $table->string('bond_number')->unique();
            $table->text('description')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('executed_at')->nullable();
            $table->enum('status', ['draft', 'issued', 'received', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('issued_by');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_bonds');
    }
};
