import React, { useState, useEffect, useCallback } from 'react';
import { ShoppingCart, Trash2, Plus, Save, AlertCircle } from 'lucide-react';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';

// Skeleton pour affichage immédiat
const PageSkeleton = () => (
  <div className="p-6 animate-pulse">
    <div className="h-8 bg-gray-200 rounded w-48 mb-6"></div>
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="lg:col-span-2 bg-white rounded-xl p-6 h-96">
        <div className="h-6 bg-gray-200 rounded w-32 mb-4"></div>
        <div className="space-y-3">
          {[1,2,3,4].map(i => <div key={i} className="h-12 bg-gray-100 rounded"></div>)}
        </div>
      </div>
      <div className="bg-white rounded-xl p-6 h-96">
        <div className="h-6 bg-gray-200 rounded w-24 mb-4"></div>
        <div className="h-48 bg-gray-100 rounded"></div>
      </div>
    </div>
  </div>
);

export default function SalesPage() {
  const { t } = useLanguageStore();
  const [cart, setCart] = useState([]);
  const [products, setProducts] = useState([]);
  const [warehouses, setWarehouses] = useState([]);
  const [selectedWarehouse, setSelectedWarehouse] = useState(null);
  const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '' });
  const [paymentMethod, setPaymentMethod] = useState('especes');
  const [loading, setLoading] = useState({ warehouses: true, products: true });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [uiReady, setUiReady] = useState(false);

  // Afficher l'UI immédiatement
  useEffect(() => {
    setUiReady(true);
  }, []);

  // Chargement parallèle des données
  const fetchWarehouses = useCallback(async () => {
    try {
      const whRes = await apiClient.get('/warehouses');
      setWarehouses(whRes.data.data || []);
      if (whRes.data.data?.length) setSelectedWarehouse(whRes.data.data[0].id);
    } catch (err) {
      console.error('Error fetching warehouses:', err);
    } finally {
      setLoading(prev => ({ ...prev, warehouses: false }));
    }
  }, []);

  const fetchProducts = useCallback(async () => {
    try {
      // Charger les produits avec leur stock disponible
      const [prodRes, stockRes] = await Promise.all([
        apiClient.get('/products?per_page=200&status=active'),
        apiClient.get('/stocks?per_page=200'),
      ]);
      
      const productList = prodRes.data?.data || [];
      const stockList = stockRes.data?.data || [];
      
      // Fusionner les infos de stock avec les produits
      const productsWithStock = productList.map(p => {
        const stock = stockList.find(s => s.product_id === p.id);
        return {
          ...p,
          available: stock?.available || 0,
          quantity: stock?.quantity || 0,
        };
      });
      
      setProducts(productsWithStock);
    } catch (err) {
      console.error('Error fetching products:', err);
      setError(err.message || 'Erreur lors du chargement');
    } finally {
      setLoading(prev => ({ ...prev, products: false }));
    }
  }, []);

  useEffect(() => {
    // Chargement parallèle
    fetchWarehouses();
    fetchProducts();
  }, [fetchWarehouses, fetchProducts]);

  const isLoading = loading.warehouses || loading.products;

  const addToCart = (product) => {
    if (!selectedWarehouse) {
      setError('Select a warehouse first');
      return;
    }

    // Check if product already in cart
    const existingItem = cart.find(item => item.product_id === product.id && item.warehouse_id === selectedWarehouse);
    if (existingItem) {
      if (existingItem.quantity < product.quantity) {
        existingItem.quantity += 1;
        setCart([...cart]);
      } else {
        setError('Not enough stock');
      }
    } else {
      cart.push({
        product_id: product.id,
        product_name: product.name,
        warehouse_id: selectedWarehouse,
        quantity: 1,
        unit_price: product.selling_price || product.cost_average || 0,
        total: product.selling_price || product.cost_average || 0,
        available: product.quantity || product.available || 999
      });
      setCart([...cart]);
    }
    setError(null);
  };

  const updateQuantity = (index, newQty) => {
    if (newQty > 0 && newQty <= cart[index].available) {
      cart[index].quantity = newQty;
      cart[index].total = newQty * cart[index].unit_price;
      setCart([...cart]);
    }
  };

  const removeFromCart = (index) => {
    cart.splice(index, 1);
    setCart([...cart]);
  };

  const calculateTotals = () => {
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * 0.18; // 18% VAT
    const total = subtotal + tax;
    return { subtotal, tax, total };
  };

  const completeSale = async () => {
    if (cart.length === 0) {
      setError('Le panier est vide');
      return;
    }

    try {
      setLoading(prev => ({ ...prev, products: true }));
      
      const payload = {
        customer_name: customerInfo.name || 'Client comptoir',
        customer_phone: customerInfo.phone,
        warehouse_id: selectedWarehouse,
        mode: 'manual',
        payment_method: paymentMethod,
        items: cart.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity,
          unit_price: item.unit_price
        })),
      };

      const saleRes = await apiClient.post('/sales', payload);
      const saleId = saleRes.data.id;
      
      // Compléter la vente
      await apiClient.post(`/sales/${saleId}/complete`, {
        amount_paid: calculateTotals().total,
        payment_method: paymentMethod,
      });

      setSuccess(`Vente complétée! Total: ${calculateTotals().total.toLocaleString()} FCFA`);
      setCart([]);
      setCustomerInfo({ name: '', phone: '' });
      fetchProducts(); // Rafraîchir les stocks
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      console.error('Error completing sale:', err);
      setError(err.response?.data?.message || 'Erreur lors de la vente');
    } finally {
      setLoading(prev => ({ ...prev, products: false }));
    }
  };

  const { subtotal, tax, total } = calculateTotals();

  if (loading && products.length === 0) return <div className="p-8">Loading...</div>;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-white mb-6 flex items-center gap-2">
          <ShoppingCart className="w-8 h-8 text-green-400" />
          POS - Sales
        </h1>

        {error && (
          <div className="mb-4 bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded-lg flex items-center gap-2">
            <AlertCircle size={20} /> {error}
          </div>
        )}

        {success && (
          <div className="mb-4 bg-green-900 border border-green-700 text-green-100 px-4 py-3 rounded-lg">
            ✓ {success}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Products Section */}
          <div className="lg:col-span-2">
            <div className="bg-slate-800 rounded-lg border border-slate-700 p-6 mb-6">
              <h2 className="text-lg font-bold text-white mb-4">Warehouse</h2>
              <select
                value={selectedWarehouse || ''}
                onChange={(e) => setSelectedWarehouse(Number(e.target.value))}
                className="w-full px-4 py-2 bg-slate-700 text-white border border-slate-600 rounded-lg focus:border-green-500 outline-none"
              >
                {warehouses.map(wh => (
                  <option key={wh.id} value={wh.id}>
                    {wh.name} ({wh.type})
                  </option>
                ))}
              </select>
            </div>

            <div className="bg-slate-800 rounded-lg border border-slate-700 p-6">
              <h2 className="text-lg font-bold text-white mb-4">Products</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {products.filter(p => !selectedWarehouse || p.warehouse_id === selectedWarehouse).map(prod => (
                  <div key={prod.id} className="bg-slate-700 p-4 rounded-lg hover:bg-slate-600 transition">
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <p className="text-white font-medium">{prod.product?.name}</p>
                        <p className="text-slate-400 text-sm">Stock: {prod.quantity} units</p>
                      </div>
                      <p className="text-green-400 font-bold">{prod.cost_average?.toLocaleString()} FCFA</p>
                    </div>
                    <button
                      onClick={() => addToCart(prod)}
                      disabled={prod.quantity === 0}
                      className="w-full mt-2 px-3 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded transition flex items-center justify-center gap-2"
                    >
                      <Plus size={16} /> Add to Cart
                    </button>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Cart & Checkout */}
          <div className="bg-slate-800 rounded-lg border border-slate-700 p-6">
            <h2 className="text-lg font-bold text-white mb-4">Shopping Cart</h2>

            {/* Cart Items */}
            <div className="bg-slate-700 rounded p-4 mb-4 max-h-64 overflow-y-auto">
              {cart.length > 0 ? (
                cart.map((item, idx) => (
                  <div key={idx} className="bg-slate-600 p-3 rounded mb-2">
                    <div className="flex justify-between items-start mb-2">
                      <p className="text-white text-sm font-medium">{item.product_name}</p>
                      <button
                        onClick={() => removeFromCart(idx)}
                        className="text-red-400 hover:text-red-300"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => updateQuantity(idx, item.quantity - 1)}
                        className="px-2 py-1 bg-slate-500 text-white rounded text-sm hover:bg-slate-400"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        value={item.quantity}
                        onChange={(e) => updateQuantity(idx, Number(e.target.value))}
                        className="w-12 text-center bg-slate-500 text-white rounded text-sm"
                      />
                      <button
                        onClick={() => updateQuantity(idx, item.quantity + 1)}
                        className="px-2 py-1 bg-slate-500 text-white rounded text-sm hover:bg-slate-400"
                      >
                        +
                      </button>
                    </div>
                    <p className="text-right text-green-400 text-sm mt-2">
                      {item.total.toLocaleString()} FCFA
                    </p>
                  </div>
                ))
              ) : (
                <p className="text-slate-400 text-sm">Cart is empty</p>
              )}
            </div>

            {/* Totals */}
            <div className="bg-slate-700 rounded p-4 mb-4 space-y-2 border border-slate-600">
              <div className="flex justify-between text-slate-300 text-sm">
                <span>Subtotal:</span>
                <span>{subtotal.toLocaleString()} FCFA</span>
              </div>
              <div className="flex justify-between text-slate-300 text-sm">
                <span>Tax (18%):</span>
                <span>{Math.round(tax).toLocaleString()} FCFA</span>
              </div>
              <div className="flex justify-between text-white font-bold border-t border-slate-600 pt-2">
                <span>Total:</span>
                <span className="text-green-400">{Math.round(total).toLocaleString()} FCFA</span>
              </div>
            </div>

            {/* Customer Info */}
            <div className="mb-4 space-y-2">
              <input
                type="text"
                placeholder="Customer Name"
                value={customerInfo.name}
                onChange={(e) => setCustomerInfo({ ...customerInfo, name: e.target.value })}
                className="w-full px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm placeholder-slate-400 focus:border-green-500 outline-none"
              />
              <input
                type="tel"
                placeholder="Phone (optional)"
                value={customerInfo.phone}
                onChange={(e) => setCustomerInfo({ ...customerInfo, phone: e.target.value })}
                className="w-full px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm placeholder-slate-400 focus:border-green-500 outline-none"
              />
            </div>

            {/* Payment Method */}
            <div className="mb-4">
              <label className="text-white text-sm mb-2 block">Payment Method</label>
              <select
                value={paymentMethod}
                onChange={(e) => setPaymentMethod(e.target.value)}
                className="w-full px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm focus:border-green-500 outline-none"
              >
                <option value="especes">💵 Espèces</option>
                <option value="cheque">📋 Chèque</option>
                <option value="virement">🏦 Virement</option>
                <option value="credit_card">💳 Carte Crédit</option>
                <option value="kkiapay">📱 KkiaPay</option>
                <option value="fedapay">📱 FedaPay</option>
              </select>
            </div>

            {/* Checkout Button */}
            <button
              onClick={completeSale}
              disabled={cart.length === 0 || loading}
              className="w-full px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg font-bold flex items-center justify-center gap-2 transition"
            >
              <Save size={20} />
              {loading ? 'Processing...' : 'Complete Sale'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
