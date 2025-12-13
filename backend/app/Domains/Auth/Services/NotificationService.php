<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use App\Models\Sale;
use Illuminate\Support\Facades\Mail;
use Exception;

class NotificationService
{
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            $data = [
                'user_name' => $user->name,
                'login_url' => config('app.url'),
                'support_email' => config('mail.from.address'),
            ];

            Mail::send('emails.welcome', $data, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Welcome to SIGEC - Get Started');
            });

            return true;
        } catch (Exception $e) {
            \Log::error('Error sending welcome email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendSaleConfirmation(Sale $sale): bool
    {
        try {
            if (!$sale->customer_email) {
                return false;
            }

            $data = [
                'sale' => $sale,
                'customer_name' => $sale->customer_name,
                'reference' => $sale->reference,
                'total' => $sale->total,
                'items' => $sale->items,
            ];

            Mail::send('emails.sale_confirmation', $data, function ($message) use ($sale) {
                $message->to($sale->customer_email)
                    ->subject("Invoice #{$sale->reference}");
            });

            return true;
        } catch (Exception $e) {
            \Log::error('Error sending sale confirmation: ' . $e->getMessage());
            return false;
        }
    }

    public function sendLowStockAlert(int $product_id, int $current_stock, int $min_stock): bool
    {
        try {
            $admins = User::where('role', 'admin')->get();
            
            $data = [
                'product_id' => $product_id,
                'current_stock' => $current_stock,
                'min_stock' => $min_stock,
            ];

            foreach ($admins as $admin) {
                Mail::send('emails.low_stock_alert', $data, function ($message) use ($admin) {
                    $message->to($admin->email)
                        ->subject('Low Stock Alert');
                });
            }

            return true;
        } catch (Exception $e) {
            \Log::error('Error sending low stock alert: ' . $e->getMessage());
            return false;
        }
    }

    public function sendResetPasswordEmail(User $user, string $reset_token): bool
    {
        try {
            $data = [
                'user_name' => $user->name,
                'reset_url' => config('app.url') . '/reset-password?token=' . $reset_token,
            ];

            Mail::send('emails.reset_password', $data, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Reset Your SIGEC Password');
            });

            return true;
        } catch (Exception $e) {
            \Log::error('Error sending reset password email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendDailyReport(User $user, array $report_data): bool
    {
        try {
            $data = [
                'user_name' => $user->name,
                'report_data' => $report_data,
                'date' => now()->format('Y-m-d'),
            ];

            Mail::send('emails.daily_report', $data, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Daily Report - ' . now()->format('Y-m-d'));
            });

            return true;
        } catch (Exception $e) {
            \Log::error('Error sending daily report: ' . $e->getMessage());
            return false;
        }
    }
}
