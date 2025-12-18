<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter les champs du portail fournisseur
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Champs pour le portail fournisseur
            if (!Schema::hasColumn('suppliers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('suppliers', 'has_portal_access')) {
                $table->boolean('has_portal_access')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('suppliers', 'portal_email')) {
                $table->string('portal_email')->nullable()->after('has_portal_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('suppliers', 'has_portal_access')) {
                $table->dropColumn('has_portal_access');
            }
            if (Schema::hasColumn('suppliers', 'portal_email')) {
                $table->dropColumn('portal_email');
            }
        });
    }
};
