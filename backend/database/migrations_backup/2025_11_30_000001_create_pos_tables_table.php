<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_tables')) {
            Schema::create('pos_tables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('pos_id')->nullable()->constrained('pos')->onDelete('set null');
                $table->string('number');
                $table->integer('capacity')->default(4);
                $table->string('zone')->nullable();
                $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
                $table->foreignId('current_order_id')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tables');
    }
};
