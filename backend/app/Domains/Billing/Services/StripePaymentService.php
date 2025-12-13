<?php

namespace App\Domains\Billing\Services;

use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Refund;
use Exception;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent(float $amount, string $currency = 'xof', array $metadata = []): array
    {
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => (int)($amount * 100), // Convertir en centimes
                'currency' => $currency,
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'client_secret' => $intent->client_secret,
                'intent_id' => $intent->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function confirmPayment(string $intent_id): array
    {
        try {
            $intent = \Stripe\PaymentIntent::retrieve($intent_id);

            if ($intent->status === 'succeeded') {
                return [
                    'success' => true,
                    'status' => 'confirmed',
                    'transaction_id' => $intent->id,
                ];
            }

            return [
                'success' => false,
                'status' => $intent->status,
                'error' => 'Payment not completed',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function refundPayment(string $transaction_id, float $amount = null): array
    {
        try {
            $refund = Refund::create([
                'payment_intent' => $transaction_id,
                'amount' => $amount ? (int)($amount * 100) : null,
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createCustomer(array $customerData): array
    {
        try {
            $customer = Customer::create([
                'name' => $customerData['name'] ?? '',
                'email' => $customerData['email'] ?? '',
                'phone' => $customerData['phone'] ?? '',
                'description' => $customerData['description'] ?? '',
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function chargeCustomer(string $customer_id, float $amount, string $description = ''): array
    {
        try {
            $charge = Charge::create([
                'customer' => $customer_id,
                'amount' => (int)($amount * 100),
                'currency' => 'xof',
                'description' => $description,
            ]);

            return [
                'success' => true,
                'charge_id' => $charge->id,
                'status' => $charge->status,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
