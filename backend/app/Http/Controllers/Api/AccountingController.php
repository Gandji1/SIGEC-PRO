<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    protected function getTenantId(Request $request)
    {
        // Priorité: header X-Tenant-ID, puis utilisateur authentifié, puis défaut à 1
        $tenantId = $request->header('X-Tenant-ID');
        
        if (!$tenantId) {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user?->tenant_id;
        }
        
        // Fallback pour le développement - toujours utiliser tenant 1
        if (!$tenantId) {
            $tenantId = 1;
        }
        
        \Log::info("AccountingController getTenantId: $tenantId");
        
        return (int) $tenantId;
    }

    /**
     * Requête de base pour les ventes comptabilisables
     * Inclut: completed, paid, ou draft avec paiement effectué
     */
    protected function salesBaseQuery($tenantId)
    {
        return Sale::where('tenant_id', $tenantId)
            ->where(function($q) {
                $q->whereIn('status', ['completed', 'paid'])
                  ->orWhere(function($q2) {
                      $q2->where('status', 'draft')
                         ->where('amount_paid', '>', 0);
                  });
            });
    }

    /**
     * Requête de base pour les achats comptabilisables
     */
    protected function purchasesBaseQuery($tenantId)
    {
        return Purchase::where('tenant_id', $tenantId)
            ->whereIn('status', ['received', 'completed', 'partial']);
    }

    /**
     * Requête de base pour les dépenses
     */
    protected function expensesBaseQuery($tenantId, $startDate, $endDate)
    {
        return Expense::where('tenant_id', $tenantId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->where(function($q2) use ($startDate, $endDate) {
                    $q2->whereNotNull('expense_date')
                       ->whereDate('expense_date', '>=', $startDate)
                       ->whereDate('expense_date', '<=', $endDate);
                })->orWhere(function($q2) use ($startDate, $endDate) {
                    $q2->whereNull('expense_date')
                       ->whereDate('created_at', '>=', $startDate)
                       ->whereDate('created_at', '<=', $endDate);
                });
            });
    }

    /**
     * Résumé comptable - Données réelles des ventes, achats, dépenses
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $periodStart = $request->query('period_start') ?? now()->startOfYear()->toDateString();
        $periodEnd = $request->query('period_end') ?? now()->toDateString();

        \Log::info("Summary period: $periodStart to $periodEnd, tenant: $tenantId");

        // Ventes (completed, paid, ou draft avec paiement)
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $periodStart)
            ->whereDate('created_at', '<=', $periodEnd);
        
        // Utiliser subtotal (HT) pour cohérence avec le Compte de Résultat
        $totalSales = (clone $salesQuery)->sum('subtotal') ?? 0;
        
        \Log::info("Total sales found (HT): $totalSales");
        $costOfGoodsSold = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;
        $totalTax = (clone $salesQuery)->sum('tax_amount') ?? 0;

        // Achats reçus (fournisseurs)
        $totalPurchases = $this->purchasesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $periodStart)
            ->whereDate('created_at', '<=', $periodEnd)
            ->sum('total') ?? 0;

        // Dépenses
        $totalExpenses = $this->expensesBaseQuery($tenantId, $periodStart, $periodEnd)
            ->sum('amount') ?? 0;

        // Valeur du stock
        $stockValue = Stock::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * COALESCE(cost_average, unit_cost, 0)) as total')
            ->value('total') ?? 0;

        // Marge brute
        $grossProfit = $totalSales - $costOfGoodsSold;
        
        // Résultat net
        $netIncome = $grossProfit - $totalExpenses;

        // Écritures en attente
        $unpostedEntries = AccountingEntry::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->count();

        return response()->json([
            'period' => ['start' => $periodStart, 'end' => $periodEnd],
            'total_sales' => (float) $totalSales,
            'cost_of_goods_sold' => (float) $costOfGoodsSold,
            'gross_profit' => (float) $grossProfit,
            'total_purchases' => (float) $totalPurchases,
            'total_expenses' => (float) $totalExpenses,
            'net_income' => (float) $netIncome,
            'total_tax' => (float) $totalTax,
            'stock_value' => (float) $stockValue,
            'unposted_entries' => $unpostedEntries,
            'profit_margin' => $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 2) : 0,
        ]);
    }

    /**
     * Compte de résultat SYSCOHADA (Norme OHADA/UEMOA)
     * Structure conforme au Plan Comptable OHADA révisé
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // === ACTIVITÉS D'EXPLOITATION ===
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
        
        // TA - Chiffre d'affaires (Compte 70)
        $chiffreAffaires = (clone $salesQuery)->sum('subtotal') ?? 0;
        
        // RA - Achats consommés (Compte 60)
        $achatsConsommes = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;

        // Dépenses par catégorie SYSCOHADA
        $expenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->toArray();

        // RB - Autres achats consommés (Compte 61/62)
        $autresAchats = ($expenses['fournitures'] ?? 0) + ($expenses['maintenance'] ?? 0);
        
        // RC - Transports consommés (Compte 61)
        $transports = $expenses['transport'] ?? 0;
        
        // RD - Services extérieurs (Compte 62/63)
        $servicesExterieurs = ($expenses['loyer'] ?? 0) + ($expenses['electricite'] ?? 0) + 
                              ($expenses['eau'] ?? 0) + ($expenses['marketing'] ?? 0);
        
        // RE - Impôts et taxes (Compte 64)
        $impotsTaxes = $expenses['taxes'] ?? 0;
        
        // RF - Autres charges (Compte 65)
        $autresCharges = $expenses['autres'] ?? 0;
        
        // RG - Charges de personnel (Compte 66)
        $chargesPersonnel = $expenses['salaires'] ?? 0;

        // === CALCULS SYSCOHADA ===
        
        // XA - MARGE BRUTE SUR MARCHANDISES
        $margeBrute = $chiffreAffaires - $achatsConsommes;
        
        // XB - VALEUR AJOUTÉE
        $valeurAjoutee = $margeBrute - $autresAchats - $transports - $servicesExterieurs;
        
        // XC - EXCÉDENT BRUT D'EXPLOITATION (EBE)
        $ebe = $valeurAjoutee - $impotsTaxes - $chargesPersonnel;
        
        // XD - RÉSULTAT D'EXPLOITATION
        $resultatExploitation = $ebe - $autresCharges;
        
        // Pour simplification (pas de charges financières ni HAO)
        // XI - RÉSULTAT FINANCIER = 0
        $resultatFinancier = 0;
        
        // XE - RÉSULTAT DES ACTIVITÉS ORDINAIRES (RAO)
        $rao = $resultatExploitation + $resultatFinancier;
        
        // XF - RÉSULTAT HAO = 0 (Hors Activités Ordinaires)
        $resultatHAO = 0;
        
        // XG - RÉSULTAT NET
        $resultatNet = $rao + $resultatHAO;

        $totalCharges = $achatsConsommes + $autresAchats + $transports + $servicesExterieurs + 
                        $impotsTaxes + $chargesPersonnel + $autresCharges;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'norme' => 'SYSCOHADA',
            
            // Produits d'exploitation
            'produits' => [
                'TA_chiffre_affaires' => (float) $chiffreAffaires,
                'total_produits_exploitation' => (float) $chiffreAffaires,
            ],
            
            // Charges d'exploitation (structure SYSCOHADA)
            'charges' => [
                'RA_achats_marchandises' => (float) $achatsConsommes,
                'RB_autres_achats' => (float) $autresAchats,
                'RC_transports' => (float) $transports,
                'RD_services_exterieurs' => (float) $servicesExterieurs,
                'RE_impots_taxes' => (float) $impotsTaxes,
                'RF_autres_charges' => (float) $autresCharges,
                'RG_charges_personnel' => (float) $chargesPersonnel,
                'total_charges_exploitation' => (float) $totalCharges,
            ],
            
            // Soldes Intermédiaires de Gestion (SIG) SYSCOHADA
            'sig' => [
                'XA_marge_brute' => (float) $margeBrute,
                'XB_valeur_ajoutee' => (float) $valeurAjoutee,
                'XC_ebe' => (float) $ebe,
                'XD_resultat_exploitation' => (float) $resultatExploitation,
                'XI_resultat_financier' => (float) $resultatFinancier,
                'XE_rao' => (float) $rao,
                'XF_resultat_hao' => (float) $resultatHAO,
                'XG_resultat_net' => (float) $resultatNet,
            ],
            
            // Ratios
            'ratios' => [
                'taux_marge_brute' => $chiffreAffaires > 0 ? round(($margeBrute / $chiffreAffaires) * 100, 2) : 0,
                'taux_valeur_ajoutee' => $chiffreAffaires > 0 ? round(($valeurAjoutee / $chiffreAffaires) * 100, 2) : 0,
                'taux_ebe' => $chiffreAffaires > 0 ? round(($ebe / $chiffreAffaires) * 100, 2) : 0,
                'taux_resultat_net' => $chiffreAffaires > 0 ? round(($resultatNet / $chiffreAffaires) * 100, 2) : 0,
            ],
            
            // Compatibilité avec l'ancien format
            'revenue' => ['sales' => (float) $chiffreAffaires, 'total' => (float) $chiffreAffaires],
            'cost_of_goods_sold' => (float) $achatsConsommes,
            'gross_profit' => (float) $margeBrute,
            'expenses' => $expenses,
            'total_expenses' => (float) ($totalCharges - $achatsConsommes),
            'operating_income' => (float) $resultatExploitation,
            'net_income' => (float) $resultatNet,
            'gross_margin' => $chiffreAffaires > 0 ? round(($margeBrute / $chiffreAffaires) * 100, 2) : 0,
            'net_margin' => $chiffreAffaires > 0 ? round(($resultatNet / $chiffreAffaires) * 100, 2) : 0,
        ]);
    }

    /**
     * Bilan SYSCOHADA (Norme OHADA/UEMOA)
     * Structure conforme au Plan Comptable OHADA révisé
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $asOfDate = $request->query('as_of_date') ?? now()->toDateString();

        // === ACTIF IMMOBILISÉ (Classe 2) ===
        // Pour une entreprise commerciale simple, pas d'immobilisations
        $immobilisationsIncorporelles = 0; // Compte 21
        $immobilisationsCorporelles = 0;   // Compte 22-24
        $immobilisationsFinancieres = 0;   // Compte 26-27
        $totalActifImmobilise = $immobilisationsIncorporelles + $immobilisationsCorporelles + $immobilisationsFinancieres;

        // === ACTIF CIRCULANT (Classe 3-4) ===
        
        // BJ - Stocks (Compte 31-38)
        $stockValue = Stock::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * COALESCE(cost_average, unit_cost, 0)) as total')
            ->value('total') ?? 0;

        // BK - Créances clients (Compte 411)
        $creancesClients = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '<=', $asOfDate)
            ->selectRaw('SUM(total - amount_paid) as total')
            ->value('total') ?? 0;

        // BL - Autres créances (Compte 42-48)
        $autresCreances = 0;

        $totalActifCirculant = $stockValue + max(0, $creancesClients) + $autresCreances;

        // === TRÉSORERIE ACTIF (Classe 5) ===
        
        // BQ - Banque (Compte 52)
        $banque = $this->salesBaseQuery($tenantId)
            ->whereIn('payment_method', ['card', 'mobile', 'bank'])
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('amount_paid') ?? 0;

        // BR - Caisse (Compte 57)
        $cashFromSales = $this->salesBaseQuery($tenantId)
            ->where('payment_method', 'cash')
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('amount_paid') ?? 0;

        $cashExpenses = Expense::where('tenant_id', $tenantId)
            ->where(function($q) {
                $q->where('payment_method', 'cash')
                  ->orWhereNull('payment_method');
            })
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('amount') ?? 0;

        $caisse = max(0, $cashFromSales - $cashExpenses);
        $totalTresorerieActif = $banque + $caisse;

        // TOTAL ACTIF
        $totalActif = $totalActifImmobilise + $totalActifCirculant + $totalTresorerieActif;

        // === CAPITAUX PROPRES (Classe 1) ===
        
        // CA - Capital social (Compte 10)
        $capitalSocial = 0; // À implémenter si nécessaire

        // CB - Réserves (Compte 11)
        $reserves = 0;

        // CD - Report à nouveau (Compte 12)
        $reportNouveau = 0;

        // CF - Résultat net de l'exercice (Compte 13)
        $salesQueryEquity = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '<=', $asOfDate);
        
        $totalSales = (clone $salesQueryEquity)->sum('subtotal') ?? 0;
        $totalCogs = (clone $salesQueryEquity)->sum('cost_of_goods_sold') ?? 0;

        $totalExpenses = Expense::where('tenant_id', $tenantId)
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('amount') ?? 0;

        $resultatNet = $totalSales - $totalCogs - $totalExpenses;
        $totalCapitauxPropres = $capitalSocial + $reserves + $reportNouveau + $resultatNet;

        // === DETTES FINANCIÈRES (Classe 1) ===
        $dettesFinancieres = 0; // Compte 16-17

        // === PASSIF CIRCULANT (Classe 4) ===
        
        // DI - Fournisseurs (Compte 401)
        $dettesFournisseurs = Purchase::where('tenant_id', $tenantId)
            ->whereIn('status', ['ordered', 'confirmed', 'partial'])
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('total') ?? 0;

        // DJ - Dettes fiscales (Compte 44)
        $dettesFiscales = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '<=', $asOfDate)
            ->sum('tax_amount') ?? 0;

        // DK - Dettes sociales (Compte 43)
        $dettesSociales = 0;

        // DL - Autres dettes (Compte 45-48)
        $autresDettes = 0;

        $totalPassifCirculant = $dettesFournisseurs + $dettesFiscales + $dettesSociales + $autresDettes;

        // === TRÉSORERIE PASSIF (Classe 5) ===
        $tresoreriePassif = 0; // Découverts bancaires

        // TOTAL PASSIF
        $totalPassif = $totalCapitauxPropres + $dettesFinancieres + $totalPassifCirculant + $tresoreriePassif;

        return response()->json([
            'as_of_date' => $asOfDate,
            'norme' => 'SYSCOHADA',
            
            // ACTIF (Structure SYSCOHADA)
            'actif' => [
                'actif_immobilise' => [
                    'AE_immobilisations_incorporelles' => (float) $immobilisationsIncorporelles,
                    'AI_immobilisations_corporelles' => (float) $immobilisationsCorporelles,
                    'AQ_immobilisations_financieres' => (float) $immobilisationsFinancieres,
                    'AZ_total_actif_immobilise' => (float) $totalActifImmobilise,
                ],
                'actif_circulant' => [
                    'BJ_stocks' => (float) $stockValue,
                    'BK_creances_clients' => (float) max(0, $creancesClients),
                    'BL_autres_creances' => (float) $autresCreances,
                    'BZ_total_actif_circulant' => (float) $totalActifCirculant,
                ],
                'tresorerie_actif' => [
                    'BQ_banque' => (float) $banque,
                    'BR_caisse' => (float) $caisse,
                    'BT_total_tresorerie_actif' => (float) $totalTresorerieActif,
                ],
                'BZ_total_actif' => (float) $totalActif,
            ],
            
            // PASSIF (Structure SYSCOHADA)
            'passif' => [
                'capitaux_propres' => [
                    'CA_capital_social' => (float) $capitalSocial,
                    'CB_reserves' => (float) $reserves,
                    'CD_report_nouveau' => (float) $reportNouveau,
                    'CF_resultat_net' => (float) $resultatNet,
                    'CP_total_capitaux_propres' => (float) $totalCapitauxPropres,
                ],
                'dettes_financieres' => [
                    'DA_emprunts' => (float) $dettesFinancieres,
                    'DF_total_dettes_financieres' => (float) $dettesFinancieres,
                ],
                'passif_circulant' => [
                    'DI_fournisseurs' => (float) $dettesFournisseurs,
                    'DJ_dettes_fiscales' => (float) $dettesFiscales,
                    'DK_dettes_sociales' => (float) $dettesSociales,
                    'DL_autres_dettes' => (float) $autresDettes,
                    'DZ_total_passif_circulant' => (float) $totalPassifCirculant,
                ],
                'tresorerie_passif' => [
                    'DT_decouverts_bancaires' => (float) $tresoreriePassif,
                ],
                'DZ_total_passif' => (float) $totalPassif,
            ],
            
            // Équilibre du bilan
            'equilibre' => [
                'total_actif' => (float) $totalActif,
                'total_passif' => (float) $totalPassif,
                'ecart' => (float) abs($totalActif - $totalPassif),
                'is_balanced' => abs($totalActif - $totalPassif) < 1,
            ],
            
            // Compatibilité avec l'ancien format
            'assets' => [
                'current' => [
                    'cash' => (float) $caisse,
                    'inventory' => (float) $stockValue,
                    'accounts_receivable' => (float) max(0, $creancesClients),
                ],
                'total' => (float) $totalActif,
            ],
            'liabilities' => [
                'current' => [
                    'accounts_payable' => (float) $dettesFournisseurs,
                    'tax_payable' => (float) $dettesFiscales,
                ],
                'total' => (float) $totalPassifCirculant,
            ],
            'equity' => [
                'retained_earnings' => (float) $resultatNet,
                'total' => (float) $totalCapitauxPropres,
            ],
            'total_liabilities_and_equity' => (float) $totalPassif,
            'is_balanced' => abs($totalActif - $totalPassif) < 1,
        ]);
    }

    /**
     * Balance générale (Trial Balance)
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Construire la balance à partir des données réelles
        $accounts = [];

        // Ventes
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
        
        $sales = (clone $salesQuery)->sum('subtotal') ?? 0;

        if ($sales > 0) {
            $accounts[] = [
                'account_code' => '701',
                'account_name' => 'Ventes de marchandises',
                'account_type' => 'revenue',
                'debit' => 0,
                'credit' => $sales,
                'balance' => -$sales,
            ];
        }

        // Coût des ventes
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;

        if ($cogs > 0) {
            $accounts[] = [
                'account_code' => '601',
                'account_name' => 'Achats de marchandises',
                'account_type' => 'expense',
                'debit' => $cogs,
                'credit' => 0,
                'balance' => $cogs,
            ];
        }

        // Dépenses par catégorie
        $expenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        $expenseAccounts = [
            'salaires' => ['code' => '641', 'name' => 'Charges de personnel'],
            'loyer' => ['code' => '613', 'name' => 'Loyers et charges locatives'],
            'electricite' => ['code' => '606', 'name' => 'Électricité'],
            'eau' => ['code' => '606', 'name' => 'Eau'],
            'transport' => ['code' => '624', 'name' => 'Transports'],
            'fournitures' => ['code' => '606', 'name' => 'Fournitures'],
            'marketing' => ['code' => '623', 'name' => 'Publicité et marketing'],
            'maintenance' => ['code' => '615', 'name' => 'Entretien et réparations'],
            'autres' => ['code' => '658', 'name' => 'Charges diverses'],
        ];

        foreach ($expenses as $exp) {
            $cat = strtolower($exp->category);
            $acc = $expenseAccounts[$cat] ?? $expenseAccounts['autres'];
            $accounts[] = [
                'account_code' => $acc['code'],
                'account_name' => $acc['name'] . ' (' . ucfirst($exp->category) . ')',
                'account_type' => 'expense',
                'debit' => (float) $exp->total,
                'credit' => 0,
                'balance' => (float) $exp->total,
            ];
        }

        // Achats fournisseurs
        $purchases = $this->purchasesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('total') ?? 0;

        if ($purchases > 0) {
            $accounts[] = [
                'account_code' => '601',
                'account_name' => 'Achats de marchandises (fournisseurs)',
                'account_type' => 'expense',
                'debit' => (float) $purchases,
                'credit' => 0,
                'balance' => (float) $purchases,
            ];
            $accounts[] = [
                'account_code' => '401',
                'account_name' => 'Fournisseurs',
                'account_type' => 'liability',
                'debit' => 0,
                'credit' => (float) $purchases,
                'balance' => -(float) $purchases,
            ];
        }

        // Caisse
        $cashIn = (clone $salesQuery)
            ->where('payment_method', 'cash')
            ->sum('amount_paid') ?? 0;

        $cashOut = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->sum('amount') ?? 0;

        if ($cashIn > 0 || $cashOut > 0) {
            $accounts[] = [
                'account_code' => '571',
                'account_name' => 'Caisse',
                'account_type' => 'asset',
                'debit' => (float) $cashIn,
                'credit' => (float) $cashOut,
                'balance' => (float) ($cashIn - $cashOut),
            ];
        }

        // TVA
        $tax = (clone $salesQuery)->sum('tax_amount') ?? 0;

        if ($tax > 0) {
            $accounts[] = [
                'account_code' => '4457',
                'account_name' => 'TVA collectée',
                'account_type' => 'liability',
                'debit' => 0,
                'credit' => (float) $tax,
                'balance' => -(float) $tax,
            ];
        }

        // Clients (créances)
        $receivables = (clone $salesQuery)
            ->selectRaw('SUM(total - amount_paid) as total')
            ->value('total') ?? 0;

        if ($receivables > 0) {
            $accounts[] = [
                'account_code' => '411',
                'account_name' => 'Clients',
                'account_type' => 'asset',
                'debit' => (float) $receivables,
                'credit' => 0,
                'balance' => (float) $receivables,
            ];
        }

        // Immobilisations et Amortissements
        $immobilisations = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('date_acquisition', '<=', $endDate)
            ->get();

        $immoByCategory = [];
        $amortByCategory = [];
        $totalDotation = 0;

        foreach ($immobilisations as $immo) {
            $catCode = substr($immo->category_code, 0, 2);
            $base = $immo->valeur_acquisition - $immo->valeur_residuelle;
            $annuite = $base / $immo->duree_vie;
            
            // Regrouper par catégorie
            if (!isset($immoByCategory[$catCode])) {
                $immoByCategory[$catCode] = ['valeur' => 0, 'label' => $immo->category_label];
                $amortByCategory[$catCode] = ['cumul' => 0, 'annuite' => 0];
            }
            $immoByCategory[$catCode]['valeur'] += $immo->valeur_acquisition;
            $amortByCategory[$catCode]['cumul'] += $immo->cumul_amortissement;
            $amortByCategory[$catCode]['annuite'] += $annuite;
            $totalDotation += $annuite;
        }

        // Ajouter les comptes d'immobilisations (classe 2)
        foreach ($immoByCategory as $catCode => $data) {
            $accounts[] = [
                'account_code' => '2' . $catCode,
                'account_name' => $data['label'] ?? 'Immobilisations',
                'account_type' => 'asset',
                'debit' => (float) $data['valeur'],
                'credit' => 0,
                'balance' => (float) $data['valeur'],
            ];
        }

        // Ajouter les comptes d'amortissements (classe 28)
        foreach ($amortByCategory as $catCode => $data) {
            if ($data['cumul'] > 0) {
                $accounts[] = [
                    'account_code' => '28' . $catCode,
                    'account_name' => 'Amortissements ' . ($immoByCategory[$catCode]['label'] ?? 'Immobilisations'),
                    'account_type' => 'contra_asset',
                    'debit' => 0,
                    'credit' => (float) $data['cumul'],
                    'balance' => -(float) $data['cumul'],
                ];
            }
        }

        // Dotations aux amortissements (compte 681)
        if ($totalDotation > 0) {
            $accounts[] = [
                'account_code' => '681',
                'account_name' => 'Dotations aux amortissements d\'exploitation',
                'account_type' => 'expense',
                'debit' => round($totalDotation, 2),
                'credit' => 0,
                'balance' => round($totalDotation, 2),
            ];
        }

        $totalDebit = array_sum(array_column($accounts, 'debit'));
        $totalCredit = array_sum(array_column($accounts, 'credit'));

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'accounts' => $accounts,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 1,
        ]);
    }

    /**
     * Grand Livre
     */
    public function ledger(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();
        $accountCode = $request->query('account_code');

        $entries = [];

        // Ventes
        if (!$accountCode || in_array($accountCode, ['701', '571', '4457'])) {
            $sales = $this->salesBaseQuery($tenantId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($sales as $sale) {
                // Écriture vente
                $entries[] = [
                    'date' => $sale->created_at->toDateString(),
                    'reference' => $sale->reference,
                    'description' => 'Vente ' . ($sale->customer_name ?: 'Client comptoir'),
                    'account_code' => '701',
                    'account_name' => 'Ventes de marchandises',
                    'debit' => 0,
                    'credit' => (float) $sale->subtotal,
                    'source' => 'sale',
                    'source_id' => $sale->id,
                ];

                // Encaissement
                if ($sale->amount_paid > 0) {
                    $entries[] = [
                        'date' => $sale->created_at->toDateString(),
                        'reference' => $sale->reference,
                        'description' => 'Encaissement ' . ucfirst($sale->payment_method),
                        'account_code' => '571',
                        'account_name' => 'Caisse',
                        'debit' => (float) $sale->amount_paid,
                        'credit' => 0,
                        'source' => 'sale',
                        'source_id' => $sale->id,
                    ];
                }

                // TVA
                if ($sale->tax_amount > 0) {
                    $entries[] = [
                        'date' => $sale->created_at->toDateString(),
                        'reference' => $sale->reference,
                        'description' => 'TVA collectée',
                        'account_code' => '4457',
                        'account_name' => 'TVA collectée',
                        'debit' => 0,
                        'credit' => (float) $sale->tax_amount,
                        'source' => 'sale',
                        'source_id' => $sale->id,
                    ];
                }
            }
        }

        // Dépenses
        if (!$accountCode || str_starts_with($accountCode, '6')) {
            $expenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($expenses as $expense) {
                $entries[] = [
                    'date' => ($expense->expense_date ?? $expense->date ?? $expense->created_at)->format('Y-m-d'),
                    'reference' => 'DEP-' . $expense->id,
                    'description' => $expense->description,
                    'account_code' => '6' . substr(md5($expense->category), 0, 2),
                    'account_name' => 'Charges - ' . ucfirst($expense->category),
                    'debit' => (float) $expense->amount,
                    'credit' => 0,
                    'source' => 'expense',
                    'source_id' => $expense->id,
                ];
            }
        }

        // Achats fournisseurs
        if (!$accountCode || in_array($accountCode, ['601', '401'])) {
            $purchases = $this->purchasesBaseQuery($tenantId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($purchases as $purchase) {
                // Écriture achat marchandises
                $entries[] = [
                    'date' => ($purchase->received_date ?? $purchase->created_at)->format('Y-m-d'),
                    'reference' => $purchase->reference,
                    'description' => 'Achat ' . ($purchase->supplier_name ?? 'Fournisseur'),
                    'account_code' => '601',
                    'account_name' => 'Achats de marchandises',
                    'debit' => (float) $purchase->total,
                    'credit' => 0,
                    'source' => 'purchase',
                    'source_id' => $purchase->id,
                ];

                // Écriture dette fournisseur
                $entries[] = [
                    'date' => ($purchase->received_date ?? $purchase->created_at)->format('Y-m-d'),
                    'reference' => $purchase->reference,
                    'description' => 'Dette fournisseur ' . ($purchase->supplier_name ?? 'Fournisseur'),
                    'account_code' => '401',
                    'account_name' => 'Fournisseurs',
                    'debit' => 0,
                    'credit' => (float) $purchase->total,
                    'source' => 'purchase',
                    'source_id' => $purchase->id,
                ];
            }
        }

        // Dotations aux amortissements (comptes 68 et 28)
        if (!$accountCode || str_starts_with($accountCode, '68') || str_starts_with($accountCode, '28')) {
            $immobilisations = DB::table('immobilisations')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('date_acquisition', '<=', $endDate)
                ->get();

            foreach ($immobilisations as $immo) {
                $base = $immo->valeur_acquisition - $immo->valeur_residuelle;
                $annuite = $base / $immo->duree_vie;
                
                // Calculer l'amortissement pour la période
                $startYear = date('Y', strtotime($immo->date_acquisition));
                $endYear = date('Y', strtotime($endDate));
                
                // Déterminer le compte d'amortissement selon la catégorie
                $compteAmort = '28' . substr($immo->category_code, 0, 2);
                $compteDotation = '681'; // Dotations aux amortissements d'exploitation
                
                $categoryLabels = [
                    '21' => 'Immobilisations incorporelles',
                    '22' => 'Terrains',
                    '23' => 'Bâtiments',
                    '24' => 'Matériel et outillage',
                    '244' => 'Matériel et mobilier de bureau',
                    '245' => 'Matériel de transport',
                    '246' => 'Matériel informatique',
                ];
                
                $categoryLabel = $categoryLabels[$immo->category_code] ?? 'Immobilisations';
                
                // Écriture de dotation aux amortissements
                $entries[] = [
                    'date' => $endDate,
                    'reference' => 'AMORT-' . $immo->id,
                    'description' => 'Dotation amortissement ' . $immo->designation,
                    'account_code' => $compteDotation,
                    'account_name' => 'Dotations aux amortissements',
                    'debit' => round($annuite, 2),
                    'credit' => 0,
                    'source' => 'immobilisation',
                    'source_id' => $immo->id,
                ];
                
                // Écriture d'amortissement cumulé
                $entries[] = [
                    'date' => $endDate,
                    'reference' => 'AMORT-' . $immo->id,
                    'description' => 'Amortissement ' . $immo->designation,
                    'account_code' => $compteAmort,
                    'account_name' => 'Amortissements ' . $categoryLabel,
                    'debit' => 0,
                    'credit' => round($annuite, 2),
                    'source' => 'immobilisation',
                    'source_id' => $immo->id,
                ];
            }
        }

        // Trier par date
        usort($entries, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Filtrer par compte si demandé
        if ($accountCode) {
            $entries = array_filter($entries, fn($e) => $e['account_code'] === $accountCode);
            $entries = array_values($entries);
        }

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'entries' => $entries,
            'total_entries' => count($entries),
        ]);
    }

    /**
     * Journal comptable
     */
    public function journal(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();
        $journalType = $request->query('journal_type'); // ventes, achats, caisse, operations

        $entries = [];

        // Journal des ventes
        if (!$journalType || $journalType === 'ventes') {
            $sales = $this->salesBaseQuery($tenantId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($sales as $sale) {
                $entries[] = [
                    'date' => $sale->created_at->toDateString(),
                    'journal' => 'VT',
                    'journal_name' => 'Journal des Ventes',
                    'reference' => $sale->reference,
                    'description' => 'Vente ' . ($sale->customer_name ?: 'Client comptoir'),
                    'lines' => [
                        ['account' => '411', 'label' => 'Clients', 'debit' => $sale->total, 'credit' => 0],
                        ['account' => '701', 'label' => 'Ventes', 'debit' => 0, 'credit' => $sale->subtotal],
                        ['account' => '4457', 'label' => 'TVA collectée', 'debit' => 0, 'credit' => $sale->tax_amount],
                    ],
                    'total' => (float) $sale->total,
                ];
            }
        }

        // Journal de caisse
        if (!$journalType || $journalType === 'caisse') {
            $cashSales = $this->salesBaseQuery($tenantId)
                ->where('payment_method', 'cash')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($cashSales as $sale) {
                $entries[] = [
                    'date' => $sale->created_at->toDateString(),
                    'journal' => 'CA',
                    'journal_name' => 'Journal de Caisse',
                    'reference' => $sale->reference,
                    'description' => 'Encaissement vente',
                    'lines' => [
                        ['account' => '571', 'label' => 'Caisse', 'debit' => $sale->amount_paid, 'credit' => 0],
                        ['account' => '411', 'label' => 'Clients', 'debit' => 0, 'credit' => $sale->amount_paid],
                    ],
                    'total' => (float) $sale->amount_paid,
                ];
            }

            // Dépenses en espèces
            $cashExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($cashExpenses as $exp) {
                $entries[] = [
                    'date' => ($exp->expense_date ?? $exp->date ?? $exp->created_at)->format('Y-m-d'),
                    'journal' => 'CA',
                    'journal_name' => 'Journal de Caisse',
                    'reference' => 'DEP-' . $exp->id,
                    'description' => $exp->description,
                    'lines' => [
                        ['account' => '6', 'label' => 'Charges - ' . $exp->category, 'debit' => $exp->amount, 'credit' => 0],
                        ['account' => '571', 'label' => 'Caisse', 'debit' => 0, 'credit' => $exp->amount],
                    ],
                    'total' => (float) $exp->amount,
                ];
            }
        }

        // Journal des achats (fournisseurs)
        if (!$journalType || $journalType === 'achats') {
            $purchases = $this->purchasesBaseQuery($tenantId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at')
                ->get();

            foreach ($purchases as $purchase) {
                $entries[] = [
                    'date' => ($purchase->received_date ?? $purchase->created_at)->format('Y-m-d'),
                    'journal' => 'AC',
                    'journal_name' => 'Journal des Achats',
                    'reference' => $purchase->reference,
                    'description' => 'Achat ' . ($purchase->supplier_name ?? 'Fournisseur'),
                    'lines' => [
                        ['account' => '601', 'label' => 'Achats marchandises', 'debit' => (float) $purchase->total, 'credit' => 0],
                        ['account' => '401', 'label' => 'Fournisseurs', 'debit' => 0, 'credit' => (float) $purchase->total],
                    ],
                    'total' => (float) $purchase->total,
                    'supplier' => $purchase->supplier_name ?? 'Fournisseur',
                    'status' => $purchase->status,
                ];
            }
        }

        // Trier par date
        usort($entries, fn($a, $b) => strcmp($a['date'], $b['date']));

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'journal_type' => $journalType,
            'entries' => $entries,
            'total_entries' => count($entries),
        ]);
    }

    /**
     * Soldes Intermédiaires de Gestion (SIG)
     */
    public function sig(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Chiffre d'affaires
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
        
        $salesRevenue = (clone $salesQuery)->sum('subtotal') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;

        // Marge commerciale
        $commercialMargin = $salesRevenue - $cogs;

        // Charges externes
        $externalCharges = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->whereIn('category', ['loyer', 'electricite', 'eau', 'transport', 'fournitures', 'marketing', 'maintenance'])
            ->sum('amount') ?? 0;

        // Valeur ajoutée
        $valueAdded = $commercialMargin - $externalCharges;

        // Charges de personnel
        $personnelCharges = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->where('category', 'salaires')
            ->sum('amount') ?? 0;

        // Excédent brut d'exploitation (EBE)
        $ebe = $valueAdded - $personnelCharges;

        // Autres charges
        $otherCharges = $this->expensesBaseQuery($tenantId, $startDate, $endDate)
            ->whereNotIn('category', ['loyer', 'electricite', 'eau', 'transport', 'fournitures', 'marketing', 'maintenance', 'salaires'])
            ->sum('amount') ?? 0;

        // Résultat d'exploitation
        $operatingResult = $ebe - $otherCharges;

        // Résultat net (simplifié)
        $netResult = $operatingResult;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'chiffre_affaires' => (float) $salesRevenue,
            'cout_achat_marchandises' => (float) $cogs,
            'marge_commerciale' => (float) $commercialMargin,
            'taux_marge' => $salesRevenue > 0 ? round(($commercialMargin / $salesRevenue) * 100, 2) : 0,
            'charges_externes' => (float) $externalCharges,
            'valeur_ajoutee' => (float) $valueAdded,
            'charges_personnel' => (float) $personnelCharges,
            'ebe' => (float) $ebe,
            'autres_charges' => (float) $otherCharges,
            'resultat_exploitation' => (float) $operatingResult,
            'resultat_net' => (float) $netResult,
        ]);
    }

    /**
     * Rapport de caisse
     */
    public function cashReport(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        // Support pour date unique ou période
        $startDate = $request->query('start_date') ?? $request->query('date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? $request->query('date') ?? now()->toDateString();

        // Encaissements de la période
        $cashIn = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw("
                payment_method,
                COUNT(*) as count,
                SUM(amount_paid) as total
            ")
            ->groupBy('payment_method')
            ->get();

        // Décaissements de la période
        $cashOut = Expense::where('tenant_id', $tenantId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('expense_date', [$startDate, $endDate])
                  ->orWhere(function($q2) use ($startDate, $endDate) {
                      $q2->whereNull('expense_date')
                         ->whereDate('created_at', '>=', $startDate)
                         ->whereDate('created_at', '<=', $endDate);
                  });
            })
            ->selectRaw("
                category,
                COUNT(*) as count,
                SUM(amount) as total
            ")
            ->groupBy('category')
            ->get();

        $totalIn = $cashIn->sum('total');
        $totalOut = $cashOut->sum('total');

        // Solde d'ouverture (avant la période)
        $previousBalance = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '<', $startDate)
            ->sum('amount_paid') ?? 0;

        $previousExpenses = Expense::where('tenant_id', $tenantId)
            ->whereDate('created_at', '<', $startDate)
            ->sum('amount') ?? 0;

        $openingBalance = $previousBalance - $previousExpenses;
        $closingBalance = $openingBalance + $totalIn - $totalOut;

        return response()->json([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'opening_balance' => (float) $openingBalance,
            'receipts' => $cashIn->map(fn($r) => [
                'method' => $r->payment_method ?? 'cash',
                'count' => $r->count,
                'total' => (float) $r->total,
            ]),
            'total_receipts' => (float) $totalIn,
            'disbursements' => $cashOut->map(fn($d) => [
                'category' => $d->category ?? 'autres',
                'count' => $d->count,
                'total' => (float) $d->total,
            ]),
            'total_disbursements' => (float) $totalOut,
            'closing_balance' => (float) $closingBalance,
            'net_movement' => (float) ($totalIn - $totalOut),
        ]);
    }

    /**
     * États Financiers Complets
     */
    public function financialStatements(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Données de base
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Utiliser subtotal (HT) pour cohérence comptable
        $revenue = (clone $salesQuery)->sum('subtotal') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;
        $taxCollected = (clone $salesQuery)->sum('tax_amount') ?? 0;
        $amountReceived = (clone $salesQuery)->sum('amount_paid') ?? 0;

        $totalExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)->sum('amount') ?? 0;

        $purchases = $this->purchasesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('total') ?? 0;

        // Stock
        $stockValue = Stock::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * COALESCE(cost_average, unit_cost, 0)) as total')
            ->value('total') ?? 0;

        // Calculs
        $grossProfit = $revenue - $cogs;
        $operatingProfit = $grossProfit - $totalExpenses;
        $netProfit = $operatingProfit;

        // Ratios
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        $netMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        $expenseRatio = $revenue > 0 ? ($totalExpenses / $revenue) * 100 : 0;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'income_statement' => [
                'revenue' => (float) $revenue,
                'cost_of_goods_sold' => (float) $cogs,
                'gross_profit' => (float) $grossProfit,
                'operating_expenses' => (float) $totalExpenses,
                'operating_profit' => (float) $operatingProfit,
                'net_profit' => (float) $netProfit,
            ],
            'balance_summary' => [
                'cash_received' => (float) $amountReceived,
                'stock_value' => (float) $stockValue,
                'purchases' => (float) $purchases,
                'expenses_paid' => (float) $totalExpenses,
            ],
            'tax_summary' => [
                'tax_collected' => (float) $taxCollected,
                'tax_payable' => (float) $taxCollected,
            ],
            'ratios' => [
                'gross_margin' => round($grossMargin, 2),
                'net_margin' => round($netMargin, 2),
                'expense_ratio' => round($expenseRatio, 2),
                'inventory_turnover' => $stockValue > 0 ? round($cogs / $stockValue, 2) : 0,
            ],
        ]);
    }

    /**
     * Capacité d'Autofinancement (CAF)
     */
    public function selfFinancingCapacity(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Résultat net
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Utiliser subtotal (HT) pour cohérence comptable
        $revenue = (clone $salesQuery)->sum('subtotal') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;
        $totalExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)->sum('amount') ?? 0;

        $grossProfit = $revenue - $cogs;
        $netResult = $grossProfit - $totalExpenses;

        // Pour une entreprise commerciale sans amortissements comptabilisés,
        // la CAF = Résultat Net + Dotations aux amortissements - Reprises
        // Simplifié ici car pas d'amortissements dans le système
        $dotationsAmortissements = 0; // À implémenter si nécessaire
        $reprises = 0;
        $plusValuesCessions = 0;
        $moinsValuesCessions = 0;

        // CAF = Résultat Net + Dotations - Reprises + Moins-values - Plus-values
        $caf = $netResult + $dotationsAmortissements - $reprises - $plusValuesCessions + $moinsValuesCessions;

        // Autofinancement = CAF - Dividendes distribués
        $dividendes = 0; // À implémenter si nécessaire
        $autofinancement = $caf - $dividendes;

        // Flux de trésorerie d'exploitation
        $cashFromSales = (clone $salesQuery)->sum('amount_paid') ?? 0;
        $cashExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)->sum('amount') ?? 0;
        $operatingCashFlow = $cashFromSales - $cashExpenses;

        // Variation du BFR (Besoin en Fonds de Roulement)
        $receivables = (clone $salesQuery)->selectRaw('SUM(total - amount_paid) as total')->value('total') ?? 0;
        $payables = $this->purchasesBaseQuery($tenantId)
            ->whereIn('status', ['ordered', 'confirmed', 'partial'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('total') ?? 0;

        $bfr = $receivables - $payables;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'resultat_net' => (float) $netResult,
            'dotations_amortissements' => (float) $dotationsAmortissements,
            'reprises' => (float) $reprises,
            'plus_values_cessions' => (float) $plusValuesCessions,
            'moins_values_cessions' => (float) $moinsValuesCessions,
            'capacite_autofinancement' => (float) $caf,
            'dividendes' => (float) $dividendes,
            'autofinancement' => (float) $autofinancement,
            'flux_tresorerie' => [
                'encaissements_exploitation' => (float) $cashFromSales,
                'decaissements_exploitation' => (float) $cashExpenses,
                'flux_exploitation' => (float) $operatingCashFlow,
            ],
            'bfr' => [
                'creances_clients' => (float) $receivables,
                'dettes_fournisseurs' => (float) $payables,
                'besoin_fonds_roulement' => (float) $bfr,
            ],
            'tresorerie_nette' => (float) ($operatingCashFlow - $bfr),
        ]);
    }

    /**
     * Tableau de Flux de Trésorerie
     */
    public function cashFlowStatement(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Flux d'exploitation
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        $cashFromCustomers = (clone $salesQuery)->sum('amount_paid') ?? 0;
        $cashToSuppliers = $this->purchasesBaseQuery($tenantId)
            ->whereIn('status', ['received', 'completed'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('total') ?? 0;

        $cashForExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)->sum('amount') ?? 0;

        $operatingCashFlow = $cashFromCustomers - $cashToSuppliers - $cashForExpenses;

        // Flux d'investissement (simplifié - pas d'immobilisations dans le système)
        $investingCashFlow = 0;

        // Flux de financement (simplifié)
        $financingCashFlow = 0;

        // Variation de trésorerie
        $netCashChange = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

        // Solde de trésorerie
        $openingCash = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '<', $startDate)
            ->sum('amount_paid') ?? 0;

        $openingExpenses = Expense::where('tenant_id', $tenantId)
            ->whereDate('created_at', '<', $startDate)
            ->sum('amount') ?? 0;

        $openingBalance = $openingCash - $openingExpenses;
        $closingBalance = $openingBalance + $netCashChange;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'flux_exploitation' => [
                'encaissements_clients' => (float) $cashFromCustomers,
                'paiements_fournisseurs' => (float) $cashToSuppliers,
                'paiements_charges' => (float) $cashForExpenses,
                'total' => (float) $operatingCashFlow,
            ],
            'flux_investissement' => [
                'acquisitions' => 0,
                'cessions' => 0,
                'total' => (float) $investingCashFlow,
            ],
            'flux_financement' => [
                'emprunts' => 0,
                'remboursements' => 0,
                'total' => (float) $financingCashFlow,
            ],
            'variation_tresorerie' => (float) $netCashChange,
            'tresorerie_ouverture' => (float) $openingBalance,
            'tresorerie_cloture' => (float) $closingBalance,
        ]);
    }

    /**
     * Ratios Financiers
     */
    public function financialRatios(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        $startDate = $request->query('start_date') ?? now()->startOfYear()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        // Données de base
        $salesQuery = $this->salesBaseQuery($tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Utiliser subtotal (HT) pour cohérence comptable
        $revenue = (clone $salesQuery)->sum('subtotal') ?? 0;
        $cogs = (clone $salesQuery)->sum('cost_of_goods_sold') ?? 0;
        $amountPaid = (clone $salesQuery)->sum('amount_paid') ?? 0;
        $totalExpenses = $this->expensesBaseQuery($tenantId, $startDate, $endDate)->sum('amount') ?? 0;

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $totalExpenses;

        // Actifs
        $stockValue = Stock::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * COALESCE(cost_average, unit_cost, 0)) as total')
            ->value('total') ?? 0;

        $receivables = (clone $salesQuery)->selectRaw('SUM(total - amount_paid) as total')->value('total') ?? 0;
        $cash = $amountPaid - $totalExpenses;

        $totalAssets = max(0, $cash) + $stockValue + max(0, $receivables);

        // Passifs
        $payables = $this->purchasesBaseQuery($tenantId)
            ->whereIn('status', ['ordered', 'confirmed', 'partial'])
            ->sum('total') ?? 0;

        // Ratios de rentabilité
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        $netMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        $roa = $totalAssets > 0 ? ($netProfit / $totalAssets) * 100 : 0;

        // Ratios de liquidité
        $currentRatio = $payables > 0 ? ($cash + $receivables) / $payables : 0;
        $quickRatio = $payables > 0 ? $cash / $payables : 0;

        // Ratios d'activité
        $inventoryTurnover = $stockValue > 0 ? $cogs / $stockValue : 0;
        $receivablesTurnover = $receivables > 0 ? $revenue / $receivables : 0;

        // Nombre de jours
        $daysInPeriod = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400);
        $daysInventory = $inventoryTurnover > 0 ? $daysInPeriod / $inventoryTurnover : 0;
        $daysReceivables = $receivablesTurnover > 0 ? $daysInPeriod / $receivablesTurnover : 0;

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'rentabilite' => [
                'marge_brute' => round($grossMargin, 2),
                'marge_nette' => round($netMargin, 2),
                'roa' => round($roa, 2),
            ],
            'liquidite' => [
                'ratio_liquidite_generale' => round($currentRatio, 2),
                'ratio_liquidite_immediate' => round($quickRatio, 2),
            ],
            'activite' => [
                'rotation_stocks' => round($inventoryTurnover, 2),
                'rotation_creances' => round($receivablesTurnover, 2),
                'delai_rotation_stocks' => round($daysInventory, 0),
                'delai_recouvrement' => round($daysReceivables, 0),
            ],
            'donnees_base' => [
                'chiffre_affaires' => (float) $revenue,
                'resultat_net' => (float) $netProfit,
                'total_actif' => (float) $totalAssets,
                'tresorerie' => (float) max(0, $cash),
                'stocks' => (float) $stockValue,
                'creances' => (float) max(0, $receivables),
                'dettes_fournisseurs' => (float) $payables,
            ],
        ]);
    }

    /**
     * Poster des écritures
     */
    public function postEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'exists:accounting_entries,id',
        ]);

        $tenantId = $this->getTenantId($request);

        try {
            DB::beginTransaction();

            foreach ($validated['entry_ids'] as $entryId) {
                $entry = AccountingEntry::find($entryId);
                
                if ($entry->tenant_id != $tenantId) {
                    throw new \Exception('Écriture non autorisée');
                }

                if ($entry->status === 'posted') {
                    continue;
                }

                $entry->update(['status' => 'posted']);
            }

            DB::commit();

            return response()->json(['message' => 'Écritures validées avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
