<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table de configuration PSP par tenant (clés chiffrées)
        Schema::create('tenant_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('provider', 50); // fedapay, kkiapay, momo, bank
            $table->string('environment', 20)->default('sandbox'); // sandbox, production
            $table->boolean('is_enabled')->default(false);
            $table->string('public_key')->nullable();
            $table->text('secret_key_encrypted')->nullable(); // Chiffré AES-256
            $table->text('api_user_encrypted')->nullable();   // Chiffré (pour MoMo)
            $table->text('webhook_secret_encrypted')->nullable(); // Chiffré
            $table->json('extra_config')->nullable(); // Config additionnelle
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->string('test_status', 20)->nullable(); // success, failed
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'is_enabled']);
        });

        // Table de logs webhook pour traçabilité et idempotence
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('set null');
            $table->string('provider', 50);
            $table->string('event_type', 100)->nullable();
            $table->string('reference', 255);
            $table->string('idempotency_key', 255)->unique(); // provider_reference
            $table->json('payload')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('status', 20)->default('received'); // received, processed, failed, duplicate
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['provider', 'reference']);
            $table->index(['tenant_id', 'provider']);
            $table->index('status');
            $table->index('created_at');
        });

        // Table pour les paiements par virement bancaire (validation manuelle)
        Schema::create('bank_transfer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('reference', 100)->unique(); // Référence unique générée
            $table->string('type', 20); // sale, subscription
            $table->unsignedBigInteger('related_id'); // sale_id ou subscription_id
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 20)->default('pending'); // pending, confirmed, cancelled
            $table->string('bank_reference')->nullable(); // Référence bancaire fournie par client
            $table->text('proof_document')->nullable(); // Chemin vers preuve de paiement
            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Date limite de paiement
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_payments');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('tenant_payment_configs');
    }
};
