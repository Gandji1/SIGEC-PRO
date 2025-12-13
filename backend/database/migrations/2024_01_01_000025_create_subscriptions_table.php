<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique()->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->enum('status', ['active', 'trialing', 'past_due', 'canceled', 'suspended'])->default('trialing');
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->decimal('next_billing_amount', 12, 2)->nullable();
            $table->integer('billing_retry_count')->default(0);
            $table->dateTime('next_retry_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('restrict');

            // Indexes
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
