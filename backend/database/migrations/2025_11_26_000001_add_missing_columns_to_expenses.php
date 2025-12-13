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
        Schema::table('expenses', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('expenses', 'is_fixed')) {
                $table->boolean('is_fixed')->default(false)->after('date');
            }
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->enum('payment_method', ['especes', 'cheque', 'virement', 'credit_card', 'kkiapay', 'fedapay'])->nullable()->after('is_fixed');
            }
            if (!Schema::hasColumn('expenses', 'recorded_by')) {
                $table->unsignedBigInteger('recorded_by')->nullable()->after('user_id');
                $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('expenses', 'expense_date')) {
                $table->dateTime('expense_date')->nullable()->after('date');
            }
            if (!Schema::hasColumn('expenses', 'metadata')) {
                $table->json('metadata')->nullable()->after('expense_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'is_fixed')) {
                $table->dropColumn('is_fixed');
            }
            if (Schema::hasColumn('expenses', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('expenses', 'recorded_by')) {
                $table->dropForeign(['recorded_by']);
                $table->dropColumn('recorded_by');
            }
            if (Schema::hasColumn('expenses', 'expense_date')) {
                $table->dropColumn('expense_date');
            }
            if (Schema::hasColumn('expenses', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
