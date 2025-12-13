<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubscriptionService;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check {--notify : Envoyer des notifications}';
    protected $description = 'Vérifier les abonnements expirés et suspendre les tenants';

    public function handle(SubscriptionService $service): int
    {
        $this->info('Vérification des abonnements...');

        // Vérifier les expirations
        $result = $service->checkExpiredSubscriptions();

        if ($result['expired_count'] > 0) {
            $this->warn("Abonnements expirés: {$result['expired_count']}");
            foreach ($result['expired'] as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($result['suspended_count'] > 0) {
            $this->warn("Tenants suspendus: {$result['suspended_count']}");
            foreach ($result['suspended'] as $name) {
                $this->line("  - {$name}");
            }
        }

        // Alertes pour expirations proches
        if ($this->option('notify')) {
            $expiring = $service->getExpiringSubscriptions(7);
            if (count($expiring) > 0) {
                $this->info("Abonnements expirant dans 7 jours: " . count($expiring));
                foreach ($expiring as $sub) {
                    $this->line("  - {$sub['tenant']} ({$sub['email']}) - {$sub['days_remaining']} jours");
                }
            }
        }

        $this->info('Vérification terminée.');
        return Command::SUCCESS;
    }
}
