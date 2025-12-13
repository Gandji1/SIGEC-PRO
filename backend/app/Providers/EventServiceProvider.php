<?php

namespace App\Providers;

use App\Events\SaleCompleted;
use App\Events\PurchaseReceived;
use App\Events\StockLow;
use App\Listeners\RecordSaleAuditLog;
use App\Listeners\RecordPurchaseAuditLog;
use App\Listeners\SendLowStockAlert;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SaleCompleted::class => [
            RecordSaleAuditLog::class,
        ],
        PurchaseReceived::class => [
            RecordPurchaseAuditLog::class,
        ],
        StockLow::class => [
            SendLowStockAlert::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
