<?php

namespace App\Domains\Accounting\Services;

use App\Models\ChartOfAccounts;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Service de Gestion du Plan Comptable
 * 
 * Crée automatiquement le plan comptable adapté
 * selon le type de business de la PME
 */
class ChartOfAccountsService
{
    /**
     * Types de business supportés
     */
    const BUSINESS_TYPES = [
        'retail' => 'Commerce de détail',
        'wholesale' => 'Commerce de gros',
        'service' => 'Services',
        'manufacturing' => 'Fabrication',
        'restaurant' => 'Restauration',
        'hotel' => 'Hôtellerie',
        'healthcare' => 'Santé',
        'education' => 'Éducation',
        'non_profit' => 'Organisations à but non lucratif',
        'consulting' => 'Conseil',
    ];

    /**
     * Crée le plan comptable automatique pour un tenant
     * 
     * @param Tenant $tenant
     * @param string $businessType
     * @return void
     */
    public function createChartOfAccounts(Tenant $tenant, string $businessType = 'retail'): void
    {
        try {
            DB::beginTransaction();

            // Vérifier si le plan existe déjà
            if (ChartOfAccounts::where('tenant_id', $tenant->id)->exists()) {
                throw new \Exception("Plan comptable existe déjà pour ce tenant");
            }

            // Créer le plan comptable standard
            $accounts = ChartOfAccounts::STANDARD_CHART;

            foreach ($accounts as $account) {
                ChartOfAccounts::create([
                    'tenant_id' => $tenant->id,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'account_type' => $account['account_type'],
                    'sub_type' => $account['sub_type'],
                    'category' => $account['category'],
                    'business_type' => $businessType,
                    'is_active' => true,
                    'order' => $account['order'],
                ]);
            }

            // Ajouter des comptes spécifiques au type de business
            $this->addBusinessSpecificAccounts($tenant, $businessType);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Ajoute des comptes spécifiques au type de business
     */
    private function addBusinessSpecificAccounts(Tenant $tenant, string $businessType): void
    {
        $specificAccounts = match ($businessType) {
            'restaurant' => [
                [
                    'code' => '4150',
                    'name' => 'Ventes - Restaurant',
                    'account_type' => 'revenue',
                    'sub_type' => 'sales',
                    'category' => 'operational',
                    'order' => 315,
                ],
                [
                    'code' => '5110',
                    'name' => 'Coût - Nourriture',
                    'account_type' => 'expense',
                    'sub_type' => 'cogs',
                    'category' => 'operational',
                    'order' => 405,
                ],
                [
                    'code' => '5120',
                    'name' => 'Coût - Boissons',
                    'account_type' => 'expense',
                    'sub_type' => 'cogs',
                    'category' => 'operational',
                    'order' => 406,
                ],
            ],
            'hotel' => [
                [
                    'code' => '4160',
                    'name' => 'Ventes - Chambres',
                    'account_type' => 'revenue',
                    'sub_type' => 'sales',
                    'category' => 'operational',
                    'order' => 316,
                ],
                [
                    'code' => '4170',
                    'name' => 'Ventes - Restauration',
                    'account_type' => 'revenue',
                    'sub_type' => 'services',
                    'category' => 'operational',
                    'order' => 317,
                ],
                [
                    'code' => '5430',
                    'name' => 'Services Hôteliers',
                    'account_type' => 'expense',
                    'sub_type' => 'utilities',
                    'category' => 'operational',
                    'order' => 431,
                ],
            ],
            'manufacturing' => [
                [
                    'code' => '1400',
                    'name' => 'Matières Premières',
                    'account_type' => 'asset',
                    'sub_type' => 'inventory',
                    'category' => 'operational',
                    'order' => 55,
                ],
                [
                    'code' => '5130',
                    'name' => 'Coût - Matières Premières',
                    'account_type' => 'expense',
                    'sub_type' => 'cogs',
                    'category' => 'operational',
                    'order' => 407,
                ],
                [
                    'code' => '5140',
                    'name' => 'Coût - Main d\'Œuvre Directe',
                    'account_type' => 'expense',
                    'sub_type' => 'salaries',
                    'category' => 'operational',
                    'order' => 411,
                ],
            ],
            'healthcare' => [
                [
                    'code' => '4200',
                    'name' => 'Revenus - Consultations',
                    'account_type' => 'revenue',
                    'sub_type' => 'services',
                    'category' => 'operational',
                    'order' => 311,
                ],
                [
                    'code' => '5550',
                    'name' => 'Fournitures Médicales',
                    'account_type' => 'expense',
                    'sub_type' => 'supplies',
                    'category' => 'operational',
                    'order' => 441,
                ],
            ],
            default => []
        };

        foreach ($specificAccounts as $account) {
            ChartOfAccounts::create([
                'tenant_id' => $tenant->id,
                'code' => $account['code'],
                'name' => $account['name'],
                'account_type' => $account['account_type'],
                'sub_type' => $account['sub_type'],
                'category' => $account['category'],
                'business_type' => $businessType,
                'is_active' => true,
                'order' => $account['order'],
            ]);
        }
    }

    /**
     * Obtient le compte par type et sous-type
     * Pour auto-mapping lors des transactions
     */
    public function getAccountBySubType(Tenant $tenant, string $subType): ?ChartOfAccounts
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('sub_type', $subType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Obtient tous les comptes de revenus
     */
    public function getRevenueAccounts(Tenant $tenant)
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('account_type', 'revenue')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Obtient tous les comptes de dépenses
     */
    public function getExpenseAccounts(Tenant $tenant)
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Obtient tous les comptes d'actifs
     */
    public function getAssetAccounts(Tenant $tenant)
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('account_type', 'asset')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Obtient tous les comptes de passifs
     */
    public function getLiabilityAccounts(Tenant $tenant)
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('account_type', 'liability')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Obtient tous les comptes
     */
    public function getAllAccounts(Tenant $tenant)
    {
        return ChartOfAccounts::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Mappe automatiquement une transaction à un compte
     */
    public function autoMapTransaction(Tenant $tenant, string $type, string $subType): ?ChartOfAccounts
    {
        // Type peut être: sale, purchase, payment, adjustment
        return match ($type) {
            'sale' => $this->getAccountBySubType($tenant, 'sales'),
            'service' => $this->getAccountBySubType($tenant, 'services'),
            'cogs' => $this->getAccountBySubType($tenant, 'cogs'),
            'customer_payment' => $this->getAccountBySubType($tenant, 'checking'),
            'supplier_payment' => $this->getAccountBySubType($tenant, 'checking'),
            default => null,
        };
    }
}
