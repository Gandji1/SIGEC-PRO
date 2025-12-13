<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'level')) {
                $table->string('level')->default('info')->after('action');
            }
            if (!Schema::hasColumn('audit_logs', 'type')) {
                $table->string('type')->default('system')->after('level');
            }
            if (!Schema::hasColumn('audit_logs', 'details')) {
                $table->text('details')->nullable()->after('description');
            }
            if (!Schema::hasColumn('audit_logs', 'message')) {
                $table->text('message')->nullable()->after('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['level', 'type', 'details', 'message']);
        });
    }
};
