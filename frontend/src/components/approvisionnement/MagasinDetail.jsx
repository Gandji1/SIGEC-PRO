import React, { useState, useEffect, useCallback, useMemo, memo } from 'react';
import { KPI, NavBtn, Card, Empty, StatusBadge, PriorityBadge } from './UIComponents';
import { RequestModal, ServeModal } from './Modals';
import { formatCurrency, formatDate } from './utils';
import apiClient from '../../services/apiClient';

// Cache global persistant (survit aux re-renders)
const globalDetailCache = {
  products: [],
  dashboard: {},
  loaded: false,
};

export default function MagasinDetail({ headers, warehouse, grosWarehouse, userRole }) {
  const canEdit = ['manager', 'super_admin', 'owner', 'tenant'].includes(userRole);
  
  const [section, setSection] = useState('dashboard');
  const [dashboard, setDashboard] = useState(globalDetailCache.dashboard);
  const [products, setProducts] = useState(globalDetailCache.products);
  const [requests, setRequests] = useState([]);
  const [transfers, setTransfers] = useState([]);
  const [orders, setOrders] = useState([]);
  const [stocks, setStocks] = useState([]);
  const [modal, setModal] = useState(null);
  const [selected, setSelected] = useState(null);
  const [tableLoading, setTableLoading] = useState(!globalDetailCache.loaded);
  const [dataReady, setDataReady] = useState(globalDetailCache.loaded);

  // Charger les données au montage - toujours recharger pour avoir les données fraîches
  useEffect(() => {
    let isMounted = true;
    const wid = warehouse?.id || '';
    
    // Réinitialiser le cache pour forcer le rechargement
    globalDetailCache.loaded = false;
    
    const loadAllData = async () => {
      try {
        const [dashRes, prodRes, reqRes, transRes, ordRes] = await Promise.allSettled([
          apiClient.get('/approvisionnement/detail/dashboard'),
          apiClient.get('/products?per_page=100'),
          apiClient.get(`/approvisionnement/requests?from_warehouse_id=${wid}&per_page=50`),
          apiClient.get(`/approvisionnement/transfers?to_warehouse_id=${wid}&per_page=50`),
          apiClient.get('/pos/orders?status=pending&per_page=50'),
        ]);
        
        if (!isMounted) return;
        
        // Extraire les données
        const dashData = dashRes.status === 'fulfilled' ? (dashRes.value.data || {}) : {};
        const prodData = prodRes.status === 'fulfilled' ? (prodRes.value.data?.data || prodRes.value.data || []) : [];
        const reqData = reqRes.status === 'fulfilled' ? (reqRes.value.data?.data || reqRes.value.data || []) : [];
        const transData = transRes.status === 'fulfilled' ? (transRes.value.data?.data || transRes.value.data || []) : [];
        const ordData = ordRes.status === 'fulfilled' ? (ordRes.value.data?.data || ordRes.value.data || []) : [];
        
        // Stocker dans le cache global
        globalDetailCache.dashboard = dashData;
        globalDetailCache.products = Array.isArray(prodData) ? prodData : [];
        globalDetailCache.loaded = true;
        
        // Mettre à jour le state
        setDashboard(dashData);
        setProducts(globalDetailCache.products);
        setRequests(Array.isArray(reqData) ? reqData : []);
        setTransfers(Array.isArray(transData) ? transData : []);
        setOrders(Array.isArray(ordData) ? ordData : []);
        setDataReady(true);
        
        console.log('[MagasinDetail] Données chargées:', { products: globalDetailCache.products.length });
      } catch (e) {
        console.error('[MagasinDetail] Erreur chargement:', e);
      } finally {
        if (isMounted) setTableLoading(false);
      }
    };
    
    loadAllData();
    
    return () => { isMounted = false; };
  }, [warehouse?.id]);

  // Charger données spécifiques de section
  useEffect(() => {
    const wid = warehouse?.id || '';
    
    const loadSection = async () => {
      try {
        if (section === 'stocks') {
          setTableLoading(true);
          const stkRes = await apiClient.get(`/stocks?warehouse_id=${wid}&per_page=100`);
          setStocks(stkRes.data?.data || stkRes.data || []);
          setTableLoading(false);
        }
      } catch (e) { 
        console.error('[MagasinDetail] Section load error:', e); 
        setTableLoading(false); 
      }
    };
    
    loadSection();
  }, [section, warehouse?.id]);

  // Refresh
  const refresh = useCallback(async () => {
    setTableLoading(true);
    try {
      const wid = warehouse?.id || '';
      const [dashRes, reqRes, transRes, ordRes] = await Promise.allSettled([
        apiClient.get('/approvisionnement/detail/dashboard'),
        apiClient.get(`/approvisionnement/requests?from_warehouse_id=${wid}&per_page=50`),
        apiClient.get(`/approvisionnement/transfers?to_warehouse_id=${wid}&per_page=50`),
        apiClient.get('/pos/orders?status=pending&per_page=50'),
      ]);
      
      if (dashRes.status === 'fulfilled') setDashboard(dashRes.value.data || {});
      if (reqRes.status === 'fulfilled') setRequests(reqRes.value.data?.data || reqRes.value.data || []);
      if (transRes.status === 'fulfilled') setTransfers(transRes.value.data?.data || transRes.value.data || []);
      if (ordRes.status === 'fulfilled') setOrders(ordRes.value.data?.data || ordRes.value.data || []);
    } catch (e) { console.error('[MagasinDetail] Refresh error:', e); }
    setTableLoading(false);
  }, [warehouse?.id]);

  const submitReq = async (id) => {
    try {
      await apiClient.post(`/approvisionnement/requests/${id}/submit`);
      refresh(); 
      alert('✅ Demande soumise au Magasin Gros');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.message || e.message));
    }
  };

  const receiveTransfer = async (id) => {
    if (!confirm('Confirmer la réception de ce transfert ? Le stock a déjà été ajouté à votre magasin.')) return;
    try {
      await apiClient.post(`/approvisionnement/transfers/${id}/receive`);
      refresh(); 
      alert('✅ Réception confirmée');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  const validateTransfer = async (id) => {
    if (!confirm('Valider définitivement ce transfert ?')) return;
    try {
      await apiClient.post(`/approvisionnement/transfers/${id}/validate`);
      refresh(); 
      alert('✅ Transfert validé');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  const serveOrder = async (id) => {
    try {
      await apiClient.post(`/approvisionnement/orders/${id}/serve`);
      refresh(); 
      alert('✅ Commande servie');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.message || e.message));
    }
  };

  const pendingTransfers = useMemo(() => transfers.filter(t => ['executed', 'received'].includes(t.status)), [transfers]);
  const pendingOrders = useMemo(() => orders.filter(o => ['pending', 'preparing', 'paid'].includes(o.status)), [orders]);

  // RENDU INSTANTANÉ - Pas de loading global
  return (
    <div className="space-y-6">
      {/* KPIs - Affichage immédiat */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <KPI title="Stock SDU" value={dashboard?.available_stock || stocks.length} color="green" icon="📦" />
        <KPI title="Valeur Stock" value={dashboard?.stock_value} format="currency" color="blue" icon="💰" />
        <KPI title="Commandes POS" value={dashboard?.pending_orders || pendingOrders.length} color="purple" icon="🛒" />
        <KPI title="Transferts" value={dashboard?.pending_transfers_count || pendingTransfers.length} color="orange" icon="📥" />
        <KPI title="Mes Demandes" value={dashboard?.pending_requests_count || requests.filter(r => ['draft', 'requested'].includes(r.status)).length} color="indigo" icon="📤" />
      </div>

      {/* Navigation - Toujours visible */}
      <div className="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-2">
        <NavBtn active={section === 'dashboard'} onClick={() => setSection('dashboard')} icon="📊" label="Dashboard" color="green" />
        <NavBtn active={section === 'requests'} onClick={() => setSection('requests')} icon="📤" label="Demandes" count={requests.length || undefined} color="green" />
        <NavBtn active={section === 'reception'} onClick={() => setSection('reception')} icon="📥" label="Réceptionner" count={pendingTransfers.length || undefined} color="green" />
        <NavBtn active={section === 'orders'} onClick={() => setSection('orders')} icon="🛒" label="Commandes POS" count={pendingOrders.length || undefined} color="green" />
        <NavBtn active={section === 'stocks'} onClick={() => setSection('stocks')} icon="📦" label="Stock SDU" color="green" />
        <div className="flex-1" />
        {/* Bouton Nouvelle Demande - Visible uniquement pour le Gérant */}
        {canEdit && (
          <button 
            disabled={!dataReady}
            onClick={() => setModal('request')} 
            className={`px-4 py-2 rounded-lg font-medium ${dataReady ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-400 text-white cursor-wait'}`}
          >
            {dataReady ? '+ Nouvelle Demande' : '⏳ Chargement...'}
          </button>
        )}
      </div>

      {/* SECTIONS avec loader local */}
      {section === 'dashboard' && (
        <DashboardSection requests={requests} pendingTransfers={pendingTransfers} pendingOrders={pendingOrders} receiveTransfer={receiveTransfer} validateTransfer={validateTransfer} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'requests' && (
        <MyRequestsSection requests={requests} submitReq={submitReq} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'reception' && (
        <TransfersSection transfers={pendingTransfers} receiveTransfer={receiveTransfer} validateTransfer={validateTransfer} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'orders' && (
        <OrdersSection orders={pendingOrders} onServe={(o) => { setSelected(o); setModal('serve'); }} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'stocks' && (
        <StocksSection stocks={stocks} loading={tableLoading} />
      )}

      {/* MODALS */}
      {modal === 'request' && (
        <RequestModal headers={headers} products={products} fromWarehouseId={warehouse?.id} toWarehouseId={grosWarehouse?.id} onClose={() => setModal(null)} onSaved={() => { setModal(null); refresh(); }} />
      )}
      {modal === 'serve' && selected && (
        <ServeModal headers={headers} order={selected} warehouseId={warehouse?.id} onClose={() => { setModal(null); setSelected(null); }} onSaved={() => { setModal(null); setSelected(null); refresh(); }} />
      )}
    </div>
  );
}

// Skeleton
const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-3"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

// ===== SECTIONS AVEC LOADER LOCAL =====
const DashboardSection = memo(({ requests, pendingTransfers, pendingOrders, receiveTransfer, validateTransfer, loading, canEdit }) => (
  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <Card title="📥 Transferts à Réceptionner">
      {loading ? <TableSkeleton /> : (
        <>
          {pendingTransfers.slice(0, 5).map(t => (
            <div key={t.id} className="flex justify-between items-center py-3 border-b last:border-0">
              <div>
                <span className="font-mono font-medium">{t.reference}</span>
                <span className="text-gray-500 ml-2">{t.items?.length || 0} articles</span>
              </div>
              <div className="flex items-center gap-2">
                <StatusBadge status={t.status} />
                {canEdit && t.status === 'executed' && <button onClick={() => receiveTransfer(t.id)} className="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200">Réceptionner</button>}
                {canEdit && t.status === 'received' && <button onClick={() => validateTransfer(t.id)} className="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200">Valider</button>}
              </div>
            </div>
          ))}
          {pendingTransfers.length === 0 && <Empty msg="Aucun transfert en attente" />}
        </>
      )}
    </Card>

    <Card title="🛒 Commandes POS à Servir">
      {loading ? <TableSkeleton /> : (
        <>
          {pendingOrders.slice(0, 5).map(o => (
            <div key={o.id} className="flex justify-between items-center py-3 border-b last:border-0">
              <div>
                <span className="font-mono font-medium">{o.reference}</span>
                <span className="text-gray-500 ml-2">{o.customer?.name || 'Comptoir'}</span>
              </div>
              <div className="flex items-center gap-3">
                <span className="font-medium">{formatCurrency(o.total)}</span>
                <StatusBadge status={o.status} />
              </div>
            </div>
          ))}
          {pendingOrders.length === 0 && <Empty msg="Aucune commande en attente" />}
        </>
      )}
    </Card>

    <Card title="📤 Mes Demandes Récentes" className="lg:col-span-2">
      {loading ? <TableSkeleton /> : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-2 text-left text-xs font-semibold text-gray-600">Référence</th>
                <th className="px-4 py-2 text-left text-xs font-semibold text-gray-600">Priorité</th>
                <th className="px-4 py-2 text-left text-xs font-semibold text-gray-600">Produits</th>
                <th className="px-4 py-2 text-left text-xs font-semibold text-gray-600">Statut</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {requests.slice(0, 5).map(r => (
                <tr key={r.id}>
                  <td className="px-4 py-2 font-mono">{r.reference}</td>
                  <td className="px-4 py-2"><PriorityBadge priority={r.priority} /></td>
                  <td className="px-4 py-2">{r.items?.length || 0}</td>
                  <td className="px-4 py-2"><StatusBadge status={r.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
          {requests.length === 0 && <Empty msg="Aucune demande" />}
        </div>
      )}
    </Card>
  </div>
));

const MyRequestsSection = memo(({ requests, submitReq, loading, canEdit }) => (
  <Card title="📤 Mes Demandes vers le Magasin Gros">
    {loading ? <TableSkeleton /> : (
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Priorité</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date Besoin</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Produits</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {requests.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium">{r.reference}</td>
                <td className="px-4 py-3"><PriorityBadge priority={r.priority} /></td>
                <td className="px-4 py-3 text-gray-600">{r.needed_by_date ? formatDate(r.needed_by_date) : '-'}</td>
                <td className="px-4 py-3">{r.items?.length || 0} articles</td>
                <td className="px-4 py-3"><StatusBadge status={r.status} /></td>
                {canEdit && (
                  <td className="px-4 py-3">
                    {r.status === 'draft' && <button onClick={() => submitReq(r.id)} className="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Soumettre</button>}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {requests.length === 0 && <Empty msg="Aucune demande" />}
      </div>
    )}
  </Card>
));

function TransfersSection({ transfers, receiveTransfer, validateTransfer, canEdit }) {
  return (
    <Card title="📥 Transferts à Réceptionner/Valider" subtitle="Transferts du Magasin Gros">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Origine</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Articles</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {transfers.map(t => (
              <tr key={t.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium">{t.reference}</td>
                <td className="px-4 py-3">{t.from_warehouse?.name || 'Magasin Gros'}</td>
                <td className="px-4 py-3 text-gray-600">{formatDate(t.created_at)}</td>
                <td className="px-4 py-3">{t.items?.length || 0} produits</td>
                <td className="px-4 py-3"><StatusBadge status={t.status} /></td>
                {canEdit && (
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {t.status === 'executed' && (
                        <button onClick={() => receiveTransfer(t.id)} className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                          📥 Réceptionner
                        </button>
                      )}
                      {t.status === 'received' && (
                        <button onClick={() => validateTransfer(t.id)} className="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                          ✓ Valider
                        </button>
                      )}
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {transfers.length === 0 && <Empty msg="Aucun transfert en attente" />}
      </div>
    </Card>
  );
}

function OrdersSection({ orders, onServe, canEdit }) {
  return (
    <Card title="🛒 Commandes POS à Servir" subtitle="Préparer et servir les commandes">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Client</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Articles</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Total</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {orders.map(o => (
              <tr key={o.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium">{o.reference}</td>
                <td className="px-4 py-3">{o.customer?.name || 'Comptoir'}</td>
                <td className="px-4 py-3">{o.items?.length || 0} produits</td>
                <td className="px-4 py-3 font-medium">{formatCurrency(o.total)}</td>
                <td className="px-4 py-3"><StatusBadge status={o.status} /></td>
                {canEdit && (
                  <td className="px-4 py-3">
                    {['pending', 'preparing'].includes(o.status) && (
                      <button onClick={() => onServe(o)} className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        🍽️ Servir
                      </button>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {orders.length === 0 && <Empty msg="Aucune commande en attente" />}
      </div>
    </Card>
  );
}

function StocksSection({ stocks }) {
  const totalValue = stocks.reduce((sum, s) => sum + (s.quantity || 0) * (s.cost_average || s.unit_cost || 0), 0);
  
  return (
    <Card title="📦 Stock Disponible Utilisable (SDU)" subtitle={`${stocks.length} produits disponibles`}>
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Produit</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Code</th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Quantité</th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Réservé</th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Disponible</th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">CMP</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {stocks.map(s => (
              <tr key={s.id} className={`hover:bg-gray-50 ${s.quantity < 10 ? 'bg-red-50' : ''}`}>
                <td className="px-4 py-3 font-medium">{s.product?.name || '-'}</td>
                <td className="px-4 py-3 font-mono text-gray-500">{s.product?.code || '-'}</td>
                <td className="px-4 py-3 text-right font-bold">{s.quantity}</td>
                <td className="px-4 py-3 text-right text-orange-600">{s.reserved || 0}</td>
                <td className="px-4 py-3 text-right font-bold text-green-600">{s.available}</td>
                <td className="px-4 py-3 text-right">{formatCurrency(s.cost_average || s.unit_cost)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot className="bg-gray-100 font-bold">
            <tr>
              <td colSpan="5" className="px-4 py-3 text-right">Total Valeur:</td>
              <td className="px-4 py-3 text-right text-lg">{formatCurrency(totalValue)}</td>
            </tr>
          </tfoot>
        </table>
        {stocks.length === 0 && <Empty msg="Aucun stock disponible" />}
      </div>
    </Card>
  );
}
