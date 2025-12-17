import React, { useState, useEffect, useCallback, useMemo, memo, useRef } from 'react';
import { Package, Plus, Search, RefreshCw, Edit2, Trash2 } from 'lucide-react';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import ExportButton from '../components/ExportButton';
import ConfirmModal from '../components/ConfirmModal';
import EmptyState from '../components/EmptyState';
import { useToast } from '../components/Toast';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';
import { ListPageSkeleton, TableSkeleton } from '../components/Skeleton';

export default function ProductsPage() {
  const { tenant } = useTenantStore();
  const { t } = useLanguageStore();
  const { toast } = useToast();
  const { get: getCache, set: setCache, invalidate } = useCacheStore();
  const fetchedRef = useRef(false);
  
  // Initialiser avec le cache pour affichage instantané
  const cachedProducts = getCache(CACHE_KEYS.PRODUCTS);
  const [products, setProducts] = useState(cachedProducts || []);
  const [tableLoading, setTableLoading] = useState(!cachedProducts);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');
  const [deleteModal, setDeleteModal] = useState({ open: false, product: null });
  const [deleting, setDeleting] = useState(false);
  const [formData, setFormData] = useState({
    code: '',
    name: '',
    description: '',
    category: '',
    purchase_price: '',
    selling_price: '',
    unit: 'pcs',
    min_stock: 10,
    max_stock: 100,
    tax_percent: 18,
  });

  // Debounce search pour éviter trop de requêtes
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const fetchProducts = useCallback(async (force = false) => {
    const currentCachedProducts = getCache(CACHE_KEYS.PRODUCTS);
    // Si on a le cache et pas de recherche et pas de force, utiliser le cache
    if (!force && currentCachedProducts && currentCachedProducts.length > 0 && !debouncedSearch) {
      setProducts(currentCachedProducts);
      setTableLoading(false);
      return;
    }
    
    setTableLoading(true);
    try {
      const params = new URLSearchParams();
      if (debouncedSearch) params.append('search', debouncedSearch);
      params.append('per_page', '100');
      const response = await apiClient.get(`/products?${params}`);
      const data = response.data.data || response.data || [];
      setProducts(data);
      
      // Mettre en cache seulement si pas de recherche
      if (!debouncedSearch) {
        setCache(CACHE_KEYS.PRODUCTS, data);
      }
    } catch (error) {
      console.error('Error fetching products:', error);
      if (!cachedProducts || cachedProducts.length === 0) setProducts([]);
    } finally {
      setTableLoading(false);
    }
  }, [debouncedSearch, cachedProducts, getCache, setCache]);

  useEffect(() => {
    if (!fetchedRef.current || debouncedSearch) {
      fetchedRef.current = true;
      fetchProducts();
    }
  }, [fetchProducts, debouncedSearch]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      // Convert price fields to numbers
      const submitData = {
        ...formData,
        purchase_price: parseFloat(formData.purchase_price),
        selling_price: parseFloat(formData.selling_price),
        min_stock: parseInt(formData.min_stock) || 0,
        max_stock: parseInt(formData.max_stock) || 0,
        tax_percent: parseFloat(formData.tax_percent) || 0,
      };

      if (editing) {
        await apiClient.put(`/products/${editing.id}`, submitData);
      } else {
        await apiClient.post('/products', submitData);
      }
      setShowForm(false);
      setEditing(null);
      setFormData({
        code: '',
        name: '',
        description: '',
        category: '',
        purchase_price: '',
        selling_price: '',
        unit: 'pcs',
        min_stock: 10,
        max_stock: 100,
        tax_percent: 18,
      });
      invalidate(CACHE_KEYS.PRODUCTS);
      fetchProducts(true);
    } catch (error) {
      console.error('Error saving product:', error);
      const message = error.response?.data?.message || 
                     error.response?.data?.error || 
                     error.message || 
                     'Erreur lors de la sauvegarde';
      setError(message);
    }
  };

  const handleEdit = (product) => {
    setEditing(product);
    setFormData(product);
    setShowForm(true);
  };

  const handleDeleteClick = (product) => {
    setDeleteModal({ open: true, product });
  };

  const handleDeleteConfirm = async () => {
    if (!deleteModal.product) return;
    setDeleting(true);
    try {
      await apiClient.delete(`/products/${deleteModal.product.id}`);
      toast.success(`Produit "${deleteModal.product.name}" supprimé`);
      invalidate(CACHE_KEYS.PRODUCTS);
      fetchProducts(true);
      setDeleteModal({ open: false, product: null });
    } catch (error) {
      console.error('Error deleting product:', error);
      toast.error(error.response?.data?.message || 'Erreur lors de la suppression');
    } finally {
      setDeleting(false);
    }
  };

  const handleCancel = () => {
    setShowForm(false);
    setEditing(null);
    setError('');
    setFormData({
      code: '',
      name: '',
      description: '',
      category: '',
      purchase_price: '',
      selling_price: '',
      unit: 'pcs',
      min_stock: 10,
      max_stock: 100,
      tax_percent: 18,
    });
  };

  // Données pour l'export
  const exportData = products.map(p => ({
    code: p.code,
    nom: p.name,
    categorie: p.category || 'N/A',
    prix_achat: p.purchase_price || 0,
    prix_vente: p.selling_price || 0,
    unite: p.unit,
    stock_min: p.min_stock,
    tva: p.tax_percent
  }));

  const exportColumns = [
    { key: 'code', header: 'Code' },
    { key: 'nom', header: 'Nom' },
    { key: 'categorie', header: 'Catégorie' },
    { key: 'prix_achat', header: 'Prix Achat', type: 'currency' },
    { key: 'prix_vente', header: 'Prix Vente', type: 'currency' },
    { key: 'unite', header: 'Unité' },
    { key: 'stock_min', header: 'Stock Min', type: 'number' },
    { key: 'tva', header: 'TVA %', type: 'number' }
  ];

  return (
    <div className="p-4 sm:p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <Package className="text-brand-600" size={28} />
            Produits
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{products.length} produits</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchProducts}
            disabled={tableLoading}
            className="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          >
            <RefreshCw size={18} className={`text-gray-600 dark:text-gray-300 ${tableLoading ? 'animate-spin' : ''}`} />
          </button>
          <ExportButton
            data={exportData}
            columns={exportColumns}
            filename="produits"
            title="Liste des Produits"
            subtitle={`${products.length} articles`}
            variant="secondary"
          />
          <button
            onClick={() => setShowForm(!showForm)}
            className="px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium flex items-center gap-2 transition"
          >
            <Plus size={18} />
            {showForm ? 'Annuler' : 'Nouveau'}
          </button>
        </div>
      </div>

      {showForm && (
        <div className="bg-white p-6 rounded-lg shadow">
          <h2 className="text-xl font-bold mb-4">
            {editing ? 'Éditer Produit' : 'Ajouter Produit'}
          </h2>
          {error && (
            <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
              {error}
            </div>
          )}
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
            <input
              type="text"
              placeholder="Code produit"
              value={formData.code}
              onChange={(e) => setFormData({...formData, code: e.target.value})}
              className="border rounded px-3 py-2"
              required
            />
            <input
              type="text"
              placeholder="Nom"
              value={formData.name}
              onChange={(e) => setFormData({...formData, name: e.target.value})}
              className="border rounded px-3 py-2"
              required
            />
            <textarea
              placeholder="Description"
              value={formData.description}
              onChange={(e) => setFormData({...formData, description: e.target.value})}
              className="border rounded px-3 py-2 col-span-2"
            />
            <input
              type="text"
              placeholder="Catégorie"
              value={formData.category}
              onChange={(e) => setFormData({...formData, category: e.target.value})}
              className="border rounded px-3 py-2"
            />
            <select
              value={formData.unit}
              onChange={(e) => setFormData({...formData, unit: e.target.value})}
              className="border rounded px-3 py-2"
            >
              <option>pcs</option>
              <option>kg</option>
              <option>l</option>
              <option>m</option>
            </select>
            <input
              type="number"
              placeholder="Prix d'achat"
              value={formData.purchase_price}
              onChange={(e) => setFormData({...formData, purchase_price: e.target.value})}
              className="border rounded px-3 py-2"
              step="0.01"
              required
            />
            <input
              type="number"
              placeholder="Prix de vente"
              value={formData.selling_price}
              onChange={(e) => setFormData({...formData, selling_price: e.target.value})}
              className="border rounded px-3 py-2"
              step="0.01"
              required
            />
            <input
              type="number"
              placeholder="Stock minimum"
              value={formData.min_stock}
              onChange={(e) => setFormData({...formData, min_stock: e.target.value})}
              className="border rounded px-3 py-2"
            />
            <input
              type="number"
              placeholder="Stock maximum"
              value={formData.max_stock}
              onChange={(e) => setFormData({...formData, max_stock: e.target.value})}
              className="border rounded px-3 py-2"
            />
            <div className="col-span-2 flex gap-2">
              <button
                type="submit"
                className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex-1"
              >
                {editing ? 'Mettre à jour' : 'Ajouter'}
              </button>
              <button
                type="button"
                onClick={handleCancel}
                className="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded flex-1"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="bg-white p-6 rounded-lg shadow">
        <input
          type="text"
          placeholder="Rechercher produit..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full border rounded px-3 py-2 mb-4"
        />

        {tableLoading ? (
          <TableSkeleton />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100">
                <tr>
                  <th className="px-4 py-2 text-left">Nom</th>
                  <th className="px-4 py-2 text-left">Code</th>
                  <th className="px-4 py-2 text-right">Prix Achat</th>
                  <th className="px-4 py-2 text-right">Prix Vente</th>
                  <th className="px-4 py-2 text-center">Catégorie</th>
                  <th className="px-4 py-2 text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.length === 0 ? (
                  <tr><td colSpan="6" className="px-4 py-8 text-center text-gray-500">Aucun produit</td></tr>
                ) : products.map((product) => (
                  <tr key={product.id} className="border-b hover:bg-gray-50">
                    <td className="px-4 py-2">{product.name}</td>
                    <td className="px-4 py-2">{product.code}</td>
                    <td className="px-4 py-2 text-right">{parseFloat(product.purchase_price || 0).toFixed(2)}</td>
                    <td className="px-4 py-2 text-right">{parseFloat(product.selling_price || 0).toFixed(2)}</td>
                    <td className="px-4 py-2 text-center">{product.category}</td>
                    <td className="px-4 py-2 text-center space-x-2">
                      <button onClick={() => handleEdit(product)} className="text-blue-600 hover:text-blue-800">Éditer</button>
                      <button onClick={() => handleDeleteClick(product)} className="text-red-600 hover:text-red-800">Supprimer</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal de confirmation de suppression */}
      <ConfirmModal
        isOpen={deleteModal.open}
        onClose={() => setDeleteModal({ open: false, product: null })}
        onConfirm={handleDeleteConfirm}
        title="Supprimer le produit"
        message={`Êtes-vous sûr de vouloir supprimer "${deleteModal.product?.name}" ? Cette action est irréversible.`}
        confirmText="Supprimer"
        cancelText="Annuler"
        variant="danger"
        loading={deleting}
      />
    </div>
  );
}
