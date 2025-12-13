import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';
import { usePrint } from '../hooks/usePrint';
import { usePayment } from '../hooks/usePayment';
import PaymentModal from '../components/PaymentModal';
import { ShoppingCart, Trash2, Plus, Minus, CreditCard, Banknote, Smartphone, Search, X, Printer, FileText, Receipt } from 'lucide-react';

// Skeleton pour affichage immédiat du POS
const POSSkeleton = () => (
  <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 p-4 animate-pulse">
    <div className="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-4">
      <div className="h-10 bg-gray-200 dark:bg-slate-700 rounded mb-4"></div>
      <div className="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-2">
        {[1,2,3,4,5,6,7,8].map(i => (
          <div key={i} className="h-16 bg-gray-100 dark:bg-slate-700 rounded"></div>
        ))}
      </div>
    </div>
    <div className="bg-white dark:bg-slate-800 rounded-xl p-4">
      <div className="h-6 bg-gray-200 dark:bg-slate-700 rounded w-24 mb-4"></div>
      <div className="h-48 bg-gray-100 dark:bg-slate-700 rounded"></div>
    </div>
  </div>
);

export default function POSPage() {
  const { user, tenant } = useTenantStore();
  const { t } = useLanguageStore();
  const { get: getCache, set: setCache } = useCacheStore();
  const { printReceipt, generateReceipt } = usePrint();
  const { availableMethods } = usePayment();
  
  // Initialiser avec les données du cache pour affichage instantané
  const [products, setProducts] = useState(() => getCache(CACHE_KEYS.PRODUCTS_ACTIVE) || []);
  const [cartItems, setCartItems] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(!getCache(CACHE_KEYS.PRODUCTS_ACTIVE));
  const [submitting, setSubmitting] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [tableNumber, setTableNumber] = useState('');
  const [notes, setNotes] = useState('');
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [lastOrder, setLastOrder] = useState(null);
  // Serveur = toujours mode manuel, autres rôles = direct par défaut
  const isServer = user?.role === 'pos_server';
  const [orderMode, setOrderMode] = useState(isServer ? 'manuel' : 'direct');
  const fetchedRef = useRef(false);
  
  // POS assignés au serveur
  const [assignedPosList, setAssignedPosList] = useState([]);
  const [selectedPosId, setSelectedPosId] = useState(user?.assigned_pos_id || null);
  
  // Charger les POS assignés au serveur
  useEffect(() => {
    const fetchAssignedPos = async () => {
      try {
        // Si l'utilisateur a des POS affiliés, les charger
        if (user?.affiliated_pos?.length > 0) {
          setAssignedPosList(user.affiliated_pos);
          if (!selectedPosId && user.affiliated_pos[0]) {
            setSelectedPosId(user.affiliated_pos[0].id);
          }
        } else {
          // Sinon charger tous les POS du tenant (pour owner/manager)
          const res = await apiClient.get('/tenant-config/pos');
          const posList = res.data?.data || [];
          setAssignedPosList(posList);
          if (!selectedPosId && posList[0]) {
            setSelectedPosId(posList[0].id);
          }
        }
      } catch (err) {
        console.error('Error fetching POS:', err);
      }
    };
    fetchAssignedPos();
  }, [user, selectedPosId]);

  const fetchProducts = useCallback(async (force = false) => {
    // Éviter les requêtes dupliquées
    if (fetchedRef.current && !force) return;
    fetchedRef.current = true;
    
    // Vérifier le cache d'abord
    const cached = getCache(CACHE_KEYS.PRODUCTS_ACTIVE);
    if (cached && cached.length > 0 && !force) {
      setProducts(cached);
      setLoading(false);
      return;
    }
    
    try {
      const response = await apiClient.get('/products?per_page=500&status=active');
      const productList = response.data?.data || response.data || [];
      const validProducts = Array.isArray(productList) ? productList : [];
      
      setProducts(validProducts);
      setCache(CACHE_KEYS.PRODUCTS_ACTIVE, validProducts);
    } catch (error) {
      console.error('Error fetching products:', error);
      // Utiliser le cache même en cas d'erreur
      if (!products.length) setProducts([]);
    } finally {
      setLoading(false);
    }
  }, [getCache, setCache, products.length]);

  useEffect(() => {
    fetchProducts();
  }, [fetchProducts]);

  // Filtrage local pour éviter les requêtes
  const filteredProducts = useMemo(() => {
    if (!searchTerm) return products;
    const term = searchTerm.toLowerCase();
    return products.filter(p => 
      p.name?.toLowerCase().includes(term) || 
      p.code?.toLowerCase().includes(term)
    );
  }, [products, searchTerm]);

  const handleAddToCart = (product) => {
    const existing = cartItems.find(item => item.product_id === product.id);
    
    if (existing) {
      setCartItems(cartItems.map(item =>
        item.product_id === product.id
          ? { ...item, quantity: item.quantity + 1 }
          : item
      ));
    } else {
      setCartItems([...cartItems, {
        product_id: product.id,
        product,
        quantity: 1,
        unit_price: product.selling_price,
      }]);
    }
  };

  const handleRemoveFromCart = (product_id) => {
    setCartItems(cartItems.filter(item => item.product_id !== product_id));
  };

  const handleQuantityChange = (product_id, quantity) => {
    if (quantity <= 0) {
      handleRemoveFromCart(product_id);
    } else {
      setCartItems(cartItems.map(item =>
        item.product_id === product_id
          ? { ...item, quantity }
          : item
      ));
    }
  };

  const calculateTotals = () => {
    const subtotal = cartItems.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
    const tax = subtotal * 0.18; // 18% TAX
    const total = subtotal + tax;

    return { subtotal, tax, total };
  };

  // Soumission en une seule étape
  const handleSubmitOrder = async () => {
    if (cartItems.length === 0) {
      alert(t('pos.emptyCart'));
      return;
    }

    setSubmitting(true);
    try {
      const response = await apiClient.post('/pos/orders', {
        pos_id: selectedPosId || assignedPosList[0]?.id || 1,
        table_number: tableNumber || null,
        items: cartItems.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity,
          unit_price: item.unit_price,
        })),
        notes: notes || null,
        payment_method: paymentMethod,
        order_mode: orderMode,
      });

      const order = response.data?.data || response.data;
      setLastOrder({
        ...order,
        items: cartItems,
        subtotal,
        tax,
        total,
        table_number: tableNumber,
      });

      // Si mode direct, ouvrir le modal de paiement
      if (orderMode === 'direct') {
        setShowPaymentModal(true);
      } else {
        // Mode manuel - juste confirmer
        alert(t('pos.orderSuccess'));
        setCartItems([]);
        setTableNumber('');
        setNotes('');
      }
    } catch (error) {
      console.error('Error:', error);
      alert(error.response?.data?.message || t('pos.orderError'));
    } finally {
      setSubmitting(false);
    }
  };

  // Impression ticket/facturette
  const handlePrintTicket = (type) => {
    if (cartItems.length === 0) return;
    
    const orderData = {
      reference: `TMP-${Date.now()}`,
      items: cartItems,
      subtotal,
      tax_amount: tax,
      total,
      table_number: tableNumber,
      payment_method: paymentMethod,
      created_at: new Date().toISOString(),
    };

    if (type === 'facturette') {
      // Facturette = ticket simplifié avec TVA
      printReceipt(orderData, { showTax: true, title: 'FACTURETTE' });
    } else {
      // Ticket simple
      printReceipt(orderData, { showTax: false, title: 'TICKET DE CAISSE' });
    }
  };

  const { subtotal, tax, total } = calculateTotals();

  if (loading) return <POSSkeleton />;

  return (
    <div className="p-4 md:p-6">
      {/* Header compact */}
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl md:text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <ShoppingCart className="text-orange-500" size={24} />
          {t('pos.title')}
        </h1>
        
        {/* Sélecteur de POS - visible si plusieurs POS assignés */}
        {assignedPosList.length > 1 && (
          <select
            value={selectedPosId || ''}
            onChange={(e) => setSelectedPosId(e.target.value)}
            className="px-3 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium focus:ring-2 focus:ring-orange-500"
          >
            {assignedPosList.map(pos => (
              <option key={pos.id} value={pos.id}>
                📍 {pos.name}
              </option>
            ))}
          </select>
        )}
        
        {/* Afficher le POS actuel si un seul */}
        {assignedPosList.length === 1 && (
          <span className="px-3 py-2 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 rounded-lg text-sm font-medium">
            📍 {assignedPosList[0]?.name}
          </span>
        )}
        
        <div className="text-sm text-gray-500 dark:text-gray-400">
          {filteredProducts.length} {t('pos.products').toLowerCase()}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Section Produits - 2/3 */}
        <div className="lg:col-span-2">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4">
            {/* Recherche */}
            <div className="relative mb-4">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
              <input
                type="text"
                placeholder={t('pos.searchProducts')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500/50"
              />
              {searchTerm && (
                <button onClick={() => setSearchTerm('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <X size={16} />
                </button>
              )}
            </div>

            {/* Grille de produits compacte */}
            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2 max-h-[60vh] overflow-y-auto pr-1">
              {filteredProducts.map(product => (
                <button
                  key={product.id}
                  onClick={() => handleAddToCart(product)}
                  className="group bg-gray-50 dark:bg-slate-700 hover:bg-orange-50 dark:hover:bg-orange-900/20 border border-gray-200 dark:border-slate-600 hover:border-orange-400 rounded-lg p-2 transition-all text-left"
                >
                  <p className="font-medium text-gray-800 dark:text-white text-xs leading-tight line-clamp-2 mb-1 group-hover:text-orange-600 dark:group-hover:text-orange-400">
                    {product.name}
                  </p>
                  <p className="text-orange-600 dark:text-orange-400 font-bold text-sm">
                    {Number(product.selling_price).toLocaleString()} {tenant?.currency || 'XOF'}
                  </p>
                </button>
              ))}
              {filteredProducts.length === 0 && (
                <div className="col-span-full text-center py-8 text-gray-500">
                  {t('pos.noProducts')}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Section Panier - 1/3 */}
        <div className="lg:col-span-1">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 sticky top-4">
            <h2 className="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
              <ShoppingCart size={18} className="text-orange-500" />
              {t('pos.cart')} ({cartItems.length})
            </h2>

            {/* Table & Notes */}
            <div className="grid grid-cols-2 gap-2 mb-3">
              <input
                type="text"
                placeholder={t('pos.table')}
                value={tableNumber}
                onChange={(e) => setTableNumber(e.target.value)}
                className="px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm"
              />
              <input
                type="text"
                placeholder={t('pos.notes')}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm"
              />
            </div>

            {/* Articles du panier */}
            <div className="max-h-48 overflow-y-auto mb-3 space-y-2">
              {cartItems.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-6">{t('pos.emptyCart')}</p>
              ) : (
                cartItems.map(item => (
                  <div key={item.product_id} className="flex items-center gap-2 bg-gray-50 dark:bg-slate-700 rounded-lg p-2">
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-800 dark:text-white truncate">{item.product.name}</p>
                      <p className="text-xs text-orange-600">{Number(item.unit_price).toLocaleString()} × {item.quantity}</p>
                    </div>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => handleQuantityChange(item.product_id, item.quantity - 1)}
                        className="w-6 h-6 rounded bg-gray-200 dark:bg-slate-600 flex items-center justify-center hover:bg-gray-300"
                      >
                        <Minus size={12} />
                      </button>
                      <span className="w-6 text-center text-sm font-medium">{item.quantity}</span>
                      <button
                        onClick={() => handleQuantityChange(item.product_id, item.quantity + 1)}
                        className="w-6 h-6 rounded bg-gray-200 dark:bg-slate-600 flex items-center justify-center hover:bg-gray-300"
                      >
                        <Plus size={12} />
                      </button>
                      <button
                        onClick={() => handleRemoveFromCart(item.product_id)}
                        className="w-6 h-6 rounded bg-red-100 dark:bg-red-900/30 text-red-500 flex items-center justify-center hover:bg-red-200 ml-1"
                      >
                        <Trash2 size={12} />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Totaux */}
            <div className="border-t border-gray-200 dark:border-slate-600 pt-3 mb-3 space-y-1">
              <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>{t('pos.subtotal')}</span>
                <span>{subtotal.toLocaleString()} {tenant?.currency || 'XOF'}</span>
              </div>
              <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>{t('pos.tax')} (18%)</span>
                <span>{tax.toLocaleString()} {tenant?.currency || 'XOF'}</span>
              </div>
              <div className="flex justify-between text-lg font-bold text-gray-800 dark:text-white pt-1">
                <span>{t('pos.total')}</span>
                <span className="text-orange-600">{total.toLocaleString()} {tenant?.currency || 'XOF'}</span>
              </div>
            </div>

            {/* Mode de paiement */}
            <div className="mb-3">
              <div className="grid grid-cols-3 gap-1">
                <button
                  onClick={() => setPaymentMethod('cash')}
                  className={`flex flex-col items-center gap-1 p-2 rounded-lg border text-xs transition ${
                    paymentMethod === 'cash' 
                      ? 'bg-orange-100 dark:bg-orange-900/30 border-orange-400 text-orange-700 dark:text-orange-400' 
                      : 'bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600'
                  }`}
                >
                  <Banknote size={16} />
                  {t('pos.cash')}
                </button>
                <button
                  onClick={() => setPaymentMethod('card')}
                  className={`flex flex-col items-center gap-1 p-2 rounded-lg border text-xs transition ${
                    paymentMethod === 'card' 
                      ? 'bg-orange-100 dark:bg-orange-900/30 border-orange-400 text-orange-700 dark:text-orange-400' 
                      : 'bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600'
                  }`}
                >
                  <CreditCard size={16} />
                  {t('pos.card')}
                </button>
                <button
                  onClick={() => setPaymentMethod('mobile')}
                  className={`flex flex-col items-center gap-1 p-2 rounded-lg border text-xs transition ${
                    paymentMethod === 'mobile' 
                      ? 'bg-orange-100 dark:bg-orange-900/30 border-orange-400 text-orange-700 dark:text-orange-400' 
                      : 'bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600'
                  }`}
                >
                  <Smartphone size={16} />
                  {t('pos.mobile')}
                </button>
              </div>
            </div>

            {/* Mode de commande - Direct uniquement pour gérant/caissier */}
            {['owner', 'admin', 'manager', 'caissier'].includes(user?.role) ? (
              <div className="mb-3">
                <p className="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Mode de commande</p>
                <div className="grid grid-cols-2 gap-1">
                  <button
                    onClick={() => setOrderMode('direct')}
                    className={`flex items-center justify-center gap-1 p-2 rounded-lg border text-xs transition ${
                      orderMode === 'direct' 
                        ? 'bg-blue-100 dark:bg-blue-900/30 border-blue-400 text-blue-700 dark:text-blue-400' 
                        : 'bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600'
                    }`}
                  >
                    <Receipt size={14} />
                    Direct
                  </button>
                  <button
                    onClick={() => setOrderMode('manuel')}
                    className={`flex items-center justify-center gap-1 p-2 rounded-lg border text-xs transition ${
                      orderMode === 'manuel' 
                        ? 'bg-purple-100 dark:bg-purple-900/30 border-purple-400 text-purple-700 dark:text-purple-400' 
                        : 'bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600'
                    }`}
                  >
                    <FileText size={14} />
                    Manuel
                  </button>
                </div>
              </div>
            ) : (
              <div className="mb-3 p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                <p className="text-xs text-purple-700 dark:text-purple-400 flex items-center gap-1">
                  <FileText size={14} />
                  Mode Manuel - Le gérant validera le paiement
                </p>
              </div>
            )}

            {/* Boutons d'action */}
            <div className="space-y-2">
              <div className="flex gap-2">
                <button
                  onClick={() => setCartItems([])}
                  disabled={cartItems.length === 0}
                  className="flex-1 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 py-2.5 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-slate-600 transition text-sm disabled:opacity-50"
                >
                  {t('action.clear')}
                </button>
                <button
                  onClick={handleSubmitOrder}
                  disabled={cartItems.length === 0 || submitting}
                  className="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-2.5 rounded-lg font-medium transition text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? t('msg.loading') : t('pos.checkout')}
                </button>
              </div>
              
              {/* Boutons d'impression */}
              <div className="flex gap-2">
                <button
                  onClick={() => handlePrintTicket('facturette')}
                  disabled={cartItems.length === 0}
                  className="flex-1 flex items-center justify-center gap-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-medium transition text-xs disabled:opacity-50"
                >
                  <Receipt size={14} />
                  Facturette
                </button>
                <button
                  onClick={() => handlePrintTicket('ticket')}
                  disabled={cartItems.length === 0}
                  className="flex-1 flex items-center justify-center gap-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition text-xs disabled:opacity-50"
                >
                  <Printer size={14} />
                  Ticket
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal de paiement */}
      <PaymentModal
        isOpen={showPaymentModal}
        onClose={() => setShowPaymentModal(false)}
        order={lastOrder}
        onPaymentComplete={(result) => {
          setShowPaymentModal(false);
          if (result.success) {
            // Imprimer le ticket après paiement
            printReceipt(lastOrder);
            setCartItems([]);
            setLastOrder(null);
          }
        }}
      />
    </div>
  );
}
