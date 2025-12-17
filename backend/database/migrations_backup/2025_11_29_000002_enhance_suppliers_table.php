<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('suppliers', 'lead_time_days')) {
                $table->integer('lead_time_days')->nullable()->after('payment_terms');
            }
            if (!Schema::hasColumn('suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'lead_time_days', 'is_active']);
        });
    }
};
