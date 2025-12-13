<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chart of Accounts
 * 
 * Plan comptable standard avec codes automatiques
 * pour PME sans ressources comptables
 */
class ChartOfAccounts extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'account_type', // asset, liability, equity, revenue, expense
        'sub_type',     // checking, savings, inventory, ar, ap, etc
        'category',     // operational, financial, tax, etc
        'is_active',
        'business_type', // retail, service, manufacturing, etc
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Constantes pour types de comptes
     */
    const ACCOUNT_TYPES = [
        'asset' => 'Actif',
        'liability' => 'Passif',
        'equity' => 'Capitaux propres',
        'revenue' => 'Revenus',
        'expense' => 'Dépenses',
    ];

    const SUB_TYPES = [
        // Assets
        'cash' => 'Caisse',
        'checking' => 'Compte chèque',
        'savings' => 'Compte épargne',
        'ar' => 'Comptes clients',
        'inventory' => 'Inventaire',
        'fixed_assets' => 'Immobilisations',
        'intangible' => 'Actifs immatériels',
        
        // Liabilities
        'ap' => 'Comptes fournisseurs',
        'short_term_debt' => 'Dettes court terme',
        'long_term_debt' => 'Dettes long terme',
        'sales_tax' => 'Taxes de vente',
        'payroll_liabilities' => 'Dettes salariales',
        
        // Equity
        'capital' => 'Capital social',
        'retained_earnings' => 'Résultats accumulés',
        'drawings' => 'Prélèvements',
        
        // Revenue
        'sales' => 'Ventes',
        'services' => 'Services',
        'other_income' => 'Autres revenus',
        'discounts' => 'Rabais & remises',
        
        // Expenses
        'cogs' => 'Coût des marchandises',
        'salaries' => 'Salaires',
        'rent' => 'Loyer',
        'utilities' => 'Services publics',
        'supplies' => 'Fournitures',
        'advertising' => 'Publicité',
        'depreciation' => 'Amortissement',
        'interest' => 'Intérêts',
        'taxes' => 'Taxes & impôts',
        'professional_fees' => 'Frais professionnels',
        'travel' => 'Voyages',
        'meals' => 'Repas & divertissement',
    ];

    /**
     * Plan comptable standard
     */
    const STANDARD_CHART = [
        // 1000-1999: ACTIFS
        [
            'code' => '1010',
            'name' => 'Caisse',
            'account_type' => 'asset',
            'sub_type' => 'cash',
            'category' => 'operational',
            'order' => 10,
        ],
        [
            'code' => '1020',
            'name' => 'Compte Chèque',
            'account_type' => 'asset',
            'sub_type' => 'checking',
            'category' => 'operational',
            'order' => 20,
        ],
        [
            'code' => '1030',
            'name' => 'Compte Épargne',
            'account_type' => 'asset',
            'sub_type' => 'savings',
            'category' => 'operational',
            'order' => 30,
        ],
        [
            'code' => '1200',
            'name' => 'Comptes Clients',
            'account_type' => 'asset',
            'sub_type' => 'ar',
            'category' => 'operational',
            'order' => 40,
        ],
        [
            'code' => '1300',
            'name' => 'Inventaire',
            'account_type' => 'asset',
            'sub_type' => 'inventory',
            'category' => 'operational',
            'order' => 50,
        ],
        [
            'code' => '1500',
            'name' => 'Immobilisations',
            'account_type' => 'asset',
            'sub_type' => 'fixed_assets',
            'category' => 'operational',
            'order' => 60,
        ],

        // 2000-2999: PASSIFS
        [
            'code' => '2100',
            'name' => 'Comptes Fournisseurs',
            'account_type' => 'liability',
            'sub_type' => 'ap',
            'category' => 'operational',
            'order' => 100,
        ],
        [
            'code' => '2200',
            'name' => 'Dettes Court Terme',
            'account_type' => 'liability',
            'sub_type' => 'short_term_debt',
            'category' => 'financial',
            'order' => 110,
        ],
        [
            'code' => '2300',
            'name' => 'Dettes Long Terme',
            'account_type' => 'liability',
            'sub_type' => 'long_term_debt',
            'category' => 'financial',
            'order' => 120,
        ],
        [
            'code' => '2500',
            'name' => 'Taxes de Vente à Payer',
            'account_type' => 'liability',
            'sub_type' => 'sales_tax',
            'category' => 'tax',
            'order' => 130,
        ],
        [
            'code' => '2600',
            'name' => 'Dettes Salariales',
            'account_type' => 'liability',
            'sub_type' => 'payroll_liabilities',
            'category' => 'operational',
            'order' => 140,
        ],

        // 3000-3999: CAPITAUX PROPRES
        [
            'code' => '3100',
            'name' => 'Capital Social',
            'account_type' => 'equity',
            'sub_type' => 'capital',
            'category' => 'operational',
            'order' => 200,
        ],
        [
            'code' => '3200',
            'name' => 'Résultats Accumulés',
            'account_type' => 'equity',
            'sub_type' => 'retained_earnings',
            'category' => 'operational',
            'order' => 210,
        ],
        [
            'code' => '3300',
            'name' => 'Prélèvements',
            'account_type' => 'equity',
            'sub_type' => 'drawings',
            'category' => 'operational',
            'order' => 220,
        ],

        // 4000-4999: REVENUS
        [
            'code' => '4100',
            'name' => 'Ventes - Produits',
            'account_type' => 'revenue',
            'sub_type' => 'sales',
            'category' => 'operational',
            'order' => 300,
        ],
        [
            'code' => '4200',
            'name' => 'Ventes - Services',
            'account_type' => 'revenue',
            'sub_type' => 'services',
            'category' => 'operational',
            'order' => 310,
        ],
        [
            'code' => '4300',
            'name' => 'Autres Revenus',
            'account_type' => 'revenue',
            'sub_type' => 'other_income',
            'category' => 'operational',
            'order' => 320,
        ],
        [
            'code' => '4400',
            'name' => 'Rabais & Remises',
            'account_type' => 'revenue',
            'sub_type' => 'discounts',
            'category' => 'operational',
            'order' => 330,
        ],

        // 5000-5999: DÉPENSES
        [
            'code' => '5100',
            'name' => 'Coût des Marchandises',
            'account_type' => 'expense',
            'sub_type' => 'cogs',
            'category' => 'operational',
            'order' => 400,
        ],
        [
            'code' => '5200',
            'name' => 'Salaires & Traitements',
            'account_type' => 'expense',
            'sub_type' => 'salaries',
            'category' => 'operational',
            'order' => 410,
        ],
        [
            'code' => '5300',
            'name' => 'Loyer',
            'account_type' => 'expense',
            'sub_type' => 'rent',
            'category' => 'operational',
            'order' => 420,
        ],
        [
            'code' => '5400',
            'name' => 'Services Publics',
            'account_type' => 'expense',
            'sub_type' => 'utilities',
            'category' => 'operational',
            'order' => 430,
        ],
        [
            'code' => '5500',
            'name' => 'Fournitures',
            'account_type' => 'expense',
            'sub_type' => 'supplies',
            'category' => 'operational',
            'order' => 440,
        ],
        [
            'code' => '5600',
            'name' => 'Publicité & Marketing',
            'account_type' => 'expense',
            'sub_type' => 'advertising',
            'category' => 'operational',
            'order' => 450,
        ],
        [
            'code' => '5700',
            'name' => 'Amortissement',
            'account_type' => 'expense',
            'sub_type' => 'depreciation',
            'category' => 'operational',
            'order' => 460,
        ],
        [
            'code' => '5800',
            'name' => 'Intérêts',
            'account_type' => 'expense',
            'sub_type' => 'interest',
            'category' => 'financial',
            'order' => 470,
        ],
        [
            'code' => '5900',
            'name' => 'Taxes & Impôts',
            'account_type' => 'expense',
            'sub_type' => 'taxes',
            'category' => 'tax',
            'order' => 480,
        ],
        [
            'code' => '6000',
            'name' => 'Frais Professionnels',
            'account_type' => 'expense',
            'sub_type' => 'professional_fees',
            'category' => 'operational',
            'order' => 490,
        ],
        [
            'code' => '6100',
            'name' => 'Voyages & Transport',
            'account_type' => 'expense',
            'sub_type' => 'travel',
            'category' => 'operational',
            'order' => 500,
        ],
        [
            'code' => '6200',
            'name' => 'Repas & Divertissement',
            'account_type' => 'expense',
            'sub_type' => 'meals',
            'category' => 'operational',
            'order' => 510,
        ],
    ];
}
