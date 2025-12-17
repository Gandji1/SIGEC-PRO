<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type'); // sales_journal, purchase_journal, trial_balance, income_statement, balance_sheet, inventory, etc
            $table->string('format'); // xlsx, docx, pdf
            $table->string('filename');
            $table->string('path'); // Storage path
            $table->string('url')->nullable(); // Signed URL
            $table->integer('size')->nullable(); // File size in bytes
            $table->dateTime('url_expires_at')->nullable(); // Signed URL expiration
            $table->dateTime('from_date');
            $table->dateTime('to_date');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
