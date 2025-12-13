<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('business_type', [
                'retail', // Commerce de détail
                'wholesale', // Commerce de gros
                'service', // Services
                'manufacturing', // Fabrication
                'restaurant', // Restaurant/Café
                'pharmacy', // Pharmacie
                'health', // Santé/Clinique
                'education', // Éducation
                'other' // Autre
            ])->default('other')->after('status');
            
            $table->boolean('accounting_setup_complete')->default(false)->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('business_type');
            $table->dropColumn('accounting_setup_complete');
        });
    }
};
