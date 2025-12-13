import React, { useState, useEffect, useCallback, useMemo, memo } from 'react';
import { KPI, NavBtn, Card, Empty, StatusBadge, PriorityBadge, MovementBadge } from './UIComponents';
import { PurchaseModal, ReceiveModal } from './Modals';
import { formatCurrency, formatDate, formatDateTime } from './utils';
import apiClient from '../../services/apiClient';

// Cache local ultra-rapide avec TTL plus long
const cache = new Map();
const CACHE_TTL = 300000; // 5 minutes pour réduire les appels API
const getCache = (k) => { const c = cache.get(k); return c && Date.now() - c.t < CACHE_TTL ? c.d : null; };
const setCache = (k, d) => cache.set(k, { d, t: Date.now() });

export default function MagasinGros({ headers, warehouse, userRole }) {
  // Seul le Gérant peut effectuer des actions d'écriture (pas le Tenant/Owner)
  const canEdit = userRole === 'manager' || userRole === 'super_admin';
  
  const [section, setSection] = useState('dashboard');
  const [dashboard, setDashboard] = useState(getCache('gros_dash') || {});
  const [suppliers, setSuppliers] = useState([]);
  const [products, setProducts] = useState([]);
  const [purchases, setPurchases] = useState([]);
  const [requests, setRequests] = useState([]);
  const [movements, setMovements] = useState([]);
  const [stocks, setStocks] = useState([]);
  const [inventories, setInventories] = useState([]);
  const [modal, setModal] = useState(null);
  const [selected, setSelected] = useState(null);
  const [tableLoading, setTableLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0],
  });

  // Charger les données avec cache local pour affichage instantané
  useEffect(() => {
    let isMounted = true;
    
    // Afficher immédiatement les données du cache local
    const cachedDash = getCache('gros_dash');
    if (cachedDash) {
      setDashboard(cachedDash);
      setTableLoading(false);
    }
    
    const loadData = async () => {
      // Charger le dashboard
      try {
        const dashRes = await apiClient.get('/approvisionnement/gros/dashboard');
        if (isMounted) {
          setDashboard(dashRes.data || {});
          setCache('gros_dash', dashRes.data);
        }
      } catch (e) {
        console.error('[MagasinGros] Dashboard error:', e);
      }
      
      if (isMounted) setTableLoading(false);
      
      // Charger les autres données en arrière-plan
      try {
        const [suppRes, prodRes, purchRes, reqRes] = await Promise.allSettled([
          apiClient.get('/suppliers?per_page=100'),
          apiClient.get('/products?per_page=200'),
          apiClient.get('/approvisionnement/purchases?per_page=50'),
          apiClient.get('/approvisionnement/requests?status=requested&per_page=50'),
        ]);
        
        if (!isMounted) return;
        
        const suppData = suppRes?.status === 'fulfilled' ? (suppRes.value.data?.data || suppRes.value.data || []) : [];
        const prodData = prodRes?.status === 'fulfilled' ? (prodRes.value.data?.data || prodRes.value.data || []) : [];
        const purchData = purchRes?.status === 'fulfilled' ? (purchRes.value.data?.data || purchRes.value.data || []) : [];
        const reqData = reqRes?.status === 'fulfilled' ? (reqRes.value.data?.data || reqRes.value.data || []) : [];
        
        setSuppliers(Array.isArray(suppData) ? suppData : []);
        setProducts(Array.isArray(prodData) ? prodData : []);
        setPurchases(Array.isArray(purchData) ? purchData : []);
        setRequests(Array.isArray(reqData) ? reqData : []);
      } catch (e) {
        console.error('[MagasinGros] Background load error:', e);
      }
    };
    
    if (!cachedDash) setTableLoading(true);
    loadData();
    
    return () => { isMounted = false; };
  }, []);

  // Charger données spécifiques de section (seulement si pas déjà chargées)
  useEffect(() => {
    const wid = warehouse?.id || '';
    
    const loadSection = async () => {
      try {
        switch (section) {
          case 'movements':
            setTableLoading(true);
            const movRes = await apiClient.get('/approvisionnement/movements?per_page=50');
            setMovements(movRes.data?.data || movRes.data || []);
            setTableLoading(false);
            break;
          case 'stocks':
            setTableLoading(true);
            const stkRes = await apiClient.get(`/stocks?warehouse_id=${wid}&per_page=100`);
            setStocks(stkRes.data?.data || stkRes.data || []);
            setTableLoading(false);
            break;
          case 'inventory':
            setTableLoading(true);
            const invRes = await apiClient.get('/approvisionnement/inventories?per_page=20');
            setInventories(invRes.data?.data || invRes.data || []);
            setTableLoading(false);
            break;
        }
      } catch (e) { 
        console.error('[MagasinGros] Section load error:', e); 
        setTableLoading(false); 
      }
    };
    
    loadSection();
  }, [section, warehouse?.id]);

  // Refresh
  const refresh = useCallback(async () => {
    setTableLoading(true);
    try {
      const [dashRes, reqRes, purchRes] = await Promise.allSettled([
        apiClient.get('/approvisionnement/gros/dashboard'),
        apiClient.get('/approvisionnement/requests?status=requested&per_page=50'),
        apiClient.get('/approvisionnement/purchases?per_page=50'),
      ]);
      
      if (dashRes.status === 'fulfilled') setDashboard(dashRes.value.data || {});
      if (reqRes.status === 'fulfilled') setRequests(reqRes.value.data?.data || reqRes.value.data || []);
      if (purchRes.status === 'fulfilled') setPurchases(purchRes.value.data?.data || purchRes.value.data || []);
    } catch (e) { console.error('[MagasinGros] Refresh error:', e); }
    setTableLoading(false);
  }, []);

  const exportData = (type) => {
    const baseUrl = import.meta.env.VITE_API_URL || '/api';
    const url = `${baseUrl}/approvisionnement/exports/${type}?from=${dateRange.from}&to=${dateRange.to}&warehouse_id=${warehouse?.id || ''}`;
    window.open(url, '_blank');
  };

  // Gérant soumet pour approbation par le Tenant
  const submitForApproval = async (id) => {
    if (!confirm('Soumettre cette commande pour approbation par le propriétaire ?')) return;
    try {
      await apiClient.post(`/purchases/${id}/submit-for-approval`);
      refresh(); 
      alert('✅ Commande soumise pour approbation');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  // Tenant approuve la commande (envoi au fournisseur)
  const approveByTenant = async (id) => {
    if (!confirm('Approuver cette commande et l\'envoyer au fournisseur ?')) return;
    try {
      await apiClient.post(`/purchases/${id}/approve`);
      refresh(); 
      alert('✅ Commande approuvée et envoyée au fournisseur');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  // Tenant rejette la commande
  const rejectByTenant = async (id) => {
    const reason = prompt('Raison du rejet :');
    if (!reason) return;
    try {
      await apiClient.post(`/purchases/${id}/reject`, { reason });
      refresh(); 
      alert('✅ Commande rejetée');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  // Ancien submitPO pour compatibilité
  const submitPO = async (id) => {
    if (!confirm('Soumettre cette commande au fournisseur ?')) return;
    try {
      await apiClient.post(`/approvisionnement/purchases/${id}/submit`);
      refresh(); 
      alert('✅ Commande soumise');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.message || e.message));
    }
  };

  const approveReq = async (id) => {
    if (!confirm('Approuver cette demande ? Le stock sera transféré au Magasin Détail.')) return;
    try {
      await apiClient.post(`/approvisionnement/requests/${id}/approve`);
      refresh(); 
      alert('✅ Demande approuvée - Stock transféré au Détail');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.error || e.response?.data?.message || e.message));
    }
  };

  const rejectReq = async (id) => {
    const reason = prompt('Raison du rejet :');
    if (!reason) return;
    try {
      await apiClient.post(`/approvisionnement/requests/${id}/reject`, { reason });
      refresh(); 
      alert('✅ Demande rejetée');
    } catch (e) {
      alert('❌ Erreur: ' + (e.response?.data?.message || e.message));
    }
  };

  const pendingPO = useMemo(() => purchases.filter(p => ['ordered', 'partial', 'confirmed'].includes(p.status)), [purchases]);

  // RENDU INSTANTANÉ - Pas de loading global
  return (
    <div className="space-y-6">
      {/* KPIs - Affichage immédiat */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <KPI title="Valeur Stock" value={dashboard?.stock_value} format="currency" color="blue" icon="💰" />
        <KPI title="Mouvements Jour" value={dashboard?.movements_today} color="green" icon="📦" />
        <KPI title="Stock Bas" value={dashboard?.low_stock_count} color="yellow" icon="⚠️" />
        <KPI title="Commandes" value={dashboard?.pending_po_count} color="purple" icon="📋" />
        <KPI title="Demandes" value={dashboard?.pending_requests_count || requests.length} color="red" icon="📥" />
        <KPI title="Produits" value={dashboard?.total_products || stocks.length} color="indigo" icon="📊" />
      </div>

      {/* Navigation - Toujours visible */}
      <div className="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-2">
        <NavBtn active={section === 'dashboard'} onClick={() => setSection('dashboard')} icon="📊" label="Dashboard" />
        <NavBtn active={section === 'suppliers'} onClick={() => setSection('suppliers')} icon="🏭" label="Fournisseurs" />
        <NavBtn active={section === 'purchases'} onClick={() => setSection('purchases')} icon="📋" label="Commandes" />
        <NavBtn active={section === 'reception'} onClick={() => setSection('reception')} icon="📥" label="Réception" count={pendingPO.length || undefined} />
        <NavBtn active={section === 'requests'} onClick={() => setSection('requests')} icon="📨" label="Demandes" count={requests.length || undefined} />
        <NavBtn active={section === 'movements'} onClick={() => setSection('movements')} icon="🔄" label="Mouvements" />
        <NavBtn active={section === 'stocks'} onClick={() => setSection('stocks')} icon="📦" label="Stock" />
        <NavBtn active={section === 'inventory'} onClick={() => setSection('inventory')} icon="📋" label="Inventaire" />
        <div className="flex-1" />
        {/* Bouton Nouvelle Commande - Visible uniquement pour le Gérant */}
        {canEdit && (
          <button onClick={async () => {
            setModal('purchase');
            // Charger produits et fournisseurs si pas encore chargés
            if (products.length === 0 || suppliers.length === 0) {
              try {
                const [prodRes, suppRes] = await Promise.allSettled([
                  apiClient.get('/products?per_page=200'),
                  apiClient.get('/suppliers?per_page=100'),
                ]);
                if (prodRes.status === 'fulfilled') {
                  setProducts(prodRes.value.data?.data || prodRes.value.data || []);
                }
                if (suppRes.status === 'fulfilled') {
                  setSuppliers(suppRes.value.data?.data || suppRes.value.data || []);
                }
                console.log('[MagasinGros] Modal data loaded');
              } catch (e) {
                console.error('[MagasinGros] Erreur chargement modal:', e);
                alert('Erreur lors du chargement des données');
                setModal(null);
              }
            }
          }} className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
            + Nouvelle Commande
          </button>
        )}
      </div>

      {/* Filtres de période */}
      {['dashboard', 'movements', 'purchases'].includes(section) && (
        <div className="bg-white rounded-xl shadow-sm p-4 flex flex-wrap items-center gap-4">
          <div className="flex items-center gap-2">
            <label className="text-sm font-medium text-gray-600">Du:</label>
            <input
              type="date"
              value={dateRange.from}
              onChange={(e) => setDateRange(prev => ({ ...prev, from: e.target.value }))}
              className="px-3 py-2 border rounded-lg text-sm"
            />
          </div>
          <div className="flex items-center gap-2">
            <label className="text-sm font-medium text-gray-600">Au:</label>
            <input
              type="date"
              value={dateRange.to}
              onChange={(e) => setDateRange(prev => ({ ...prev, to: e.target.value }))}
              className="px-3 py-2 border rounded-lg text-sm"
            />
          </div>
          <div className="flex-1" />
          <div className="flex gap-2">
            <button
              onClick={() => exportData(section === 'movements' ? 'movements' : section === 'purchases' ? 'purchases' : 'dashboard')}
              className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 flex items-center gap-2"
            >
              📥 Exporter CSV
            </button>
          </div>
        </div>
      )}

      {/* SECTIONS - Avec loader local */}
      {section === 'dashboard' && (
        <DashboardSection purchases={purchases} requests={requests} stocks={stocks} approveReq={approveReq} rejectReq={rejectReq} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'suppliers' && (
        <SuppliersSection suppliers={suppliers} loading={tableLoading} />
      )}

      {section === 'purchases' && (
        <PurchasesSection 
          purchases={purchases} 
          submitPO={submitPO} 
          submitForApproval={submitForApproval}
          approveByTenant={approveByTenant}
          rejectByTenant={rejectByTenant}
          onReceive={(p) => { setSelected(p); setModal('receive'); }} 
          loading={tableLoading}
          canEdit={canEdit}
        />
      )}

      {section === 'reception' && (
        <ReceptionSection purchases={pendingPO} onReceive={(p) => { setSelected(p); setModal('receive'); }} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'requests' && (
        <RequestsSection requests={requests} approveReq={approveReq} rejectReq={rejectReq} loading={tableLoading} canEdit={canEdit} />
      )}

      {section === 'movements' && (
        <MovementsSection movements={movements} loading={tableLoading} />
      )}

      {section === 'stocks' && (
        <StocksSection stocks={stocks} onExport={() => exportData('stock')} loading={tableLoading} />
      )}

      {section === 'inventory' && (
        <div className="bg-white rounded-xl shadow-sm p-8 text-center">
          <div className="text-6xl mb-4">📋</div>
          <h3 className="text-xl font-bold text-gray-800 mb-2">Inventaire Enrichi</h3>
          <p className="text-gray-600 mb-6">
            Accédez à la fiche d'inventaire complète avec Stock Initial, Entrées, SDU, CMM, CMP et génération automatique de commandes.
          </p>
          <a 
            href="/inventory-enriched" 
            className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition"
          >
            📋 Ouvrir l'Inventaire Enrichi
          </a>
        </div>
      )}

      {/* MODALS */}
      {modal === 'purchase' && suppliers.length > 0 && products.length > 0 && (
        <PurchaseModal headers={headers} suppliers={suppliers} products={products} warehouseId={warehouse?.id} onClose={() => setModal(null)} onSaved={() => { setModal(null); refresh(); }} />
      )}
      {modal === 'purchase' && (suppliers.length === 0 || products.length === 0) && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-8 text-center">
            <div className="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p className="text-gray-600">Chargement des données...</p>
          </div>
        </div>
      )}
      {modal === 'receive' && selected && (
        <ReceiveModal headers={headers} purchase={selected} onClose={() => { setModal(null); setSelected(null); }} onSaved={() => { setModal(null); setSelected(null); refresh(); }} />
      )}
    </div>
  );
}

// Skeleton pour tables
const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-3"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

// ===== SECTIONS AVEC LOADER LOCAL =====
const DashboardSection = memo(({ purchases, requests, stocks, approveReq, rejectReq, loading, canEdit }) => (
  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <Card title="📋 Commandes Récentes">
      {loading ? <TableSkeleton /> : (
        <>
          {purchases.slice(0, 5).map(p => (
            <div key={p.id} className="flex justify-between items-center py-3 border-b last:border-0">
              <div>
                <span className="font-mono font-medium text-blue-600">{p.reference}</span>
                <span className="text-gray-500 ml-2">{p.supplier?.name || p.supplier_name}</span>
              </div>
              <div className="flex items-center gap-3">
                <span className="font-medium">{formatCurrency(p.total)}</span>
                <StatusBadge status={p.status} />
              </div>
            </div>
          ))}
          {purchases.length === 0 && <Empty msg="Aucune commande" />}
        </>
      )}
    </Card>

    <Card title="📨 Demandes en Attente">
      {loading ? <TableSkeleton /> : (
        <>
          {requests.slice(0, 5).map(r => (
            <div key={r.id} className="flex justify-between items-center py-3 border-b last:border-0">
              <div>
                <span className="font-mono font-medium">{r.reference}</span>
                <PriorityBadge priority={r.priority} />
              </div>
              {canEdit && (
                <div className="flex gap-2">
                  <button onClick={() => approveReq(r.id)} className="px-3 py-1 bg-green-100 text-green-700 rounded text-sm font-medium hover:bg-green-200">Approuver</button>
                  <button onClick={() => rejectReq(r.id)} className="px-3 py-1 bg-red-100 text-red-700 rounded text-sm font-medium hover:bg-red-200">Rejeter</button>
                </div>
              )}
            </div>
          ))}
          {requests.length === 0 && <Empty msg="Aucune demande en attente" />}
        </>
      )}
    </Card>

    <Card title="⚠️ Stock Bas" className="lg:col-span-2">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {stocks.filter(s => s.quantity < 20).slice(0, 8).map(s => (
          <div key={s.id} className="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
            <p className="font-medium truncate">{s.product?.name}</p>
            <p className="text-2xl font-bold text-yellow-600">{s.quantity}</p>
            <p className="text-xs text-gray-500">Disponible: {s.available}</p>
          </div>
        ))}
      </div>
      {stocks.filter(s => s.quantity < 20).length === 0 && <p className="text-center text-gray-500 py-4">Tous les stocks sont suffisants ✓</p>}
    </Card>
  </div>
));

const SuppliersSection = memo(({ suppliers, loading }) => (
  <Card title="🏭 Liste des Fournisseurs">
    {loading ? <TableSkeleton /> : (
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nom</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Téléphone</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Conditions</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {suppliers.map(s => (
              <tr key={s.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-gray-600">{s.email || '-'}</td>
                <td className="px-4 py-3 text-gray-600">{s.phone || '-'}</td>
                <td className="px-4 py-3 text-gray-600">{s.payment_terms || '-'}</td>
                <td className="px-4 py-3"><StatusBadge status={s.status || 'active'} /></td>
              </tr>
            ))}
          </tbody>
        </table>
        {suppliers.length === 0 && <Empty msg="Aucun fournisseur" />}
      </div>
    )}
  </Card>
));

const PurchasesSection = memo(({ purchases, submitPO, submitForApproval, approveByTenant, rejectByTenant, onReceive, loading, canEdit }) => (
  <Card title="📋 Commandes Fournisseurs" subtitle="Flux: Gérant crée → Tenant approuve → Fournisseur reçoit → Livraison → Réception">
    {loading ? <TableSkeleton /> : (
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fournisseur</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Articles</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Total</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {purchases.map(p => (
              <tr key={p.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium text-blue-600">{p.reference}</td>
                <td className="px-4 py-3">{p.supplier?.name || p.supplier_name || '-'}</td>
                <td className="px-4 py-3 text-gray-600">{formatDate(p.created_at)}</td>
                <td className="px-4 py-3">{p.items?.length || 0}</td>
                <td className="px-4 py-3 font-medium">{formatCurrency(p.total)}</td>
                <td className="px-4 py-3"><StatusBadge status={p.status} /></td>
                {canEdit && (
                  <td className="px-4 py-3">
                    <div className="flex gap-2 flex-wrap">
                      {/* Brouillon: Gérant soumet pour approbation */}
                      {(p.status === 'draft' || p.status === 'pending') && (
                        <button onClick={() => submitForApproval(p.id)} className="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200">
                          📤 Soumettre
                        </button>
                      )}
                      {/* En attente d'approbation: Tenant approuve ou rejette */}
                      {p.status === 'pending_approval' && (
                        <>
                          <button onClick={() => approveByTenant(p.id)} className="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                            ✓ Approuver
                          </button>
                          <button onClick={() => rejectByTenant(p.id)} className="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                            ✗ Rejeter
                          </button>
                        </>
                      )}
                      {/* Livré: Réceptionner */}
                      {p.status === 'delivered' && (
                        <button onClick={() => onReceive(p)} className="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                          📥 Réceptionner
                        </button>
                      )}
                      {/* Ancien flux pour compatibilité */}
                      {['ordered', 'partial', 'confirmed'].includes(p.status) && (
                        <button onClick={() => onReceive(p)} className="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200">
                          📥 Réceptionner
                        </button>
                      )}
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {purchases.length === 0 && <Empty msg="Aucune commande" />}
      </div>
    )}
  </Card>
));

function ReceptionSection({ purchases, onReceive, canEdit }) {
  return (
    <Card title="📥 Réception des Commandes" subtitle="Commandes en attente de réception">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fournisseur</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date Commande</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Articles</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Statut</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {purchases.map(p => (
              <tr key={p.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium text-blue-600">{p.reference}</td>
                <td className="px-4 py-3">{p.supplier?.name || p.supplier_name}</td>
                <td className="px-4 py-3 text-gray-600">{formatDate(p.created_at)}</td>
                <td className="px-4 py-3">{p.items?.length || 0} produits</td>
                <td className="px-4 py-3"><StatusBadge status={p.status} /></td>
                {canEdit && (
                  <td className="px-4 py-3">
                    <button onClick={() => onReceive(p)} className="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                      📥 Réceptionner
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {purchases.length === 0 && <Empty msg="Aucune commande en attente de réception" />}
      </div>
    </Card>
  );
}

function RequestsSection({ requests, approveReq, rejectReq, canEdit }) {
  return (
    <Card title="📨 Demandes de Stock du Magasin Détail" subtitle="Demandes en attente d'approbation">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Demandeur</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Priorité</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date Besoin</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Produits</th>
              {canEdit && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y">
            {requests.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono font-medium">{r.reference}</td>
                <td className="px-4 py-3">{r.requested_by_user?.name || '-'}</td>
                <td className="px-4 py-3"><PriorityBadge priority={r.priority} /></td>
                <td className="px-4 py-3 text-gray-600">{r.needed_by_date ? formatDate(r.needed_by_date) : '-'}</td>
                <td className="px-4 py-3">{r.items?.length || 0} articles</td>
                {canEdit && (
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <button onClick={() => approveReq(r.id)} className="px-3 py-1 bg-green-600 text-white rounded text-sm font-medium hover:bg-green-700">
                        ✓ Approuver
                      </button>
                      <button onClick={() => rejectReq(r.id)} className="px-3 py-1 bg-red-600 text-white rounded text-sm font-medium hover:bg-red-700">
                        ✗ Rejeter
                      </button>
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {requests.length === 0 && <Empty msg="Aucune demande en attente" />}
      </div>
    </Card>
  );
}

function MovementsSection({ movements }) {
  return (
    <Card title="🔄 Mouvements de Stock" subtitle="Historique des entrées et sorties">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Heure</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Produit</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Quantité</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Coût Unit.</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Référence</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {movements.map(m => (
              <tr key={m.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 text-gray-600 text-sm">{formatDateTime(m.created_at)}</td>
                <td className="px-4 py-3"><MovementBadge type={m.type} /></td>
                <td className="px-4 py-3 font-medium">{m.product?.name || '-'}</td>
                <td className="px-4 py-3">
                  <span className={`font-bold ${m.to_warehouse_id ? 'text-green-600' : 'text-red-600'}`}>
                    {m.to_warehouse_id ? '+' : '-'}{m.quantity}
                  </span>
                </td>
                <td className="px-4 py-3">{formatCurrency(m.unit_cost)}</td>
                <td className="px-4 py-3 font-mono text-sm">{m.reference || '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {movements.length === 0 && <Empty msg="Aucun mouvement enregistré" />}
      </div>
    </Card>
  );
}

function StocksSection({ stocks, onExport }) {
  const totalValue = stocks.reduce((sum, s) => sum + (s.quantity || 0) * (s.cost_average || s.unit_cost || 0), 0);
  
  return (
    <Card title="📦 État du Stock - Magasin Gros" subtitle={`${stocks.length} produits en stock`}>
      <div className="flex justify-end mb-4">
        <button onClick={onExport} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
          📥 Exporter Stock CSV
        </button>
      </div>
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
              <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valeur</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {stocks.map(s => (
              <tr key={s.id} className={`hover:bg-gray-50 ${s.quantity < 20 ? 'bg-yellow-50' : ''}`}>
                <td className="px-4 py-3 font-medium">{s.product?.name || '-'}</td>
                <td className="px-4 py-3 font-mono text-gray-500">{s.product?.code || '-'}</td>
                <td className="px-4 py-3 text-right font-bold">{s.quantity}</td>
                <td className="px-4 py-3 text-right text-orange-600">{s.reserved || 0}</td>
                <td className="px-4 py-3 text-right font-bold text-green-600">{s.available}</td>
                <td className="px-4 py-3 text-right">{formatCurrency(s.cost_average || s.unit_cost)}</td>
                <td className="px-4 py-3 text-right font-medium">
                  {formatCurrency((s.quantity || 0) * (s.cost_average || s.unit_cost || 0))}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot className="bg-gray-100 font-bold">
            <tr>
              <td colSpan="6" className="px-4 py-3 text-right">Total Valeur Stock:</td>
              <td className="px-4 py-3 text-right text-lg">{formatCurrency(totalValue)}</td>
            </tr>
          </tfoot>
        </table>
        {stocks.length === 0 && <Empty msg="Aucun stock" />}
      </div>
    </Card>
  );
}

// InventorySection supprimée - remplacée par le lien vers /inventory-enriched
