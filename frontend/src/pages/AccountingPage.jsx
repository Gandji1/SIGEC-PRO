import { useState, useEffect, useCallback, memo } from 'react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';
import { DollarSign, TrendingUp, TrendingDown, Activity, FileText, Book, Calculator, Wallet, PieChart, BarChart3, Download, RefreshCw, Calendar } from 'lucide-react';
import ExportButton from '../components/ExportButton';

const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
  style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
}).format(val || 0);

const formatPercent = (val) => `${(val || 0).toFixed(1)}%`;

const CardSkeleton = memo(() => (
  <div className="animate-pulse bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 mb-3"></div>
    <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-32"></div>
  </div>
));

const TableSkeleton = memo(() => (
  <div className="animate-pulse space-y-3">
    <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 dark:bg-gray-800 rounded"></div>)}
  </div>
));

// Stat Card moderne
const StatCard = memo(({ title, value, icon: Icon, color, subValue, trend }) => (
  <div className={`${color} rounded-xl p-4 sm:p-5 border shadow-sm hover:shadow-md transition-all`}>
    <div className="flex items-center justify-between">
      <div className="flex-1 min-w-0">
        <p className="text-xs sm:text-sm font-medium opacity-80 truncate">{title}</p>
        <p className="text-lg sm:text-xl font-bold mt-1 truncate">{value}</p>
        {subValue && <p className="text-xs opacity-70 mt-1">{subValue}</p>}
      </div>
      <div className="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0 ml-2">
        <Icon size={20} className="opacity-80" />
      </div>
    </div>
  </div>
));

