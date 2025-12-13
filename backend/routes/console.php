<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RunVirtualRoleAutomations;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Commande pour exécuter les automatisations manuellement
Artisan::command('automations:run {tenant?}', function ($tenant = null) {
    $this->info('Exécution des automatisations...');
    RunVirtualRoleAutomations::dispatch($tenant);
    $this->info('Job dispatché avec succès!');
})->purpose('Exécuter les automatisations des rôles virtuels');

// Planification des automatisations (toutes les heures)
Schedule::job(new RunVirtualRoleAutomations())->hourly();
