<?php

namespace App\Http\Controllers;

use App\Models\EventPurchase;
use App\Models\PaymentTransaction;
use App\Models\User;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Handle FedaPay payment callback
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fedapayCallback(Request $request)
    { 
        $transactionType = $request->input('transaction_type'); // 'subscription' or 'event'
        $transactionId = $request->input('transaction_id');
        $eventId = $request->input('event_id');
        $userId = $request->input('user_id');

        if (!$transactionType || !$transactionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Type de transaction ou ID invalide',
            ], 400);
        }

        // Get user ID from auth or cookie for unauthenticated users
        $userId = Auth::id() ?? $userId ?? Cookie::get('user_id');

        // If still no user ID, generate a temporary one for guests
        if (!$userId) {
            $userId = 'guest_' . Str::uuid();
            Cookie::queue('user_id', $userId, 60 * 24 * 30); // Store for 30 days
        }

        try {
            // Initialize FedaPay with your API key
            FedaPay::setApiKey(config('payment.feda_secret_key'));
            FedaPay::setEnvironment(config('payment.fedapay_environment', 'sandbox'));

            // Retrieve transaction from FedaPay
            $fedapayTransaction = Transaction::retrieve($transactionId);
            $status = strtolower($fedapayTransaction->status);

            // Save transaction details
            $paymentTransaction = $this->savePaymentTransaction($fedapayTransaction, $userId);

            if ($status === 'approved') {
                if ($transactionType === 'event') {
                    $eventPurchase = $this->saveEventPurchase($paymentTransaction->id, $eventId, $userId);
                    $redirectUrl = route('events.show', $eventId) . '?payment=success';
                } else {
                    $this->saveSubscription($paymentTransaction->id, $userId);
                    $redirectUrl = route('dashboard') . '?subscription=success';
                }

                // Set flash message for toast notification
                $request->session()->flash('success', 'Paiement approuvé avec succès');

                return response()->json([
                    'status' => 'success',
                    'redirect' => $redirectUrl,
                    'message' => 'Paiement approuvé avec succès',
                ]);
            }

            $message = match($status) {
                'pending' => 'Votre paiement est en cours de traitement',
                'failed' => 'Le paiement a échoué. Veuillez réessayer',
                'canceled' => 'Le paiement a été annulé',
                default => 'Le paiement est en attente ou a échoué'
            };

            $request->session()->flash($status === 'pending' ? 'info' : 'error', $message);

            return response()->json([
                'status' => $status,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            $errorMessage = 'Une erreur est survenue lors du traitement du paiement';
            \Log::error('Payment processing error: ' . $e->getMessage(), [
                'exception' => $e,
                'transaction_id' => $transactionId,
                'user_id' => $userId
            ]);

            $request->session()->flash('error', $errorMessage);

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
            ], 500);
        }
    }

    /**
     * Save payment transaction details
     *
     * @param \FedaPay\Transaction $fedapayTransaction
     * @param string|int $userId
     * @return PaymentTransaction
     */
    protected function savePaymentTransaction($fedapayTransaction, $userId)
    {
        return PaymentTransaction::updateOrCreate(
            ['transaction_id' => $fedapayTransaction->id],
            [
                'user_id' => Str::startsWith($userId, 'guest_') ? null : $userId,
                'reference' => $fedapayTransaction->reference,
                'amount' => $fedapayTransaction->amount / 100, // Convert from centimes
                'currency' => $fedapayTransaction->currency->iso,
                'payment_method' => $fedapayTransaction->payment_method->type ?? 'card',
                'status' => $fedapayTransaction->status,
                'paid_at' => $fedapayTransaction->status === 'approved' ? now() : null,
                'metadata' => json_encode($fedapayTransaction->metadata ?? []),
                'description' => $fedapayTransaction->description ?? null,
            ]
        );
    }

    /**
     * Save event purchase
     *
     * @param int $transactionId
     * @param int $eventId
     * @param string|int $userId
     * @return EventPurchase
     */
    public function saveEventPurchase($transactionId, $eventId, $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (Str::startsWith($userId, 'guest_')) {
            $userId = null; // Set to null for guest users
        }

        return EventPurchase::create([
            'user_id' => $userId,
            'event_id' => $eventId,
            'payment_transaction_id' => $transactionId,
            'amount_paid' => PaymentTransaction::find($transactionId)->amount,
            'payment_status' => 'completed',
            'payment_method' => 'card',
            'has_access' => true,
            'access_expires_at' => now()->addYear(), // 1 year access by default
        ]);
    }

    /**
     * Save subscription
     *
     * @param int $transactionId
     * @param string|int $userId
     * @return void
     */
    public function saveSubscription($transactionId, $userId = null)
    {
        $user = null;

        if (!Str::startsWith($userId, 'guest_')) {
            $user = User::find($userId);
        }

        if ($user) {
            // Update user's subscription status
            $user->update([
                'is_subscribed' => true,
                'subscription_ends_at' => now()->addYear(),
            ]);
        }

        // You might want to store subscription details in a separate table
        // Subscription::create([...]);
    }
}
