import React, { useState, useEffect } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import MagasinGros from '../components/approvisionnement/MagasinGros';
import MagasinDetail from '../components/approvisionnement/MagasinDetail';
import { Package } from 'lucide-react';
import apiClient from '../services/apiClient';
import { PageLoader } from '../components/LoadingFallback';

export default function ApprovisionnementPage() {
  const { token, tenant, user } = useTenantStore();
  const { t } = useLanguageStore();
  const [activeTab, setActiveTab] = useState('gros');
  const [warehouses, setWarehouses] = useState([]);
  const [loading, setLoading] = useState(true);

  const headers = {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
    'X-Tenant-ID': tenant?.id || '',
  };

  useEffect(() => {
    const loadWarehouses = async () => {
      try {
        const res = await apiClient.get('/warehouses');
        const whs = res.data?.data || res.data || [];
        setWarehouses(Array.isArray(whs) ? whs : []);
        console.log('[ApprovisionnementPage] Warehouses loaded:', whs.length);
      } catch (err) {
        console.error('[ApprovisionnementPage] Error loading warehouses:', err);
        setWarehouses([]);
      } finally {
        setLoading(false);
      }
    };
    
    loadWarehouses();
    if (user?.role?.includes('detail')) setActiveTab('detail');
  }, [user?.role]);

  const grosW = warehouses.find(w => w.type === 'gros');
  const detailW = warehouses.find(w => w.type === 'detail');

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Package className="text-orange-500" size={24} />
          Approvisionnement
        </h1>
        <p className="text-gray-500 dark:text-gray-400 text-sm">Gestion des stocks, achats, transferts et commandes</p>
      </div>

      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-slate-700">
        <nav className="flex">
          <button
            onClick={() => setActiveTab('gros')}
            className={`flex-1 py-3 px-4 text-center font-semibold border-b-4 transition-all ${
              activeTab === 'gros'
                ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700'
            }`}
          >
            🏭 Magasin Gros
          </button>
          <button
            onClick={() => setActiveTab('detail')}
            className={`flex-1 py-3 px-4 text-center font-semibold border-b-4 transition-all ${
              activeTab === 'detail'
                ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700'
            }`}
          >
            🏪 Magasin Détail
          </button>
        </nav>
      </div>

      {activeTab === 'gros' ? (
        <MagasinGros headers={headers} warehouse={grosW} userRole={user?.role} />
      ) : (
        <MagasinDetail headers={headers} warehouse={detailW} grosWarehouse={grosW} userRole={user?.role} />
      )}
    </div>
  );
}
