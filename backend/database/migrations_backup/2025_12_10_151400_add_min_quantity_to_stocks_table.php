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
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'min_quantity')) {
                $table->integer('min_quantity')->default(5)->after('quantity');
            }
            if (!Schema::hasColumn('stocks', 'max_quantity')) {
                $table->integer('max_quantity')->nullable()->after('min_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['min_quantity', 'max_quantity']);
        });
    }
};
