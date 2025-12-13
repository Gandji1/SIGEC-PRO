import React, { useState, useEffect, useCallback, lazy, Suspense, memo } from 'react';
import { BarChart3, TrendingUp, Calendar, RefreshCw, FileText, DollarSign, ShoppingCart, Wallet, ArrowUpRight, ArrowDownRight } from 'lucide-react';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import ExportButton from '../components/ExportButton';

// Lazy load recharts (très lourd ~400kb)
const LazyCharts = lazy(() => import('recharts').then(module => ({
  default: ({ data, type }) => {
    const { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } = module;
    
    if (type === 'line') {
      return (
        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={data}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
            <XAxis dataKey="date" stroke="#6b7280" fontSize={12} />
            <YAxis stroke="#6b7280" fontSize={12} />
            <Tooltip 
              contentStyle={{ backgroundColor: '#1f2937', border: 'none', borderRadius: '8px', color: '#fff' }}
              labelStyle={{ color: '#9ca3af' }}
            />
            <Legend />
            <Line type="monotone" dataKey="total" stroke="#0284c7" strokeWidth={2} dot={{ fill: '#0284c7' }} />
          </LineChart>
        </ResponsiveContainer>
      );
    }
    return (
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
          <XAxis dataKey="date" stroke="#6b7280" fontSize={12} />
          <YAxis stroke="#6b7280" fontSize={12} />
          <Tooltip 
            contentStyle={{ backgroundColor: '#1f2937', border: 'none', borderRadius: '8px', color: '#fff' }}
          />
          <Bar dataKey="total" fill="#0284c7" radius={[4, 4, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    );
  }
})));

// Skeleton pour les graphiques
const ChartSkeleton = memo(() => (
  <div className="h-72 bg-gray-100 dark:bg-gray-700 rounded-xl animate-pulse flex items-center justify-center">
    <div className="text-center">
      <BarChart3 size={40} className="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
      <span className="text-gray-400 dark:text-gray-500 text-sm">Chargement du graphique...</span>
    </div>
  </div>
));

// Stat Card
const StatCard = memo(({ title, value, icon: Icon, color, trend, trendValue }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all">
    <div className="flex items-center justify-between">
      <div>
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</p>
        <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">{value}</p>
        {trend && (
          <div className={`flex items-center gap-1 mt-2 text-sm ${trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
            {trend === 'up' ? <ArrowUpRight size={16} /> : <ArrowDownRight size={16} />}
            <span>{trendValue}</span>
          </div>
        )}
      </div>
      <div className={`w-12 h-12 ${color} rounded-xl flex items-center justify-center`}>
        <Icon className="text-white" size={24} />
      </div>
    </div>
  </div>
));

export default function ReportsPage() {
  const { t } = useLanguageStore();
  const [reportData, setReportData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0],
  });
  const [reportType, setReportType] = useState('sales');

  const fetchReport = useCallback(async () => {
    setLoading(true);
    try {
      let response;
      const params = new URLSearchParams({
        start_date: dateRange.startDate,
        end_date: dateRange.endDate,
      });

      if (reportType === 'sales') {
        response = await apiClient.get(`/sales/report?${params}`);
      } else if (reportType === 'purchases') {
        response = await apiClient.get(`/purchases/report?${params}`);
      } else if (reportType === 'accounting') {
        response = await apiClient.get(`/accounting/summary?period_start=${dateRange.startDate}&period_end=${dateRange.endDate}`);
      } else if (reportType === 'cash') {
        response = await apiClient.get(`/cash-register/journal?date=${dateRange.endDate}`);
      }

      setReportData(response.data);
    } catch (error) {
      console.error('Error fetching report:', error);
    } finally {
      setLoading(false);
    }
  }, [reportType, dateRange]);

  useEffect(() => {
    fetchReport();
  }, [fetchReport]);

  const renderSalesReport = () => {
    if (!reportData) return null;

    const salesByDay = reportData.daily || [];

    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Total Ventes"
            value={formatCurrency(reportData.total_sales)}
            icon={DollarSign}
            color="bg-blue-500"
          />
          <StatCard
            title="Nombre Ventes"
            value={reportData.sales_count || '0'}
            icon={ShoppingCart}
            color="bg-green-500"
          />
          <StatCard
            title="Total Taxe"
            value={formatCurrency(reportData.total_tax)}
            icon={FileText}
            color="bg-purple-500"
          />
          <StatCard
            title="Panier Moyen"
            value={formatCurrency(reportData.total_sales / (reportData.sales_count || 1))}
            icon={TrendingUp}
            color="bg-orange-500"
          />
        </div>

        {salesByDay.length > 0 && (
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4">📈 Évolution des Ventes</h3>
            <Suspense fallback={<ChartSkeleton />}>
              <LazyCharts data={salesByDay} type="line" />
            </Suspense>
          </div>
        )}
      </div>
    );
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const renderCashReport = () => {
    if (!reportData?.data) return (
      <div className="bg-white dark:bg-gray-800 rounded-xl p-8 text-center border border-gray-100 dark:border-gray-700">
        <Wallet size={48} className="mx-auto text-gray-300 dark:text-gray-600 mb-4" />
        <p className="text-gray-500 dark:text-gray-400">Aucune donnée de caisse pour cette période</p>
      </div>
    );

    const data = reportData.data;
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Ventes"
            value={formatCurrency(data.totals?.sales)}
            icon={ShoppingCart}
            color="bg-green-500"
          />
          <StatCard
            title="Dépôts"
            value={formatCurrency(data.totals?.deposits)}
            icon={ArrowUpRight}
            color="bg-blue-500"
          />
          <StatCard
            title="Retraits"
            value={formatCurrency(data.totals?.withdrawals)}
            icon={ArrowDownRight}
            color="bg-red-500"
          />
          <StatCard
            title="Solde Final"
            value={formatCurrency(data.final_balance)}
            icon={Wallet}
            color="bg-purple-500"
          />
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4">
            📋 Mouvements du {data.date}
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[600px]">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-700">
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Heure</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Référence</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Description</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Montant</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Solde</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {(data.movements || []).map((mv, idx) => (
                  <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{new Date(mv.created_at).toLocaleTimeString('fr-FR')}</td>
                    <td className="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-400">{mv.reference}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${
                        mv.type === 'in' 
                          ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' 
                          : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                      }`}>
                        {mv.type === 'in' ? <ArrowUpRight size={12} /> : <ArrowDownRight size={12} />}
                        {mv.type === 'in' ? 'Entrée' : 'Sortie'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{mv.description}</td>
                    <td className={`px-4 py-3 text-right font-medium ${mv.type === 'in' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                      {mv.type === 'in' ? '+' : '-'}{formatCurrency(mv.amount)}
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">{formatCurrency(mv.running_balance)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    );
  };

  const renderAccountingReport = () => {
    if (!reportData) return null;

    const profit = (reportData.total_sales || 0) - (reportData.total_purchases || 0);

    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Total Ventes"
            value={formatCurrency(reportData.total_sales)}
            icon={TrendingUp}
            color="bg-blue-500"
          />
          <StatCard
            title="Total Achats"
            value={formatCurrency(reportData.total_purchases)}
            icon={ShoppingCart}
            color="bg-red-500"
          />
          <StatCard
            title="Profit Brut"
            value={formatCurrency(profit)}
            icon={DollarSign}
            color={profit >= 0 ? "bg-green-500" : "bg-red-500"}
            trend={profit >= 0 ? 'up' : 'down'}
            trendValue={`${((profit / (reportData.total_sales || 1)) * 100).toFixed(1)}%`}
          />
          <StatCard
            title="Total Taxe"
            value={formatCurrency(reportData.total_tax)}
            icon={FileText}
            color="bg-purple-500"
          />
        </div>

        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4">📊 Résumé Comptable</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border-l-4 border-blue-500">
              <p className="text-sm text-gray-500 dark:text-gray-400">Écritures Non Comptabilisées</p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">{reportData.unposted_entries || '0'}</p>
            </div>
            <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border-l-4 border-green-500">
              <p className="text-sm text-gray-500 dark:text-gray-400">Marge Brute</p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {reportData.total_sales ? ((profit / reportData.total_sales) * 100).toFixed(1) : 0}%
              </p>
            </div>
            <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border-l-4 border-purple-500">
              <p className="text-sm text-gray-500 dark:text-gray-400">Taux de Taxe</p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {reportData.total_sales ? ((reportData.total_tax / reportData.total_sales) * 100).toFixed(1) : 0}%
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Préparer les données pour l'export
  const getExportData = () => {
    if (!reportData) return [];
    
    if (reportType === 'cash' && reportData.data?.movements) {
      return reportData.data.movements.map(mv => ({
        date: new Date(mv.created_at).toLocaleString('fr-FR'),
        reference: mv.reference,
        type: mv.type === 'in' ? 'Entrée' : 'Sortie',
        description: mv.description,
        amount: mv.amount,
        balance: mv.running_balance
      }));
    }
    
    if (reportType === 'sales' && reportData.daily) {
      return reportData.daily;
    }
    
    return [];
  };

  const getExportColumns = () => {
    if (reportType === 'cash') {
      return [
        { key: 'date', header: 'Date/Heure' },
        { key: 'reference', header: 'Référence' },
        { key: 'type', header: 'Type' },
        { key: 'description', header: 'Description' },
        { key: 'amount', header: 'Montant', type: 'currency' },
        { key: 'balance', header: 'Solde', type: 'currency' }
      ];
    }
    
    return [
      { key: 'date', header: 'Date' },
      { key: 'total', header: 'Total', type: 'currency' },
      { key: 'count', header: 'Nombre' }
    ];
  };

  const reportTypes = [
    { value: 'sales', label: 'Ventes', icon: ShoppingCart, color: 'text-green-600' },
    { value: 'purchases', label: 'Achats', icon: TrendingUp, color: 'text-blue-600' },
    { value: 'accounting', label: 'Comptabilité', icon: FileText, color: 'text-purple-600' },
    { value: 'cash', label: 'Journal de Caisse', icon: Wallet, color: 'text-orange-600' }
  ];

  return (
    <div className="p-4 sm:p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <BarChart3 className="text-brand-600" size={32} />
            Rapports
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">Analysez vos données commerciales</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchReport}
            disabled={loading}
            className="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
            Actualiser
          </button>
          <ExportButton
            data={getExportData()}
            columns={getExportColumns()}
            filename={`rapport-${reportType}-${dateRange.startDate}`}
            title={`Rapport ${reportTypes.find(r => r.value === reportType)?.label || ''}`}
            subtitle={`Du ${dateRange.startDate} au ${dateRange.endDate}`}
            variant="primary"
          />
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 sm:p-6 shadow-sm">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Report Type Selector */}
          <div className="sm:col-span-2 lg:col-span-2">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Type de Rapport
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {reportTypes.map(type => {
                const Icon = type.icon;
                return (
                  <button
                    key={type.value}
                    onClick={() => setReportType(type.value)}
                    className={`flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                      reportType === type.value
                        ? 'bg-brand-600 text-white shadow-lg'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                    }`}
                  >
                    <Icon size={16} />
                    <span className="hidden sm:inline">{type.label}</span>
                  </button>
                );
              })}
            </div>
          </div>
          
          {/* Date Range */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              <Calendar size={14} className="inline mr-1" /> Du
            </label>
            <input
              type="date"
              value={dateRange.startDate}
              onChange={(e) => setDateRange({...dateRange, startDate: e.target.value})}
              className="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              <Calendar size={14} className="inline mr-1" /> Au
            </label>
            <input
              type="date"
              value={dateRange.endDate}
              onChange={(e) => setDateRange({...dateRange, endDate: e.target.value})}
              className="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
            />
          </div>
        </div>
      </div>

      {/* Report Content */}
      {loading ? (
        <div className="space-y-4">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {[1,2,3,4].map(i => (
              <div key={i} className="bg-white dark:bg-gray-800 rounded-xl p-5 animate-pulse border border-gray-100 dark:border-gray-700">
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-3"></div>
                <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-32"></div>
              </div>
            ))}
          </div>
          <ChartSkeleton />
        </div>
      ) : reportType === 'sales' ? (
        renderSalesReport()
      ) : reportType === 'accounting' ? (
        renderAccountingReport()
      ) : reportType === 'cash' ? (
        renderCashReport()
      ) : (
        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
          <p className="text-gray-500 dark:text-gray-400 text-center py-8">
            Sélectionnez un type de rapport pour afficher les données
          </p>
        </div>
      )}
    </div>
  );
}
