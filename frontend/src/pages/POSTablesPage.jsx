import { useState, useEffect, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { Users, Plus, Edit2, Trash2, Check } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse grid grid-cols-2 md:grid-cols-4 gap-4">
    {[1,2,3,4,5,6,7,8].map(i => (
      <div key={i} className="h-32 bg-gray-200 rounded-xl"></div>
    ))}
  </div>
));

/**
 * Page de gestion des tables POS
 */
export default function POSTablesPage() {
  const navigate = useNavigate();
  const [tables, setTables] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingTable, setEditingTable] = useState(null);
  const [form, setForm] = useState({ number: '', capacity: 4, zone: '' });

  useEffect(() => {
    fetchTables();
    // Rafraîchir toutes les 15 secondes
    const interval = setInterval(fetchTables, 15000);
    return () => clearInterval(interval);
  }, []);

  const fetchTables = async () => {
    try {
      const res = await apiClient.get('/pos/tables');
      setTables(res.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSaveTable = async (e) => {
    e.preventDefault();
    try {
      if (editingTable) {
        await apiClient.put(`/pos/tables/${editingTable.id}`, form);
      } else {
        await apiClient.post('/pos/tables', form);
      }
      fetchTables();
      setShowModal(false);
      setEditingTable(null);
      setForm({ number: '', capacity: 4, zone: '' });
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleDeleteTable = async (id) => {
    if (!confirm('Supprimer cette table?')) return;
    try {
      await apiClient.delete(`/pos/tables/${id}`);
      fetchTables();
    } catch (error) {
      alert('Erreur lors de la suppression');
    }
  };

  const handleSelectTable = (table) => {
    if (table.status === 'available') {
      navigate(`/pos?table=${table.number}`);
    } else if (table.current_order_id) {
      navigate(`/pos/order/${table.current_order_id}`);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'available': return 'bg-green-100 border-green-400 text-green-700';
      case 'occupied': return 'bg-red-100 border-red-400 text-red-700';
      case 'reserved': return 'bg-yellow-100 border-yellow-400 text-yellow-700';
      case 'cleaning': return 'bg-blue-100 border-blue-400 text-blue-700';
      default: return 'bg-gray-100 border-gray-400 text-gray-700';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'available': return 'Libre';
      case 'occupied': return 'Occupée';
      case 'reserved': return 'Réservée';
      case 'cleaning': return 'Nettoyage';
      default: return status;
    }
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">🪑 Gestion des Tables</h1>
          <p className="text-gray-600 mt-1">Sélectionnez une table pour prendre une commande</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => navigate('/pos')} className="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
            ← Retour POS
          </button>
          <button 
            onClick={() => { setEditingTable(null); setForm({ number: '', capacity: 4, zone: '' }); setShowModal(true); }}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
          >
            <Plus size={18} /> Ajouter Table
          </button>
        </div>
      </div>

      {/* Légende */}
      <div className="flex gap-4 flex-wrap">
        <div className="flex items-center gap-2">
          <span className="w-4 h-4 rounded bg-green-400"></span>
          <span className="text-sm">Libre</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-4 h-4 rounded bg-red-400"></span>
          <span className="text-sm">Occupée</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-4 h-4 rounded bg-yellow-400"></span>
          <span className="text-sm">Réservée</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-4 h-4 rounded bg-blue-400"></span>
          <span className="text-sm">Nettoyage</span>
        </div>
      </div>

      {/* Tables Grid */}
      {loading ? <TableSkeleton /> : tables.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-xl">
          <Users size={48} className="mx-auto text-gray-300 mb-4" />
          <p className="text-gray-500">Aucune table configurée</p>
          <button 
            onClick={() => setShowModal(true)}
            className="mt-4 text-blue-600 hover:underline"
          >
            Créer votre première table
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {tables.map((table) => (
            <div
              key={table.id}
              onClick={() => handleSelectTable(table)}
              className={`relative cursor-pointer rounded-xl border-2 p-4 transition hover:shadow-lg ${getStatusColor(table.status)}`}
            >
              {/* Actions */}
              <div className="absolute top-2 right-2 flex gap-1">
                <button
                  onClick={(e) => { e.stopPropagation(); setEditingTable(table); setForm(table); setShowModal(true); }}
                  className="p-1 bg-white rounded hover:bg-gray-100"
                >
                  <Edit2 size={14} />
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); handleDeleteTable(table.id); }}
                  className="p-1 bg-white rounded hover:bg-red-100"
                >
                  <Trash2 size={14} className="text-red-500" />
                </button>
              </div>

              {/* Content */}
              <div className="text-center pt-4">
                <span className="text-3xl font-bold">T{table.number}</span>
                <p className="text-sm mt-1">{table.capacity} places</p>
                <p className="text-xs mt-1 font-medium">{getStatusLabel(table.status)}</p>
                {table.zone && <p className="text-xs text-gray-500">{table.zone}</p>}
              </div>

              {/* Order indicator */}
              {table.current_order_id && (
                <div className="mt-2 text-center">
                  <span className="text-xs bg-white px-2 py-1 rounded">
                    Cmd #{table.current_order_id}
                  </span>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold mb-4">
              {editingTable ? 'Modifier la Table' : 'Nouvelle Table'}
            </h2>
            <form onSubmit={handleSaveTable} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Numéro *</label>
                <input
                  type="text"
                  value={form.number}
                  onChange={(e) => setForm({ ...form, number: e.target.value })}
                  required
                  className="w-full px-3 py-2 border rounded-lg"
                  placeholder="Ex: 1, A1, VIP1"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Capacité</label>
                <input
                  type="number"
                  min="1"
                  value={form.capacity}
                  onChange={(e) => setForm({ ...form, capacity: parseInt(e.target.value) })}
                  className="w-full px-3 py-2 border rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                <input
                  type="text"
                  value={form.zone}
                  onChange={(e) => setForm({ ...form, zone: e.target.value })}
                  className="w-full px-3 py-2 border rounded-lg"
                  placeholder="Ex: Terrasse, Intérieur, VIP"
                />
              </div>
              <div className="flex gap-2">
                <button type="submit" className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                  {editingTable ? 'Modifier' : 'Créer'}
                </button>
                <button type="button" onClick={() => setShowModal(false)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300">
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
