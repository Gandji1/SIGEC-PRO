<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mettre une valeur par défaut pour unit_cost
        DB::statement('UPDATE stocks SET unit_cost = 0 WHERE unit_cost IS NULL');
        
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Rien à faire
    }
};