export default function AccountingPage() {
  const { can } = usePermission();
  const [summary, setSummary] = useState({});
  const [incomeStatement, setIncomeStatement] = useState({});
  const [balanceSheet, setBalanceSheet] = useState({});
  const [trialBalance, setTrialBalance] = useState({ accounts: [] });
  const [sig, setSig] = useState({});
  const [journal, setJournal] = useState({ entries: [] });
  const [cashReport, setCashReport] = useState({});
  const [financialStatements, setFinancialStatements] = useState({});
  const [caf, setCaf] = useState({});
  const [cashFlow, setCashFlow] = useState({});
  const [ratios, setRatios] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('summary');
  const [dateRange, setDateRange] = useState({
    start: new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0], // Début de l'année
    end: new Date().toISOString().split('T')[0],
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    
    const params = `?period_start=${dateRange.start}&period_end=${dateRange.end}`;
    const dateParams = `?start_date=${dateRange.start}&end_date=${dateRange.end}`;
    
    try {
      // Fetch summary first to check if API is working
      const summaryRes = await apiClient.get(`/accounting/summary${params}`);
      console.log('Summary data received:', summaryRes.data);
      setSummary(summaryRes.data || {});
      
      // Fetch other reports in parallel
      const [incomeRes, balanceRes, trialRes, sigRes, journalRes, cashRes] = await Promise.all([
        apiClient.get(`/accounting/income-statement${dateParams}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/balance-sheet?as_of_date=${dateRange.end}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/trial-balance${dateParams}`).catch(() => ({ data: { accounts: [] } })),
        apiClient.get(`/accounting/sig${dateParams}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/journal${dateParams}`).catch(() => ({ data: { entries: [] } })),
        apiClient.get(`/accounting/cash-report${dateParams}`).catch(() => ({ data: {} })),
      ]);
      
      console.log('SIG data:', sigRes.data);
      console.log('Journal data:', journalRes.data);
      console.log('Cash data:', cashRes.data);
      
      setIncomeStatement(incomeRes.data || {});
      setBalanceSheet(balanceRes.data || {});
      setTrialBalance(trialRes.data || { accounts: [] });
      setSig(sigRes.data || {});
      setJournal(journalRes.data || { entries: [] });
      setCashReport(cashRes.data || {});
      
      // Fetch additional reports
      const [finRes, cafRes, flowRes, ratiosRes] = await Promise.all([
        apiClient.get(`/accounting/financial-statements${dateParams}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/caf${dateParams}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/cash-flow${dateParams}`).catch(() => ({ data: {} })),
        apiClient.get(`/accounting/ratios${dateParams}`).catch(() => ({ data: {} })),
      ]);
      
      setFinancialStatements(finRes.data || {});
      setCaf(cafRes.data || {});
      setCashFlow(flowRes.data || {});
      setRatios(ratiosRes.data || {});
      
    } catch (err) {
      console.error('Error fetching accounting data:', err);
      setError(err.response?.data?.message || err.message || 'Erreur de chargement des données comptables');
    } finally {
      setLoading(false);
    }
  }, [dateRange]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const tabs = [
    { id: 'summary', label: 'Résumé', icon: BarChart3 },
    { id: 'income', label: 'Compte de Résultat', icon: TrendingUp },
    { id: 'balance', label: 'Bilan', icon: Calculator },
    { id: 'sig', label: 'SIG', icon: PieChart },
    { id: 'trial', label: 'Balance', icon: Activity },
    { id: 'journal', label: 'Journal', icon: Book },
    { id: 'cash', label: 'Caisse', icon: Wallet },
    { id: 'financial', label: 'États Financiers', icon: FileText },
    { id: 'caf', label: 'CAF', icon: TrendingUp },
    { id: 'cashflow', label: 'Flux Trésorerie', icon: Activity },
    { id: 'ratios', label: 'Ratios', icon: PieChart },
  ];

  // Préparer les données pour l'export selon l'onglet actif
  const getExportData = () => {
    switch (activeTab) {
      case 'summary':
        return [
          { indicateur: 'Chiffre d\'affaires', valeur: summary.total_sales || 0 },
          { indicateur: 'Coût des ventes', valeur: summary.cost_of_goods_sold || 0 },
          { indicateur: 'Marge brute', valeur: summary.gross_profit || 0 },
          { indicateur: 'Charges d\'exploitation', valeur: summary.total_expenses || 0 },
          { indicateur: 'Résultat net', valeur: summary.net_income || 0 },
          { indicateur: 'Valeur du stock', valeur: summary.stock_value || 0 },
          { indicateur: 'TVA collectée', valeur: summary.total_tax || 0 },
          { indicateur: 'Taux de marge', valeur: `${summary.profit_margin || 0}%` }
        ];
      
      case 'income':
        return [
          { rubrique: 'PRODUITS D\'EXPLOITATION', code: '', montant: '' },
          { rubrique: 'TA - Chiffre d\'affaires', code: '70', montant: incomeStatement.produits?.TA_chiffre_affaires || incomeStatement.revenue?.sales || 0 },
          { rubrique: 'Total Produits', code: '', montant: incomeStatement.produits?.total_produits_exploitation || incomeStatement.revenue?.total || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'CHARGES D\'EXPLOITATION', code: '', montant: '' },
          { rubrique: 'RA - Achats de marchandises', code: '60', montant: incomeStatement.charges?.RA_achats_marchandises || incomeStatement.cost_of_goods_sold || 0 },
          { rubrique: 'RB - Autres achats', code: '61/62', montant: incomeStatement.charges?.RB_autres_achats || 0 },
          { rubrique: 'RC - Transports', code: '61', montant: incomeStatement.charges?.RC_transports || 0 },
          { rubrique: 'RD - Services extérieurs', code: '62/63', montant: incomeStatement.charges?.RD_services_exterieurs || 0 },
          { rubrique: 'RE - Impôts et taxes', code: '64', montant: incomeStatement.charges?.RE_impots_taxes || 0 },
          { rubrique: 'RF - Autres charges', code: '65', montant: incomeStatement.charges?.RF_autres_charges || 0 },
          { rubrique: 'RG - Charges de personnel', code: '66', montant: incomeStatement.charges?.RG_charges_personnel || 0 },
          { rubrique: 'Total Charges', code: '', montant: incomeStatement.charges?.total_charges_exploitation || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'SOLDES INTERMÉDIAIRES DE GESTION', code: '', montant: '' },
          { rubrique: 'XA - Marge brute', code: '', montant: incomeStatement.sig?.XA_marge_brute || incomeStatement.gross_profit || 0 },
          { rubrique: 'XB - Valeur ajoutée', code: '', montant: incomeStatement.sig?.XB_valeur_ajoutee || 0 },
          { rubrique: 'XC - Excédent Brut d\'Exploitation (EBE)', code: '', montant: incomeStatement.sig?.XC_ebe || 0 },
          { rubrique: 'XD - Résultat d\'exploitation', code: '', montant: incomeStatement.sig?.XD_resultat_exploitation || incomeStatement.operating_income || 0 },
          { rubrique: 'XI - Résultat financier', code: '', montant: incomeStatement.sig?.XI_resultat_financier || 0 },
          { rubrique: 'XE - Résultat des Activités Ordinaires', code: '', montant: incomeStatement.sig?.XE_rao || 0 },
          { rubrique: 'XF - Résultat HAO', code: '', montant: incomeStatement.sig?.XF_resultat_hao || 0 },
          { rubrique: 'XG - RÉSULTAT NET', code: '', montant: incomeStatement.sig?.XG_resultat_net || incomeStatement.net_income || 0 }
        ];
      
      case 'balance':
        return [
          { rubrique: 'ACTIF', code: '', montant: '' },
          { rubrique: 'ACTIF IMMOBILISÉ', code: 'Classe 2', montant: '' },
          { rubrique: 'AE - Immobilisations incorporelles', code: '21', montant: balanceSheet.actif?.actif_immobilise?.AE_immobilisations_incorporelles || 0 },
          { rubrique: 'AI - Immobilisations corporelles', code: '22-24', montant: balanceSheet.actif?.actif_immobilise?.AI_immobilisations_corporelles || 0 },
          { rubrique: 'AQ - Immobilisations financières', code: '26-27', montant: balanceSheet.actif?.actif_immobilise?.AQ_immobilisations_financieres || 0 },
          { rubrique: 'AZ - Total Actif Immobilisé', code: '', montant: balanceSheet.actif?.actif_immobilise?.AZ_total_actif_immobilise || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'ACTIF CIRCULANT', code: 'Classe 3-4', montant: '' },
          { rubrique: 'BJ - Stocks', code: '31-38', montant: balanceSheet.actif?.actif_circulant?.BJ_stocks || balanceSheet.assets?.current?.inventory || 0 },
          { rubrique: 'BK - Créances clients', code: '411', montant: balanceSheet.actif?.actif_circulant?.BK_creances_clients || balanceSheet.assets?.current?.accounts_receivable || 0 },
          { rubrique: 'BL - Autres créances', code: '42-48', montant: balanceSheet.actif?.actif_circulant?.BL_autres_creances || 0 },
          { rubrique: 'BZ - Total Actif Circulant', code: '', montant: balanceSheet.actif?.actif_circulant?.BZ_total_actif_circulant || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'TRÉSORERIE ACTIF', code: 'Classe 5', montant: '' },
          { rubrique: 'BQ - Banque', code: '52', montant: balanceSheet.actif?.tresorerie_actif?.BQ_banque || 0 },
          { rubrique: 'BR - Caisse', code: '57', montant: balanceSheet.actif?.tresorerie_actif?.BR_caisse || balanceSheet.assets?.current?.cash || 0 },
          { rubrique: 'BT - Total Trésorerie', code: '', montant: balanceSheet.actif?.tresorerie_actif?.BT_total_tresorerie_actif || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'TOTAL ACTIF', code: '', montant: balanceSheet.actif?.BZ_total_actif || balanceSheet.assets?.total || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'PASSIF', code: '', montant: '' },
          { rubrique: 'CAPITAUX PROPRES', code: 'Classe 1', montant: '' },
          { rubrique: 'CA - Capital social', code: '10', montant: balanceSheet.passif?.capitaux_propres?.CA_capital_social || 0 },
          { rubrique: 'CB - Réserves', code: '11', montant: balanceSheet.passif?.capitaux_propres?.CB_reserves || 0 },
          { rubrique: 'CD - Report à nouveau', code: '12', montant: balanceSheet.passif?.capitaux_propres?.CD_report_nouveau || 0 },
          { rubrique: 'CF - Résultat net', code: '13', montant: balanceSheet.passif?.capitaux_propres?.CF_resultat_net || balanceSheet.equity?.retained_earnings || 0 },
          { rubrique: 'CP - Total Capitaux Propres', code: '', montant: balanceSheet.passif?.capitaux_propres?.CP_total_capitaux_propres || balanceSheet.equity?.total || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'PASSIF CIRCULANT', code: 'Classe 4', montant: '' },
          { rubrique: 'DI - Fournisseurs', code: '401', montant: balanceSheet.passif?.passif_circulant?.DI_fournisseurs || balanceSheet.liabilities?.current?.accounts_payable || 0 },
          { rubrique: 'DJ - Dettes fiscales', code: '44', montant: balanceSheet.passif?.passif_circulant?.DJ_dettes_fiscales || balanceSheet.liabilities?.current?.tax_payable || 0 },
          { rubrique: 'DK - Dettes sociales', code: '43', montant: balanceSheet.passif?.passif_circulant?.DK_dettes_sociales || 0 },
          { rubrique: 'DL - Autres dettes', code: '45-48', montant: balanceSheet.passif?.passif_circulant?.DL_autres_dettes || 0 },
          { rubrique: 'DZ - Total Passif Circulant', code: '', montant: balanceSheet.passif?.passif_circulant?.DZ_total_passif_circulant || balanceSheet.liabilities?.total || 0 },
          { rubrique: '', code: '', montant: '' },
          { rubrique: 'TOTAL PASSIF', code: '', montant: balanceSheet.passif?.DZ_total_passif || balanceSheet.total_liabilities_and_equity || 0 }
        ];
      
      case 'sig':
        return [
          { solde: 'Chiffre d\'affaires', montant: sig.chiffre_affaires || 0, taux: '' },
          { solde: 'Coût d\'achat des marchandises', montant: sig.cout_achat_marchandises || 0, taux: '' },
          { solde: 'Marge commerciale', montant: sig.marge_commerciale || 0, taux: `${sig.taux_marge || 0}%` },
          { solde: 'Charges externes', montant: sig.charges_externes || 0, taux: '' },
          { solde: 'Valeur ajoutée', montant: sig.valeur_ajoutee || 0, taux: '' },
          { solde: 'Charges de personnel', montant: sig.charges_personnel || 0, taux: '' },
          { solde: 'Excédent Brut d\'Exploitation (EBE)', montant: sig.ebe || 0, taux: '' },
          { solde: 'Autres charges', montant: sig.autres_charges || 0, taux: '' },
          { solde: 'Résultat d\'exploitation', montant: sig.resultat_exploitation || 0, taux: '' },
          { solde: 'Résultat net', montant: sig.resultat_net || 0, taux: '' }
        ];
      
      case 'trial':
        return (trialBalance.accounts || []).map(acc => ({
          code: acc.account_code,
          compte: acc.account_name,
          type: acc.account_type,
          debit: acc.debit || 0,
          credit: acc.credit || 0,
          solde: acc.balance || 0
        }));
      
      case 'journal':
        return (journal.entries || []).flatMap(entry => 
          (entry.lines || []).map(line => ({
            date: entry.date,
            journal: entry.journal,
            reference: entry.reference,
            compte: line.account,
            libelle: line.label,
            debit: line.debit || 0,
            credit: line.credit || 0
          }))
        );
      
      case 'cash':
        return [
          { rubrique: 'Solde d\'ouverture', montant: cashReport.opening_balance || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'ENCAISSEMENTS', montant: '' },
          ...(cashReport.receipts || []).map(r => ({ rubrique: `${r.method}`, montant: r.total || 0 })),
          { rubrique: 'Total Encaissements', montant: cashReport.total_receipts || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'DÉCAISSEMENTS', montant: '' },
          ...(cashReport.disbursements || []).map(d => ({ rubrique: `${d.category}`, montant: d.total || 0 })),
          { rubrique: 'Total Décaissements', montant: cashReport.total_disbursements || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'Mouvement net', montant: cashReport.net_movement || 0 },
          { rubrique: 'Solde de clôture', montant: cashReport.closing_balance || 0 }
        ];
      
      case 'financial':
        return [
          { rubrique: 'COMPTE DE RÉSULTAT', montant: '' },
          { rubrique: 'Chiffre d\'affaires', montant: financialStatements.income_statement?.revenue || 0 },
          { rubrique: 'Coût des ventes', montant: financialStatements.income_statement?.cost_of_goods_sold || 0 },
          { rubrique: 'Marge brute', montant: financialStatements.income_statement?.gross_profit || 0 },
          { rubrique: 'Charges d\'exploitation', montant: financialStatements.income_statement?.operating_expenses || 0 },
          { rubrique: 'Résultat d\'exploitation', montant: financialStatements.income_statement?.operating_profit || 0 },
          { rubrique: 'Résultat net', montant: financialStatements.income_statement?.net_profit || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'RATIOS', montant: '' },
          { rubrique: 'Marge brute', montant: `${financialStatements.ratios?.gross_margin || 0}%` },
          { rubrique: 'Marge nette', montant: `${financialStatements.ratios?.net_margin || 0}%` },
          { rubrique: 'Ratio des charges', montant: `${financialStatements.ratios?.expense_ratio || 0}%` }
        ];
      
      case 'caf':
        return [
          { rubrique: 'Résultat net', montant: caf.resultat_net || 0 },
          { rubrique: 'Dotations aux amortissements', montant: caf.dotations_amortissements || 0 },
          { rubrique: 'Reprises', montant: caf.reprises || 0 },
          { rubrique: 'Plus-values de cessions', montant: caf.plus_values_cessions || 0 },
          { rubrique: 'Moins-values de cessions', montant: caf.moins_values_cessions || 0 },
          { rubrique: 'CAPACITÉ D\'AUTOFINANCEMENT', montant: caf.capacite_autofinancement || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'Dividendes', montant: caf.dividendes || 0 },
          { rubrique: 'AUTOFINANCEMENT', montant: caf.autofinancement || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'FLUX DE TRÉSORERIE', montant: '' },
          { rubrique: 'Encaissements d\'exploitation', montant: caf.flux_tresorerie?.encaissements_exploitation || 0 },
          { rubrique: 'Décaissements d\'exploitation', montant: caf.flux_tresorerie?.decaissements_exploitation || 0 },
          { rubrique: 'Flux d\'exploitation', montant: caf.flux_tresorerie?.flux_exploitation || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'BFR', montant: '' },
          { rubrique: 'Créances clients', montant: caf.bfr?.creances_clients || 0 },
          { rubrique: 'Dettes fournisseurs', montant: caf.bfr?.dettes_fournisseurs || 0 },
          { rubrique: 'Besoin en fonds de roulement', montant: caf.bfr?.besoin_fonds_roulement || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'TRÉSORERIE NETTE', montant: caf.tresorerie_nette || 0 }
        ];
      
      case 'cashflow':
        return [
          { rubrique: 'FLUX D\'EXPLOITATION', montant: '' },
          { rubrique: 'Encaissements clients', montant: cashFlow.exploitation?.encaissements_clients || 0 },
          { rubrique: 'Paiements fournisseurs', montant: cashFlow.exploitation?.paiements_fournisseurs || 0 },
          { rubrique: 'Paiements charges', montant: cashFlow.exploitation?.paiements_charges || 0 },
          { rubrique: 'Total flux d\'exploitation', montant: cashFlow.exploitation?.total || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'FLUX D\'INVESTISSEMENT', montant: '' },
          { rubrique: 'Acquisitions', montant: cashFlow.investissement?.acquisitions || 0 },
          { rubrique: 'Cessions', montant: cashFlow.investissement?.cessions || 0 },
          { rubrique: 'Total flux d\'investissement', montant: cashFlow.investissement?.total || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'FLUX DE FINANCEMENT', montant: '' },
          { rubrique: 'Emprunts', montant: cashFlow.financement?.emprunts || 0 },
          { rubrique: 'Remboursements', montant: cashFlow.financement?.remboursements || 0 },
          { rubrique: 'Total flux de financement', montant: cashFlow.financement?.total || 0 },
          { rubrique: '', montant: '' },
          { rubrique: 'VARIATION DE TRÉSORERIE', montant: cashFlow.variation_tresorerie || 0 },
          { rubrique: 'Trésorerie d\'ouverture', montant: cashFlow.tresorerie_ouverture || 0 },
          { rubrique: 'Trésorerie de clôture', montant: cashFlow.tresorerie_cloture || 0 }
        ];
      
      case 'ratios':
        return [
          { categorie: 'RENTABILITÉ', ratio: '', valeur: '' },
          { categorie: '', ratio: 'Marge brute', valeur: `${ratios.rentabilite?.marge_brute || 0}%` },
          { categorie: '', ratio: 'Marge nette', valeur: `${ratios.rentabilite?.marge_nette || 0}%` },
          { categorie: '', ratio: 'ROA (Rentabilité des actifs)', valeur: `${ratios.rentabilite?.roa || 0}%` },
          { categorie: '', ratio: '', valeur: '' },
          { categorie: 'LIQUIDITÉ', ratio: '', valeur: '' },
          { categorie: '', ratio: 'Ratio de liquidité générale', valeur: ratios.liquidite?.ratio_liquidite_generale || 0 },
          { categorie: '', ratio: 'Ratio de liquidité immédiate', valeur: ratios.liquidite?.ratio_liquidite_immediate || 0 },
          { categorie: '', ratio: '', valeur: '' },
          { categorie: 'ACTIVITÉ', ratio: '', valeur: '' },
          { categorie: '', ratio: 'Rotation des stocks', valeur: ratios.activite?.rotation_stocks || 0 },
          { categorie: '', ratio: 'Rotation des créances', valeur: ratios.activite?.rotation_creances || 0 },
          { categorie: '', ratio: 'Délai rotation stocks (jours)', valeur: ratios.activite?.delai_rotation_stocks || 0 },
          { categorie: '', ratio: 'Délai recouvrement (jours)', valeur: ratios.activite?.delai_recouvrement || 0 },
          { categorie: '', ratio: '', valeur: '' },
          { categorie: 'DONNÉES DE BASE', ratio: '', valeur: '' },
          { categorie: '', ratio: 'Chiffre d\'affaires', valeur: ratios.donnees_base?.chiffre_affaires || 0 },
          { categorie: '', ratio: 'Résultat net', valeur: ratios.donnees_base?.resultat_net || 0 },
          { categorie: '', ratio: 'Total actif', valeur: ratios.donnees_base?.total_actif || 0 },
          { categorie: '', ratio: 'Trésorerie', valeur: ratios.donnees_base?.tresorerie || 0 },
          { categorie: '', ratio: 'Stocks', valeur: ratios.donnees_base?.stocks || 0 },
          { categorie: '', ratio: 'Créances', valeur: ratios.donnees_base?.creances || 0 },
          { categorie: '', ratio: 'Dettes fournisseurs', valeur: ratios.donnees_base?.dettes_fournisseurs || 0 }
        ];
      
      default:
        return [];
    }
  };

  const getExportColumns = () => {
    switch (activeTab) {
      case 'summary':
        return [
          { key: 'indicateur', header: 'Indicateur' },
          { key: 'valeur', header: 'Valeur', type: 'currency' }
        ];
      
      case 'income':
      case 'balance':
        return [
          { key: 'rubrique', header: 'Rubrique' },
          { key: 'code', header: 'Code Compte' },
          { key: 'montant', header: 'Montant', type: 'currency' }
        ];
      
      case 'sig':
        return [
          { key: 'solde', header: 'Solde Intermédiaire' },
          { key: 'montant', header: 'Montant', type: 'currency' },
          { key: 'taux', header: 'Taux' }
        ];
      
      case 'trial':
        return [
          { key: 'code', header: 'Code' },
          { key: 'compte', header: 'Compte' },
          { key: 'type', header: 'Type' },
          { key: 'debit', header: 'Débit', type: 'currency' },
          { key: 'credit', header: 'Crédit', type: 'currency' },
          { key: 'solde', header: 'Solde', type: 'currency' }
        ];
      
      case 'journal':
        return [
          { key: 'date', header: 'Date' },
          { key: 'journal', header: 'Journal' },
          { key: 'reference', header: 'Référence' },
          { key: 'compte', header: 'Compte' },
          { key: 'libelle', header: 'Libellé' },
          { key: 'debit', header: 'Débit', type: 'currency' },
          { key: 'credit', header: 'Crédit', type: 'currency' }
        ];
      
      case 'cash':
      case 'financial':
      case 'caf':
      case 'cashflow':
        return [
          { key: 'rubrique', header: 'Rubrique' },
          { key: 'montant', header: 'Montant', type: 'currency' }
        ];
      
      case 'ratios':
        return [
          { key: 'categorie', header: 'Catégorie' },
          { key: 'ratio', header: 'Ratio' },
          { key: 'valeur', header: 'Valeur' }
        ];
      
      default:
        return [];
    }
  };

  const getExportTitle = () => {
    const tabInfo = tabs.find(t => t.id === activeTab);
    return tabInfo ? tabInfo.label : 'Comptabilité';
  };

  return (
    <div className="p-4 sm:p-6 space-y-6 min-h-screen">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <Calculator className="text-brand-600" size={28} />
            Comptabilité
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">États financiers et rapports comptables</p>
        </div>
        <div className="flex items-center gap-3">
          <button 
            onClick={fetchData} 
            disabled={loading}
            className="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            <RefreshCw size={18} className={`text-gray-600 dark:text-gray-300 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <ExportButton
            data={getExportData()}
            columns={getExportColumns()}
            filename={`comptabilite-${activeTab}-${dateRange.start}`}
            title={getExportTitle()}
            subtitle={`Du ${dateRange.start} au ${dateRange.end}`}
            variant="primary"
          />
        </div>
      </div>

      {/* Message d'erreur */}
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl">
          <strong>Erreur:</strong> {error}
        </div>
      )}

      {/* Filtres de période */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-wrap items-center gap-4">
        <div className="flex items-center gap-2">
          <Calendar size={16} className="text-gray-400" />
          <label className="text-sm font-medium text-gray-600 dark:text-gray-400">Du:</label>
          <input
            type="date"
            value={dateRange.start}
            onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value }))}
            className="px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          />
        </div>
        <div className="flex items-center gap-2">
          <label className="text-sm font-medium text-gray-600 dark:text-gray-400">Au:</label>
          <input
            type="date"
            value={dateRange.end}
            onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value }))}
            className="px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          />
        </div>
      </div>

      {/* Summary Cards - Design moderne */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
        {loading ? [1,2,3,4,5,6].map(i => <CardSkeleton key={i} />) : (
          <>
            <StatCard
              title="Ventes"
              value={formatCurrency(summary.total_sales)}
              icon={TrendingUp}
              color="bg-gradient-to-br from-green-500 to-green-600 text-white border-green-400"
              subValue={`${formatPercent(summary.profit_margin)} marge`}
            />
            <StatCard
              title="Coût Ventes"
              value={formatCurrency(summary.cost_of_goods_sold)}
              icon={TrendingDown}
              color="bg-gradient-to-br from-orange-500 to-orange-600 text-white border-orange-400"
            />
            <StatCard
              title="Marge Brute"
              value={formatCurrency(summary.gross_profit)}
              icon={DollarSign}
              color="bg-gradient-to-br from-blue-500 to-blue-600 text-white border-blue-400"
            />
            <StatCard
              title="Dépenses"
              value={formatCurrency(summary.total_expenses)}
              icon={Wallet}
              color="bg-gradient-to-br from-red-500 to-red-600 text-white border-red-400"
            />
            <StatCard
              title="Résultat Net"
              value={formatCurrency(summary.net_income)}
              icon={Activity}
              color={summary.net_income >= 0 
                ? "bg-gradient-to-br from-emerald-500 to-emerald-600 text-white border-emerald-400"
                : "bg-gradient-to-br from-red-500 to-red-600 text-white border-red-400"
              }
            />
            <StatCard
              title="Valeur Stock"
              value={formatCurrency(summary.stock_value)}
              icon={BarChart3}
              color="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white border-indigo-400"
            />
          </>
        )}
      </div>

      {/* Tabs */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div className="border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
          <div className="flex">
            {tabs.map(tab => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-3 sm:px-4 py-3 font-medium text-xs sm:text-sm border-b-2 transition flex items-center gap-1.5 sm:gap-2 whitespace-nowrap ${
                  activeTab === tab.id
                    ? 'border-brand-600 text-brand-600 bg-brand-50 dark:bg-brand-900/20'
                    : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700'
                }`}
              >
                <tab.icon size={16} />
                <span className="hidden sm:inline">{tab.label}</span>
              </button>
            ))}
          </div>
        </div>

        <div className="p-6">
          {/* RÉSUMÉ */}
          {activeTab === 'summary' && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="space-y-4">
                <h3 className="text-lg font-bold text-gray-900">Indicateurs Clés</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-gray-600">Chiffre d'affaires</span>
                    <span className="font-bold">{formatCurrency(summary.total_sales)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-gray-600">Coût des ventes</span>
                    <span className="font-bold text-orange-600">{formatCurrency(summary.cost_of_goods_sold)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                    <span className="text-gray-700 font-medium">Marge brute</span>
                    <span className="font-bold text-blue-600">{formatCurrency(summary.gross_profit)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-gray-600">Charges d'exploitation</span>
                    <span className="font-bold text-red-600">{formatCurrency(summary.total_expenses)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-green-100 rounded-lg border-2 border-green-300">
                    <span className="text-gray-900 font-bold">Résultat Net</span>
                    <span className={`font-bold text-xl ${summary.net_income >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {formatCurrency(summary.net_income)}
                    </span>
                  </div>
                </div>
              </div>
              <div className="space-y-4">
                <h3 className="text-lg font-bold text-gray-900">Ratios</h3>
                <div className="space-y-3">
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-600">Taux de marge brute</span>
                      <span className="font-bold">{formatPercent(summary.profit_margin)}</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${Math.min(summary.profit_margin || 0, 100)}%` }}></div>
                    </div>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-600">Taux de marge nette</span>
                      <span className="font-bold">{formatPercent(summary.total_sales > 0 ? (summary.net_income / summary.total_sales) * 100 : 0)}</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div className={`h-2 rounded-full ${summary.net_income >= 0 ? 'bg-green-600' : 'bg-red-600'}`} 
                           style={{ width: `${Math.min(Math.abs((summary.net_income / (summary.total_sales || 1)) * 100), 100)}%` }}></div>
                    </div>
                  </div>
                  <div className="p-4 bg-purple-50 rounded-lg">
                    <p className="text-gray-600 text-sm">TVA Collectée</p>
                    <p className="text-2xl font-bold text-purple-600">{formatCurrency(summary.total_tax)}</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* COMPTE DE RÉSULTAT SYSCOHADA */}
          {activeTab === 'income' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">Compte de Résultat</h3>
                <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-medium rounded">SYSCOHADA</span>
              </div>
              {loading ? <TableSkeleton /> : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  {/* Colonne Produits & Charges */}
                  <div className="space-y-4">
                    <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                      <h4 className="font-semibold text-green-800 dark:text-green-300 mb-3">PRODUITS D'EXPLOITATION</h4>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-700 dark:text-gray-300">TA - Chiffre d'affaires (70)</span>
                        <span className="font-bold">{formatCurrency(incomeStatement.produits?.TA_chiffre_affaires || incomeStatement.revenue?.sales)}</span>
                      </div>
                      <div className="flex justify-between items-center mt-2 pt-2 border-t border-green-200 dark:border-green-700">
                        <span className="font-semibold">Total Produits</span>
                        <span className="font-bold text-green-700 dark:text-green-400">{formatCurrency(incomeStatement.produits?.total_produits_exploitation || incomeStatement.revenue?.total)}</span>
                      </div>
                    </div>

                    <div className="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                      <h4 className="font-semibold text-orange-800 dark:text-orange-300 mb-3">CHARGES D'EXPLOITATION</h4>
                      <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                          <span>RA - Achats de marchandises (60)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RA_achats_marchandises || incomeStatement.cost_of_goods_sold)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RB - Autres achats (61/62)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RB_autres_achats)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RC - Transports (61)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RC_transports)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RD - Services extérieurs (62/63)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RD_services_exterieurs)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RE - Impôts et taxes (64)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RE_impots_taxes)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RF - Autres charges (65)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RF_autres_charges)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>RG - Charges de personnel (66)</span>
                          <span className="font-medium">{formatCurrency(incomeStatement.charges?.RG_charges_personnel)}</span>
                        </div>
                      </div>
                      <div className="flex justify-between items-center mt-2 pt-2 border-t border-orange-200 dark:border-orange-700">
                        <span className="font-semibold">Total Charges</span>
                        <span className="font-bold text-orange-700 dark:text-orange-400">{formatCurrency(incomeStatement.charges?.total_charges_exploitation || ((incomeStatement.cost_of_goods_sold || 0) + (incomeStatement.total_expenses || 0)))}</span>
                      </div>
                    </div>
                  </div>

                  {/* Colonne SIG */}
                  <div className="space-y-4">
                    <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                      <h4 className="font-semibold text-blue-800 dark:text-blue-300 mb-3">SOLDES INTERMÉDIAIRES DE GESTION</h4>
                      <div className="space-y-3">
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XA - Marge brute</span>
                          <span className="font-bold text-blue-600">{formatCurrency(incomeStatement.sig?.XA_marge_brute || incomeStatement.gross_profit)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XB - Valeur ajoutée</span>
                          <span className="font-bold text-purple-600">{formatCurrency(incomeStatement.sig?.XB_valeur_ajoutee)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XC - Excédent Brut d'Exploitation (EBE)</span>
                          <span className="font-bold text-indigo-600">{formatCurrency(incomeStatement.sig?.XC_ebe)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XD - Résultat d'exploitation</span>
                          <span className="font-bold">{formatCurrency(incomeStatement.sig?.XD_resultat_exploitation || incomeStatement.operating_income)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XI - Résultat financier</span>
                          <span className="font-bold">{formatCurrency(incomeStatement.sig?.XI_resultat_financier || 0)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XE - Résultat des Activités Ordinaires</span>
                          <span className="font-bold">{formatCurrency(incomeStatement.sig?.XE_rao)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded">
                          <span className="text-sm">XF - Résultat HAO</span>
                          <span className="font-bold">{formatCurrency(incomeStatement.sig?.XF_resultat_hao || 0)}</span>
                        </div>
                      </div>
                    </div>

                    <div className={`p-4 rounded-lg border-2 ${(incomeStatement.sig?.XG_resultat_net || incomeStatement.net_income) >= 0 ? 'bg-green-100 dark:bg-green-900/30 border-green-400' : 'bg-red-100 dark:bg-red-900/30 border-red-400'}`}>
                      <div className="flex justify-between items-center">
                        <span className="font-bold text-lg">XG - RÉSULTAT NET</span>
                        <span className={`font-bold text-2xl ${(incomeStatement.sig?.XG_resultat_net || incomeStatement.net_income) >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'}`}>
                          {formatCurrency(incomeStatement.sig?.XG_resultat_net || incomeStatement.net_income)}
                        </span>
                      </div>
                      <div className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        Taux de marge nette: {formatPercent(incomeStatement.ratios?.taux_resultat_net || incomeStatement.net_margin)}
                      </div>
                    </div>

                    {/* Ratios */}
                    <div className="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                      <h4 className="font-semibold text-gray-700 dark:text-gray-300 mb-3">Ratios de performance</h4>
                      <div className="grid grid-cols-2 gap-3 text-sm">
                        <div className="text-center p-2 bg-white dark:bg-gray-700 rounded">
                          <p className="text-gray-500 dark:text-gray-400">Marge brute</p>
                          <p className="font-bold text-blue-600">{formatPercent(incomeStatement.ratios?.taux_marge_brute || incomeStatement.gross_margin)}</p>
                        </div>
                        <div className="text-center p-2 bg-white dark:bg-gray-700 rounded">
                          <p className="text-gray-500 dark:text-gray-400">Valeur ajoutée</p>
                          <p className="font-bold text-purple-600">{formatPercent(incomeStatement.ratios?.taux_valeur_ajoutee)}</p>
                        </div>
                        <div className="text-center p-2 bg-white dark:bg-gray-700 rounded">
                          <p className="text-gray-500 dark:text-gray-400">EBE</p>
                          <p className="font-bold text-indigo-600">{formatPercent(incomeStatement.ratios?.taux_ebe)}</p>
                        </div>
                        <div className="text-center p-2 bg-white dark:bg-gray-700 rounded">
                          <p className="text-gray-500 dark:text-gray-400">Résultat net</p>
                          <p className={`font-bold ${(incomeStatement.ratios?.taux_resultat_net || incomeStatement.net_margin) >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {formatPercent(incomeStatement.ratios?.taux_resultat_net || incomeStatement.net_margin)}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* BILAN SYSCOHADA */}
          {activeTab === 'balance' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">Bilan au {balanceSheet.as_of_date}</h3>
                <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-medium rounded">SYSCOHADA</span>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* ACTIF */}
                <div className="space-y-4">
                  <h3 className="text-lg font-bold text-blue-800 dark:text-blue-400 border-b-2 border-blue-500 pb-2">ACTIF</h3>
                  {loading ? <TableSkeleton /> : (
                    <div className="space-y-3">
                      {/* Actif Immobilisé */}
                      <div className="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg border border-slate-200 dark:border-slate-700">
                        <h4 className="font-semibold text-slate-700 dark:text-slate-300 mb-3">ACTIF IMMOBILISÉ (Classe 2)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>AE - Immobilisations incorporelles (21)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_immobilise?.AE_immobilisations_incorporelles)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>AI - Immobilisations corporelles (22-24)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_immobilise?.AI_immobilisations_corporelles)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>AQ - Immobilisations financières (26-27)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_immobilise?.AQ_immobilisations_financieres)}</span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-slate-200 dark:border-slate-600">
                          <span className="font-semibold">AZ - Total Actif Immobilisé</span>
                          <span className="font-bold">{formatCurrency(balanceSheet.actif?.actif_immobilise?.AZ_total_actif_immobilise)}</span>
                        </div>
                      </div>

                      {/* Actif Circulant */}
                      <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                        <h4 className="font-semibold text-blue-800 dark:text-blue-300 mb-3">ACTIF CIRCULANT (Classe 3-4)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>BJ - Stocks (31-38)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_circulant?.BJ_stocks || balanceSheet.assets?.current?.inventory)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>BK - Créances clients (411)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_circulant?.BK_creances_clients || balanceSheet.assets?.current?.accounts_receivable)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>BL - Autres créances (42-48)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.actif_circulant?.BL_autres_creances)}</span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-blue-200 dark:border-blue-700">
                          <span className="font-semibold">BZ - Total Actif Circulant</span>
                          <span className="font-bold text-blue-700 dark:text-blue-400">{formatCurrency(balanceSheet.actif?.actif_circulant?.BZ_total_actif_circulant)}</span>
                        </div>
                      </div>

                      {/* Trésorerie Actif */}
                      <div className="bg-cyan-50 dark:bg-cyan-900/20 p-4 rounded-lg border border-cyan-200 dark:border-cyan-800">
                        <h4 className="font-semibold text-cyan-800 dark:text-cyan-300 mb-3">TRÉSORERIE ACTIF (Classe 5)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>BQ - Banque (52)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.tresorerie_actif?.BQ_banque)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>BR - Caisse (57)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.actif?.tresorerie_actif?.BR_caisse || balanceSheet.assets?.current?.cash)}</span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-cyan-200 dark:border-cyan-700">
                          <span className="font-semibold">BT - Total Trésorerie</span>
                          <span className="font-bold text-cyan-700 dark:text-cyan-400">{formatCurrency(balanceSheet.actif?.tresorerie_actif?.BT_total_tresorerie_actif)}</span>
                        </div>
                      </div>

                      {/* Total Actif */}
                      <div className="bg-blue-600 text-white p-4 rounded-lg">
                        <div className="flex justify-between items-center">
                          <span className="font-bold text-lg">TOTAL ACTIF</span>
                          <span className="font-bold text-2xl">{formatCurrency(balanceSheet.actif?.BZ_total_actif || balanceSheet.assets?.total)}</span>
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* PASSIF */}
                <div className="space-y-4">
                  <h3 className="text-lg font-bold text-orange-800 dark:text-orange-400 border-b-2 border-orange-500 pb-2">PASSIF</h3>
                  {loading ? <TableSkeleton /> : (
                    <div className="space-y-3">
                      {/* Capitaux Propres */}
                      <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                        <h4 className="font-semibold text-green-800 dark:text-green-300 mb-3">CAPITAUX PROPRES (Classe 1)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>CA - Capital social (10)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.capitaux_propres?.CA_capital_social)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>CB - Réserves (11)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.capitaux_propres?.CB_reserves)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>CD - Report à nouveau (12)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.capitaux_propres?.CD_report_nouveau)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>CF - Résultat net (13)</span>
                            <span className={`font-medium ${(balanceSheet.passif?.capitaux_propres?.CF_resultat_net || balanceSheet.equity?.retained_earnings) >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                              {formatCurrency(balanceSheet.passif?.capitaux_propres?.CF_resultat_net || balanceSheet.equity?.retained_earnings)}
                            </span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-green-200 dark:border-green-700">
                          <span className="font-semibold">CP - Total Capitaux Propres</span>
                          <span className="font-bold text-green-700 dark:text-green-400">{formatCurrency(balanceSheet.passif?.capitaux_propres?.CP_total_capitaux_propres || balanceSheet.equity?.total)}</span>
                        </div>
                      </div>

                      {/* Dettes Financières */}
                      <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                        <h4 className="font-semibold text-purple-800 dark:text-purple-300 mb-3">DETTES FINANCIÈRES (Classe 1)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>DA - Emprunts (16-17)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.dettes_financieres?.DA_emprunts)}</span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-purple-200 dark:border-purple-700">
                          <span className="font-semibold">DF - Total Dettes Financières</span>
                          <span className="font-bold text-purple-700 dark:text-purple-400">{formatCurrency(balanceSheet.passif?.dettes_financieres?.DF_total_dettes_financieres)}</span>
                        </div>
                      </div>

                      {/* Passif Circulant */}
                      <div className="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                        <h4 className="font-semibold text-orange-800 dark:text-orange-300 mb-3">PASSIF CIRCULANT (Classe 4)</h4>
                        <div className="space-y-2 text-sm">
                          <div className="flex justify-between">
                            <span>DI - Fournisseurs (401)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.passif_circulant?.DI_fournisseurs || balanceSheet.liabilities?.current?.accounts_payable)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>DJ - Dettes fiscales (44)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.passif_circulant?.DJ_dettes_fiscales || balanceSheet.liabilities?.current?.tax_payable)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>DK - Dettes sociales (43)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.passif_circulant?.DK_dettes_sociales)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>DL - Autres dettes (45-48)</span>
                            <span className="font-medium">{formatCurrency(balanceSheet.passif?.passif_circulant?.DL_autres_dettes)}</span>
                          </div>
                        </div>
                        <div className="flex justify-between mt-2 pt-2 border-t border-orange-200 dark:border-orange-700">
                          <span className="font-semibold">DZ - Total Passif Circulant</span>
                          <span className="font-bold text-orange-700 dark:text-orange-400">{formatCurrency(balanceSheet.passif?.passif_circulant?.DZ_total_passif_circulant || balanceSheet.liabilities?.total)}</span>
                        </div>
                      </div>

                      {/* Total Passif */}
                      <div className="bg-orange-600 text-white p-4 rounded-lg">
                        <div className="flex justify-between items-center">
                          <span className="font-bold text-lg">TOTAL PASSIF</span>
                          <span className="font-bold text-2xl">{formatCurrency(balanceSheet.passif?.DZ_total_passif || balanceSheet.total_liabilities_and_equity)}</span>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {/* Équilibre du bilan */}
              <div className={`p-4 rounded-lg border-2 ${balanceSheet.is_balanced || balanceSheet.equilibre?.is_balanced ? 'bg-green-50 dark:bg-green-900/20 border-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-400'}`}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <span className={`text-2xl ${balanceSheet.is_balanced || balanceSheet.equilibre?.is_balanced ? 'text-green-600' : 'text-red-600'}`}>
                      {balanceSheet.is_balanced || balanceSheet.equilibre?.is_balanced ? '✓' : '⚠'}
                    </span>
                    <div>
                      <p className="font-bold text-gray-900 dark:text-white">
                        {balanceSheet.is_balanced || balanceSheet.equilibre?.is_balanced ? 'Bilan équilibré' : 'Bilan non équilibré'}
                      </p>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        Écart: {formatCurrency(balanceSheet.equilibre?.ecart || Math.abs((balanceSheet.actif?.BZ_total_actif || balanceSheet.assets?.total || 0) - (balanceSheet.passif?.DZ_total_passif || balanceSheet.total_liabilities_and_equity || 0)))}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-sm text-gray-500 dark:text-gray-400">Actif = Passif</p>
                    <p className="font-bold">{formatCurrency(balanceSheet.actif?.BZ_total_actif || balanceSheet.assets?.total)} = {formatCurrency(balanceSheet.passif?.DZ_total_passif || balanceSheet.total_liabilities_and_equity)}</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* SIG - Soldes Intermédiaires de Gestion */}
          {activeTab === 'sig' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900">Soldes Intermédiaires de Gestion</h3>
              {loading ? <TableSkeleton /> : (
                <div className="space-y-3">
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span>Chiffre d'affaires</span>
                    <span className="font-bold">{formatCurrency(sig.chiffre_affaires)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span>- Coût d'achat des marchandises vendues</span>
                    <span className="font-bold text-red-600">({formatCurrency(sig.cout_achat_marchandises)})</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-blue-100 rounded-lg">
                    <span className="font-semibold">= Marge commerciale</span>
                    <span className="font-bold text-blue-700">{formatCurrency(sig.marge_commerciale)} ({formatPercent(sig.taux_marge)})</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span>- Charges externes</span>
                    <span className="font-bold text-red-600">({formatCurrency(sig.charges_externes)})</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-green-100 rounded-lg">
                    <span className="font-semibold">= Valeur ajoutée</span>
                    <span className="font-bold text-green-700">{formatCurrency(sig.valeur_ajoutee)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span>- Charges de personnel</span>
                    <span className="font-bold text-red-600">({formatCurrency(sig.charges_personnel)})</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-purple-100 rounded-lg">
                    <span className="font-semibold">= Excédent Brut d'Exploitation (EBE)</span>
                    <span className="font-bold text-purple-700">{formatCurrency(sig.ebe)}</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span>- Autres charges</span>
                    <span className="font-bold text-red-600">({formatCurrency(sig.autres_charges)})</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-indigo-100 rounded-lg">
                    <span className="font-semibold">= Résultat d'exploitation</span>
                    <span className="font-bold text-indigo-700">{formatCurrency(sig.resultat_exploitation)}</span>
                  </div>
                  <div className={`flex justify-between items-center p-4 rounded-lg ${sig.resultat_net >= 0 ? 'bg-green-200' : 'bg-red-200'}`}>
                    <span className="font-bold text-lg">RÉSULTAT NET</span>
                    <span className={`font-bold text-2xl ${sig.resultat_net >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                      {formatCurrency(sig.resultat_net)}
                    </span>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* BALANCE */}
          {activeTab === 'trial' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900">Balance Générale</h3>
              {loading ? <TableSkeleton /> : (
                <>
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead className="bg-gray-100">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600">Code</th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600">Compte</th>
                          <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600">Débit</th>
                          <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600">Crédit</th>
                          <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600">Solde</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {(trialBalance.accounts || []).map((acc, idx) => (
                          <tr key={idx} className="hover:bg-gray-50">
                            <td className="px-4 py-3 font-mono text-sm">{acc.account_code}</td>
                            <td className="px-4 py-3">{acc.account_name}</td>
                            <td className="px-4 py-3 text-right font-medium">{acc.debit > 0 ? formatCurrency(acc.debit) : '-'}</td>
                            <td className="px-4 py-3 text-right font-medium">{acc.credit > 0 ? formatCurrency(acc.credit) : '-'}</td>
                            <td className={`px-4 py-3 text-right font-bold ${acc.balance >= 0 ? 'text-blue-600' : 'text-red-600'}`}>
                              {formatCurrency(acc.balance)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                      <tfoot className="bg-gray-100 font-bold">
                        <tr>
                          <td colSpan="2" className="px-4 py-3">TOTAUX</td>
                          <td className="px-4 py-3 text-right">{formatCurrency(trialBalance.total_debit)}</td>
                          <td className="px-4 py-3 text-right">{formatCurrency(trialBalance.total_credit)}</td>
                          <td className="px-4 py-3 text-right">
                            {trialBalance.is_balanced ? '✓' : formatCurrency(trialBalance.total_debit - trialBalance.total_credit)}
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                  {trialBalance.accounts?.length === 0 && (
                    <p className="text-center text-gray-500 py-8">Aucune écriture pour cette période</p>
                  )}
                </>
              )}
            </div>
          )}

          {/* JOURNAL */}
          {activeTab === 'journal' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900">Journal Comptable</h3>
              {loading ? <TableSkeleton /> : (
                <>
                  <div className="space-y-4">
                    {(journal.entries || []).map((entry, idx) => (
                      <div key={idx} className="border rounded-lg overflow-hidden">
                        <div className="bg-gray-50 px-4 py-2 flex justify-between items-center">
                          <div className="flex items-center gap-3">
                            <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">{entry.journal}</span>
                            <span className="font-mono text-sm">{entry.reference}</span>
                            <span className="text-gray-600">{entry.description}</span>
                          </div>
                          <span className="text-sm text-gray-500">{entry.date}</span>
                        </div>
                        <table className="w-full text-sm">
                          <tbody>
                            {(entry.lines || []).map((line, lidx) => (
                              <tr key={lidx} className="border-t">
                                <td className="px-4 py-2 font-mono">{line.account}</td>
                                <td className="px-4 py-2">{line.label}</td>
                                <td className="px-4 py-2 text-right">{line.debit > 0 ? formatCurrency(line.debit) : ''}</td>
                                <td className="px-4 py-2 text-right">{line.credit > 0 ? formatCurrency(line.credit) : ''}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    ))}
                  </div>
                  {journal.entries?.length === 0 && (
                    <p className="text-center text-gray-500 py-8">Aucune écriture pour cette période</p>
                  )}
                  <p className="text-sm text-gray-500 text-right">{journal.total_entries || 0} écritures</p>
                </>
              )}
            </div>
          )}

          {/* CAISSE */}
          {activeTab === 'cash' && (
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-gray-900">Rapport de Caisse - {cashReport.date}</h3>
              {loading ? <TableSkeleton /> : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-4">
                    <div className="bg-gray-100 p-4 rounded-lg">
                      <p className="text-gray-600 text-sm">Solde d'ouverture</p>
                      <p className="text-2xl font-bold">{formatCurrency(cashReport.opening_balance)}</p>
                    </div>
                    <div className="bg-green-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-green-800 mb-3">Encaissements</h4>
                      {(cashReport.receipts || []).map((r, idx) => (
                        <div key={idx} className="flex justify-between items-center py-1">
                          <span className="capitalize">{r.method} ({r.count})</span>
                          <span className="font-medium text-green-700">{formatCurrency(r.total)}</span>
                        </div>
                      ))}
                      <div className="flex justify-between items-center mt-2 pt-2 border-t border-green-200">
                        <span className="font-semibold">Total</span>
                        <span className="font-bold text-green-700">{formatCurrency(cashReport.total_receipts)}</span>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-4">
                    <div className="bg-red-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-red-800 mb-3">Décaissements</h4>
                      {(cashReport.disbursements || []).map((d, idx) => (
                        <div key={idx} className="flex justify-between items-center py-1">
                          <span className="capitalize">{d.category} ({d.count})</span>
                          <span className="font-medium text-red-700">{formatCurrency(d.total)}</span>
                        </div>
                      ))}
                      <div className="flex justify-between items-center mt-2 pt-2 border-t border-red-200">
                        <span className="font-semibold">Total</span>
                        <span className="font-bold text-red-700">{formatCurrency(cashReport.total_disbursements)}</span>
                      </div>
                    </div>
                    <div className="bg-blue-100 p-4 rounded-lg">
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Mouvement net</span>
                        <span className={`font-bold ${cashReport.net_movement >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                          {formatCurrency(cashReport.net_movement)}
                        </span>
                      </div>
                    </div>
                    <div className="bg-indigo-100 p-4 rounded-lg">
                      <p className="text-gray-600 text-sm">Solde de clôture</p>
                      <p className="text-2xl font-bold text-indigo-700">{formatCurrency(cashReport.closing_balance)}</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ÉTATS FINANCIERS */}
          {activeTab === 'financial' && (
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-gray-900">États Financiers Complets</h3>
              {loading ? <TableSkeleton /> : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div className="space-y-4">
                    <div className="bg-green-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-green-800 mb-3">Compte de Résultat</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>Chiffre d'affaires</span><span className="font-bold">{formatCurrency(financialStatements.income_statement?.revenue)}</span></div>
                        <div className="flex justify-between"><span>Coût des ventes</span><span className="text-orange-600">({formatCurrency(financialStatements.income_statement?.cost_of_goods_sold)})</span></div>
                        <div className="flex justify-between border-t pt-2"><span className="font-medium">Marge brute</span><span className="font-bold text-blue-600">{formatCurrency(financialStatements.income_statement?.gross_profit)}</span></div>
                        <div className="flex justify-between"><span>Charges d'exploitation</span><span className="text-red-600">({formatCurrency(financialStatements.income_statement?.operating_expenses)})</span></div>
                        <div className="flex justify-between border-t pt-2 bg-green-100 -mx-4 px-4 py-2">
                          <span className="font-bold">Résultat Net</span>
                          <span className={`font-bold text-lg ${financialStatements.income_statement?.net_profit >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                            {formatCurrency(financialStatements.income_statement?.net_profit)}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div className="bg-blue-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-blue-800 mb-3">Résumé du Bilan</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>Encaissements</span><span className="font-medium">{formatCurrency(financialStatements.balance_summary?.cash_received)}</span></div>
                        <div className="flex justify-between"><span>Valeur du stock</span><span className="font-medium">{formatCurrency(financialStatements.balance_summary?.stock_value)}</span></div>
                        <div className="flex justify-between"><span>Achats</span><span className="font-medium">{formatCurrency(financialStatements.balance_summary?.purchases)}</span></div>
                        <div className="flex justify-between"><span>Dépenses payées</span><span className="font-medium">{formatCurrency(financialStatements.balance_summary?.expenses_paid)}</span></div>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-4">
                    <div className="bg-purple-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-purple-800 mb-3">TVA</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>TVA collectée</span><span className="font-bold">{formatCurrency(financialStatements.tax_summary?.tax_collected)}</span></div>
                        <div className="flex justify-between"><span>TVA à payer</span><span className="font-bold text-purple-700">{formatCurrency(financialStatements.tax_summary?.tax_payable)}</span></div>
                      </div>
                    </div>
                    <div className="bg-indigo-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-indigo-800 mb-3">Ratios Clés</h4>
                      <div className="space-y-3">
                        <div>
                          <div className="flex justify-between mb-1"><span>Marge brute</span><span className="font-bold">{formatPercent(financialStatements.ratios?.gross_margin)}</span></div>
                          <div className="w-full bg-gray-200 rounded-full h-2"><div className="bg-blue-600 h-2 rounded-full" style={{ width: `${Math.min(financialStatements.ratios?.gross_margin || 0, 100)}%` }}></div></div>
                        </div>
                        <div>
                          <div className="flex justify-between mb-1"><span>Marge nette</span><span className="font-bold">{formatPercent(financialStatements.ratios?.net_margin)}</span></div>
                          <div className="w-full bg-gray-200 rounded-full h-2"><div className={`h-2 rounded-full ${(financialStatements.ratios?.net_margin || 0) >= 0 ? 'bg-green-600' : 'bg-red-600'}`} style={{ width: `${Math.min(Math.abs(financialStatements.ratios?.net_margin || 0), 100)}%` }}></div></div>
                        </div>
                        <div className="flex justify-between"><span>Ratio de charges</span><span className="font-bold">{formatPercent(financialStatements.ratios?.expense_ratio)}</span></div>
                        <div className="flex justify-between"><span>Rotation des stocks</span><span className="font-bold">{financialStatements.ratios?.inventory_turnover || 0}x</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* CAF - Capacité d'Autofinancement */}
          {activeTab === 'caf' && (
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-gray-900">Capacité d'Autofinancement (CAF)</h3>
              {loading ? <TableSkeleton /> : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div className="space-y-4">
                    <div className="bg-gray-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-gray-800 mb-3">Calcul de la CAF</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>Résultat net</span><span className={`font-bold ${caf.resultat_net >= 0 ? 'text-green-600' : 'text-red-600'}`}>{formatCurrency(caf.resultat_net)}</span></div>
                        <div className="flex justify-between"><span>+ Dotations aux amortissements</span><span>{formatCurrency(caf.dotations_amortissements)}</span></div>
                        <div className="flex justify-between"><span>- Reprises</span><span>({formatCurrency(caf.reprises)})</span></div>
                        <div className="flex justify-between"><span>- Plus-values de cession</span><span>({formatCurrency(caf.plus_values_cessions)})</span></div>
                        <div className="flex justify-between"><span>+ Moins-values de cession</span><span>{formatCurrency(caf.moins_values_cessions)}</span></div>
                        <div className="flex justify-between border-t-2 pt-2 bg-blue-100 -mx-4 px-4 py-2">
                          <span className="font-bold">= CAF</span>
                          <span className="font-bold text-xl text-blue-700">{formatCurrency(caf.capacite_autofinancement)}</span>
                        </div>
                        <div className="flex justify-between"><span>- Dividendes distribués</span><span>({formatCurrency(caf.dividendes)})</span></div>
                        <div className="flex justify-between bg-green-100 -mx-4 px-4 py-2">
                          <span className="font-bold">= Autofinancement</span>
                          <span className="font-bold text-green-700">{formatCurrency(caf.autofinancement)}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-4">
                    <div className="bg-green-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-green-800 mb-3">Flux de Trésorerie</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>Encaissements exploitation</span><span className="text-green-600">{formatCurrency(caf.flux_tresorerie?.encaissements_exploitation)}</span></div>
                        <div className="flex justify-between"><span>Décaissements exploitation</span><span className="text-red-600">({formatCurrency(caf.flux_tresorerie?.decaissements_exploitation)})</span></div>
                        <div className="flex justify-between border-t pt-2"><span className="font-medium">Flux d'exploitation</span><span className="font-bold">{formatCurrency(caf.flux_tresorerie?.flux_exploitation)}</span></div>
                      </div>
                    </div>
                    <div className="bg-orange-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-orange-800 mb-3">Besoin en Fonds de Roulement</h4>
                      <div className="space-y-2">
                        <div className="flex justify-between"><span>Créances clients</span><span>{formatCurrency(caf.bfr?.creances_clients)}</span></div>
                        <div className="flex justify-between"><span>Dettes fournisseurs</span><span>({formatCurrency(caf.bfr?.dettes_fournisseurs)})</span></div>
                        <div className="flex justify-between border-t pt-2"><span className="font-medium">BFR</span><span className="font-bold">{formatCurrency(caf.bfr?.besoin_fonds_roulement)}</span></div>
                      </div>
                    </div>
                    <div className="bg-indigo-100 p-4 rounded-lg">
                      <div className="flex justify-between items-center">
                        <span className="font-bold">Trésorerie Nette</span>
                        <span className={`font-bold text-xl ${caf.tresorerie_nette >= 0 ? 'text-green-700' : 'text-red-700'}`}>{formatCurrency(caf.tresorerie_nette)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* FLUX DE TRÉSORERIE */}
          {activeTab === 'cashflow' && (
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-gray-900">Tableau des Flux de Trésorerie</h3>
              {loading ? <TableSkeleton /> : (
                <div className="space-y-4">
                  <div className="bg-green-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-green-800 mb-3">Flux d'Exploitation</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between"><span>Encaissements clients</span><span className="text-green-600">{formatCurrency(cashFlow.flux_exploitation?.encaissements_clients)}</span></div>
                      <div className="flex justify-between"><span>Paiements fournisseurs</span><span className="text-red-600">({formatCurrency(cashFlow.flux_exploitation?.paiements_fournisseurs)})</span></div>
                      <div className="flex justify-between"><span>Paiements charges</span><span className="text-red-600">({formatCurrency(cashFlow.flux_exploitation?.paiements_charges)})</span></div>
                      <div className="flex justify-between border-t pt-2 font-bold"><span>Total flux d'exploitation</span><span className={cashFlow.flux_exploitation?.total >= 0 ? 'text-green-700' : 'text-red-700'}>{formatCurrency(cashFlow.flux_exploitation?.total)}</span></div>
                    </div>
                  </div>
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-blue-800 mb-3">Flux d'Investissement</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between"><span>Acquisitions</span><span className="text-red-600">({formatCurrency(cashFlow.flux_investissement?.acquisitions)})</span></div>
                      <div className="flex justify-between"><span>Cessions</span><span className="text-green-600">{formatCurrency(cashFlow.flux_investissement?.cessions)}</span></div>
                      <div className="flex justify-between border-t pt-2 font-bold"><span>Total flux d'investissement</span><span>{formatCurrency(cashFlow.flux_investissement?.total)}</span></div>
                    </div>
                  </div>
                  <div className="bg-purple-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-purple-800 mb-3">Flux de Financement</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between"><span>Emprunts</span><span className="text-green-600">{formatCurrency(cashFlow.flux_financement?.emprunts)}</span></div>
                      <div className="flex justify-between"><span>Remboursements</span><span className="text-red-600">({formatCurrency(cashFlow.flux_financement?.remboursements)})</span></div>
                      <div className="flex justify-between border-t pt-2 font-bold"><span>Total flux de financement</span><span>{formatCurrency(cashFlow.flux_financement?.total)}</span></div>
                    </div>
                  </div>
                  <div className="bg-gray-100 p-4 rounded-lg">
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-bold">Variation de trésorerie</span>
                      <span className={`font-bold text-lg ${cashFlow.variation_tresorerie >= 0 ? 'text-green-700' : 'text-red-700'}`}>{formatCurrency(cashFlow.variation_tresorerie)}</span>
                    </div>
                    <div className="flex justify-between text-sm text-gray-600">
                      <span>Trésorerie d'ouverture: {formatCurrency(cashFlow.tresorerie_ouverture)}</span>
                      <span>Trésorerie de clôture: <span className="font-bold text-gray-900">{formatCurrency(cashFlow.tresorerie_cloture)}</span></span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* RATIOS FINANCIERS */}
          {activeTab === 'ratios' && (
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-gray-900">Ratios Financiers</h3>
              {loading ? <TableSkeleton /> : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  <div className="bg-green-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-green-800 mb-3">Rentabilité</h4>
                    <div className="space-y-3">
                      <div>
                        <div className="flex justify-between mb-1"><span className="text-sm">Marge brute</span><span className="font-bold">{formatPercent(ratios.rentabilite?.marge_brute)}</span></div>
                        <div className="w-full bg-gray-200 rounded-full h-2"><div className="bg-green-600 h-2 rounded-full" style={{ width: `${Math.min(ratios.rentabilite?.marge_brute || 0, 100)}%` }}></div></div>
                      </div>
                      <div>
                        <div className="flex justify-between mb-1"><span className="text-sm">Marge nette</span><span className="font-bold">{formatPercent(ratios.rentabilite?.marge_nette)}</span></div>
                        <div className="w-full bg-gray-200 rounded-full h-2"><div className={`h-2 rounded-full ${(ratios.rentabilite?.marge_nette || 0) >= 0 ? 'bg-green-600' : 'bg-red-600'}`} style={{ width: `${Math.min(Math.abs(ratios.rentabilite?.marge_nette || 0), 100)}%` }}></div></div>
                      </div>
                      <div>
                        <div className="flex justify-between mb-1"><span className="text-sm">ROA (Rentabilité des actifs)</span><span className="font-bold">{formatPercent(ratios.rentabilite?.roa)}</span></div>
                        <div className="w-full bg-gray-200 rounded-full h-2"><div className="bg-blue-600 h-2 rounded-full" style={{ width: `${Math.min(Math.abs(ratios.rentabilite?.roa || 0), 100)}%` }}></div></div>
                      </div>
                    </div>
                  </div>
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-blue-800 mb-3">Liquidité</h4>
                    <div className="space-y-3">
                      <div className="p-3 bg-white rounded">
                        <p className="text-sm text-gray-600">Ratio de liquidité générale</p>
                        <p className="text-2xl font-bold text-blue-700">{(ratios.liquidite?.ratio_liquidite_generale || 0).toFixed(2)}</p>
                        <p className="text-xs text-gray-500">{ratios.liquidite?.ratio_liquidite_generale >= 1 ? '✓ Bon' : '⚠ À surveiller'}</p>
                      </div>
                      <div className="p-3 bg-white rounded">
                        <p className="text-sm text-gray-600">Ratio de liquidité immédiate</p>
                        <p className="text-2xl font-bold text-blue-700">{(ratios.liquidite?.ratio_liquidite_immediate || 0).toFixed(2)}</p>
                      </div>
                    </div>
                  </div>
                  <div className="bg-orange-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-orange-800 mb-3">Activité</h4>
                    <div className="space-y-3">
                      <div className="p-3 bg-white rounded">
                        <p className="text-sm text-gray-600">Rotation des stocks</p>
                        <p className="text-2xl font-bold text-orange-700">{(ratios.activite?.rotation_stocks || 0).toFixed(2)}x</p>
                        <p className="text-xs text-gray-500">{ratios.activite?.delai_rotation_stocks || 0} jours</p>
                      </div>
                      <div className="p-3 bg-white rounded">
                        <p className="text-sm text-gray-600">Rotation des créances</p>
                        <p className="text-2xl font-bold text-orange-700">{(ratios.activite?.rotation_creances || 0).toFixed(2)}x</p>
                        <p className="text-xs text-gray-500">{ratios.activite?.delai_recouvrement || 0} jours de recouvrement</p>
                      </div>
                    </div>
                  </div>
                  <div className="bg-gray-50 p-4 rounded-lg lg:col-span-3">
                    <h4 className="font-semibold text-gray-800 mb-3">Données de Base</h4>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="text-center p-3 bg-white rounded">
                        <p className="text-xs text-gray-500">Chiffre d'affaires</p>
                        <p className="font-bold text-lg">{formatCurrency(ratios.donnees_base?.chiffre_affaires)}</p>
                      </div>
                      <div className="text-center p-3 bg-white rounded">
                        <p className="text-xs text-gray-500">Résultat net</p>
                        <p className={`font-bold text-lg ${(ratios.donnees_base?.resultat_net || 0) >= 0 ? 'text-green-600' : 'text-red-600'}`}>{formatCurrency(ratios.donnees_base?.resultat_net)}</p>
                      </div>
                      <div className="text-center p-3 bg-white rounded">
                        <p className="text-xs text-gray-500">Total actif</p>
                        <p className="font-bold text-lg">{formatCurrency(ratios.donnees_base?.total_actif)}</p>
                      </div>
                      <div className="text-center p-3 bg-white rounded">
                        <p className="text-xs text-gray-500">Trésorerie</p>
                        <p className="font-bold text-lg">{formatCurrency(ratios.donnees_base?.tresorerie)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

    </div>
  );
}
