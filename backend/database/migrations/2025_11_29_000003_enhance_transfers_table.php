<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('transfers', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('transfers', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('requested_by');
            }
            if (!Schema::hasColumn('transfers', 'executed_by')) {
                $table->unsignedBigInteger('executed_by')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('transfers', 'received_by')) {
                $table->unsignedBigInteger('received_by')->nullable()->after('executed_by');
            }
            if (!Schema::hasColumn('transfers', 'validated_by')) {
                $table->unsignedBigInteger('validated_by')->nullable()->after('received_by');
            }
            if (!Schema::hasColumn('transfers', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('transferred_at');
            }
            if (!Schema::hasColumn('transfers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requested_at');
            }
            if (!Schema::hasColumn('transfers', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('transfers', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('executed_at');
            }
            if (!Schema::hasColumn('transfers', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('transfers', 'stock_request_id')) {
                $table->unsignedBigInteger('stock_request_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('transfers', 'total_value')) {
                $table->decimal('total_value', 14, 2)->default(0)->after('total_items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $columns = [
                'requested_by', 'approved_by', 'executed_by', 'received_by', 'validated_by',
                'requested_at', 'approved_at', 'executed_at', 'received_at', 'validated_at',
                'stock_request_id', 'total_value'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('transfers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
