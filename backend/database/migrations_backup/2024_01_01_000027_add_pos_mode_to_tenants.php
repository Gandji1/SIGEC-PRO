<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('mode_pos', ['A', 'B'])->default('A')->comment('Option A: POS sans stock propre (détail), Option B: POS avec stock propre');
            $table->boolean('accounting_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['mode_pos', 'accounting_enabled']);
        });
    }
};
