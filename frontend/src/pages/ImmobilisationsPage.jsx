import React, { useState, useEffect, useCallback } from 'react';
import apiClient from '../services/apiClient';
import { Building2, Plus, Edit, Trash2, Calculator, FileText, Download, Filter, Calendar } from 'lucide-react';

// Catégories SYSCOHADA REVISÉ
const CATEGORIES_SYSCOHADA = [
  { code: '21', label: 'Immobilisations incorporelles', taux: 20 },
  { code: '22', label: 'Terrains', taux: 0 },
  { code: '23', label: 'Bâtiments', taux: 5 },
  { code: '24', label: 'Matériel et outillage', taux: 10 },
  { code: '244', label: 'Matériel et mobilier de bureau', taux: 10 },
  { code: '245', label: 'Matériel de transport', taux: 20 },
  { code: '246', label: 'Matériel informatique', taux: 25 },
  { code: '25', label: 'Avances et acomptes sur immobilisations', taux: 0 },
];

const METHODES_AMORTISSEMENT = [
  { value: 'lineaire', label: 'Linéaire' },
  { value: 'degressif', label: 'Dégressif' },
  { value: 'unites_production', label: 'Unités de production' },
];

export default function ImmobilisationsPage() {
  const [immobilisations, setImmobilisations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [showAmortModal, setShowAmortModal] = useState(false);
  const [selectedImmo, setSelectedImmo] = useState(null);
  const [filter, setFilter] = useState({ category: '', status: 'active' });
  const [form, setForm] = useState({
    designation: '',
    category_code: '24',
    date_acquisition: '',
    valeur_acquisition: '',
    valeur_residuelle: '0',
    duree_vie: '5',
    methode_amortissement: 'lineaire',
    numero_serie: '',
    fournisseur: '',
    localisation: '',
    notes: '',
  });

  const fetchImmobilisations = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filter.category) params.append('category', filter.category);
      if (filter.status) params.append('status', filter.status);
      
      const res = await apiClient.get(`/accounting/immobilisations?${params}`);
      setImmobilisations(res.data?.data || []);
    } catch (error) {
      console.error('Erreur chargement immobilisations:', error);
      setImmobilisations([]);
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    fetchImmobilisations();
  }, [fetchImmobilisations]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (selectedImmo) {
        await apiClient.put(`/accounting/immobilisations/${selectedImmo.id}`, form);
      } else {
        await apiClient.post('/accounting/immobilisations', form);
      }
      setShowModal(false);
      resetForm();
      fetchImmobilisations();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur lors de l\'enregistrement');
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Supprimer cette immobilisation?')) return;
    try {
      await apiClient.delete(`/accounting/immobilisations/${id}`);
      fetchImmobilisations();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const calculateAmortissements = async () => {
    try {
      const res = await apiClient.post('/accounting/immobilisations/calculate', { year: new Date().getFullYear() });
      alert(`✅ Amortissements calculés: ${res.data?.total_amortissement?.toLocaleString('fr-FR')} FCFA`);
      fetchImmobilisations();
    } catch (error) {
      alert('❌ Erreur lors du calcul des amortissements');
    }
  };

  const resetForm = () => {
    setForm({
      designation: '',
      category_code: '24',
      date_acquisition: '',
      valeur_acquisition: '',
      valeur_residuelle: '0',
      duree_vie: '5',
      methode_amortissement: 'lineaire',
      numero_serie: '',
      fournisseur: '',
      localisation: '',
      notes: '',
    });
    setSelectedImmo(null);
  };

  const openEdit = (immo) => {
    setSelectedImmo(immo);
    setForm({
      designation: immo.designation,
      category_code: immo.category_code,
      date_acquisition: immo.date_acquisition,
      valeur_acquisition: immo.valeur_acquisition.toString(),
      valeur_residuelle: immo.valeur_residuelle?.toString() || '0',
      duree_vie: immo.duree_vie.toString(),
      methode_amortissement: immo.methode_amortissement,
      numero_serie: immo.numero_serie || '',
      fournisseur: immo.fournisseur || '',
      localisation: immo.localisation || '',
      notes: immo.notes || '',
    });
    setShowModal(true);
  };

  // Calcul tableau d'amortissement
  const calculateAmortissement = (immo) => {
    const tableau = [];
    const base = immo.valeur_acquisition - immo.valeur_residuelle;
    const annuite = base / immo.duree_vie;
    let cumul = 0;
    
    const startYear = new Date(immo.date_acquisition).getFullYear();
    
    for (let i = 0; i < immo.duree_vie; i++) {
      cumul += annuite;
      tableau.push({
        annee: startYear + i,
        base_amortissable: base,
        annuite: annuite,
        cumul_amortissement: cumul,
        vnc: immo.valeur_acquisition - cumul,
      });
    }
    return tableau;
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR').format(val || 0) + ' FCFA';

  // Totaux
  const totals = immobilisations.reduce((acc, immo) => ({
    valeur_acquisition: acc.valeur_acquisition + (immo.valeur_acquisition || 0),
    cumul_amortissement: acc.cumul_amortissement + (immo.cumul_amortissement || 0),
    vnc: acc.vnc + (immo.vnc || 0),
  }), { valeur_acquisition: 0, cumul_amortissement: 0, vnc: 0 });

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-gray-900 flex items-center gap-2">
            <Building2 className="text-blue-600" /> Immobilisations
          </h1>
          <p className="text-sm text-gray-500">Gestion des actifs immobilisés - SYSCOHADA Révisé</p>
        </div>
        <button
          onClick={() => { resetForm(); setShowModal(true); }}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          <Plus size={18} /> Nouvelle immobilisation
        </button>
      </div>

      {/* Filtres */}
      <div className="flex flex-wrap gap-3 bg-white p-3 rounded-xl border">
        <select
          value={filter.category}
          onChange={(e) => setFilter({...filter, category: e.target.value})}
          className="text-sm border rounded-lg px-3 py-2"
        >
          <option value="">Toutes catégories</option>
          {CATEGORIES_SYSCOHADA.map(cat => (
            <option key={cat.code} value={cat.code}>{cat.code} - {cat.label}</option>
          ))}
        </select>
        <select
          value={filter.status}
          onChange={(e) => setFilter({...filter, status: e.target.value})}
          className="text-sm border rounded-lg px-3 py-2"
        >
          <option value="active">Actives</option>
          <option value="ceded">Cédées</option>
          <option value="scrapped">Mises au rebut</option>
          <option value="">Toutes</option>
        </select>
      </div>

      {/* Résumé */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div className="bg-blue-50 rounded-xl p-4 border border-blue-200">
          <p className="text-xs text-blue-600 font-medium">Valeur d'acquisition totale</p>
          <p className="text-xl font-bold text-blue-800">{formatCurrency(totals.valeur_acquisition)}</p>
        </div>
        <div className="bg-orange-50 rounded-xl p-4 border border-orange-200">
          <p className="text-xs text-orange-600 font-medium">Amortissements cumulés</p>
          <p className="text-xl font-bold text-orange-800">{formatCurrency(totals.cumul_amortissement)}</p>
        </div>
        <div className="bg-green-50 rounded-xl p-4 border border-green-200">
          <p className="text-xs text-green-600 font-medium">Valeur Nette Comptable (VNC)</p>
          <p className="text-xl font-bold text-green-800">{formatCurrency(totals.vnc)}</p>
        </div>
      </div>

      {/* Tableau des immobilisations */}
      <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left py-3 px-4 font-medium text-gray-600">Désignation</th>
                <th className="text-left py-3 px-4 font-medium text-gray-600">Catégorie</th>
                <th className="text-center py-3 px-4 font-medium text-gray-600">Date acq.</th>
                <th className="text-right py-3 px-4 font-medium text-gray-600">Valeur acq.</th>
                <th className="text-right py-3 px-4 font-medium text-gray-600">Amort. cumulé</th>
                <th className="text-right py-3 px-4 font-medium text-gray-600">VNC</th>
                <th className="text-center py-3 px-4 font-medium text-gray-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {loading ? (
                <tr><td colSpan={7} className="py-8 text-center text-gray-400">Chargement...</td></tr>
              ) : immobilisations.length === 0 ? (
                <tr><td colSpan={7} className="py-8 text-center text-gray-400">Aucune immobilisation</td></tr>
              ) : immobilisations.map(immo => (
                <tr key={immo.id} className="hover:bg-gray-50">
                  <td className="py-3 px-4">
                    <p className="font-medium text-gray-900">{immo.designation}</p>
                    {immo.numero_serie && <p className="text-xs text-gray-400">N° {immo.numero_serie}</p>}
                  </td>
                  <td className="py-3 px-4">
                    <span className="text-xs bg-gray-100 px-2 py-1 rounded">{immo.category_code}</span>
                    <p className="text-xs text-gray-500 mt-1">{immo.category_label}</p>
                  </td>
                  <td className="py-3 px-4 text-center text-gray-600">
                    {new Date(immo.date_acquisition).toLocaleDateString('fr-FR')}
                  </td>
                  <td className="py-3 px-4 text-right font-medium">{formatCurrency(immo.valeur_acquisition)}</td>
                  <td className="py-3 px-4 text-right text-orange-600">{formatCurrency(immo.cumul_amortissement)}</td>
                  <td className="py-3 px-4 text-right font-bold text-green-600">{formatCurrency(immo.vnc)}</td>
                  <td className="py-3 px-4">
                    <div className="flex justify-center gap-1">
                      <button
                        onClick={() => { setSelectedImmo(immo); setShowAmortModal(true); }}
                        className="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                        title="Tableau d'amortissement"
                      >
                        <Calculator size={16} />
                      </button>
                      <button
                        onClick={() => openEdit(immo)}
                        className="p-1.5 text-gray-600 hover:bg-gray-100 rounded"
                        title="Modifier"
                      >
                        <Edit size={16} />
                      </button>
                      <button
                        onClick={() => handleDelete(immo.id)}
                        className="p-1.5 text-red-600 hover:bg-red-50 rounded"
                        title="Supprimer"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal Création/Édition */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b flex justify-between items-center">
              <h2 className="text-lg font-bold">{selectedImmo ? 'Modifier' : 'Nouvelle'} Immobilisation</h2>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium mb-1">Désignation *</label>
                  <input
                    type="text"
                    value={form.designation}
                    onChange={(e) => setForm({...form, designation: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Catégorie SYSCOHADA *</label>
                  <select
                    value={form.category_code}
                    onChange={(e) => setForm({...form, category_code: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    required
                  >
                    {CATEGORIES_SYSCOHADA.map(cat => (
                      <option key={cat.code} value={cat.code}>{cat.code} - {cat.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Date d'acquisition *</label>
                  <input
                    type="date"
                    value={form.date_acquisition}
                    onChange={(e) => setForm({...form, date_acquisition: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Valeur d'acquisition (FCFA) *</label>
                  <input
                    type="number"
                    value={form.valeur_acquisition}
                    onChange={(e) => setForm({...form, valeur_acquisition: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    min="0"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Valeur résiduelle (FCFA)</label>
                  <input
                    type="number"
                    value={form.valeur_residuelle}
                    onChange={(e) => setForm({...form, valeur_residuelle: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    min="0"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Durée de vie (années) *</label>
                  <input
                    type="number"
                    value={form.duree_vie}
                    onChange={(e) => setForm({...form, duree_vie: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    min="1"
                    max="50"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Méthode d'amortissement</label>
                  <select
                    value={form.methode_amortissement}
                    onChange={(e) => setForm({...form, methode_amortissement: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                  >
                    {METHODES_AMORTISSEMENT.map(m => (
                      <option key={m.value} value={m.value}>{m.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">N° de série</label>
                  <input
                    type="text"
                    value={form.numero_serie}
                    onChange={(e) => setForm({...form, numero_serie: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Fournisseur</label>
                  <input
                    type="text"
                    value={form.fournisseur}
                    onChange={(e) => setForm({...form, fournisseur: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Localisation</label>
                  <input
                    type="text"
                    value={form.localisation}
                    onChange={(e) => setForm({...form, localisation: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium mb-1">Notes</label>
                  <textarea
                    value={form.notes}
                    onChange={(e) => setForm({...form, notes: e.target.value})}
                    className="w-full border rounded-lg px-3 py-2"
                    rows={2}
                  />
                </div>
              </div>
              <div className="flex gap-3 pt-4">
                <button type="button" onClick={() => setShowModal(false)} className="flex-1 border py-2 rounded-lg">
                  Annuler
                </button>
                <button type="submit" className="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                  {selectedImmo ? 'Mettre à jour' : 'Enregistrer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Tableau d'amortissement */}
      {showAmortModal && selectedImmo && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b flex justify-between items-center">
              <div>
                <h2 className="text-lg font-bold">Tableau d'Amortissement</h2>
                <p className="text-sm text-gray-500">{selectedImmo.designation}</p>
              </div>
              <button onClick={() => setShowAmortModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div className="p-6">
              {/* Infos */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div className="bg-gray-50 p-3 rounded-lg">
                  <p className="text-xs text-gray-500">Valeur d'acquisition</p>
                  <p className="font-bold">{formatCurrency(selectedImmo.valeur_acquisition)}</p>
                </div>
                <div className="bg-gray-50 p-3 rounded-lg">
                  <p className="text-xs text-gray-500">Valeur résiduelle</p>
                  <p className="font-bold">{formatCurrency(selectedImmo.valeur_residuelle)}</p>
                </div>
                <div className="bg-gray-50 p-3 rounded-lg">
                  <p className="text-xs text-gray-500">Durée de vie</p>
                  <p className="font-bold">{selectedImmo.duree_vie} ans</p>
                </div>
                <div className="bg-gray-50 p-3 rounded-lg">
                  <p className="text-xs text-gray-500">Méthode</p>
                  <p className="font-bold capitalize">{selectedImmo.methode_amortissement}</p>
                </div>
              </div>

              {/* Tableau */}
              <table className="w-full text-sm border">
                <thead className="bg-gray-100">
                  <tr>
                    <th className="py-2 px-3 text-left border">Année</th>
                    <th className="py-2 px-3 text-right border">Base amortissable</th>
                    <th className="py-2 px-3 text-right border">Annuité</th>
                    <th className="py-2 px-3 text-right border">Amort. cumulé</th>
                    <th className="py-2 px-3 text-right border">VNC</th>
                  </tr>
                </thead>
                <tbody>
                  {calculateAmortissement(selectedImmo).map((row, idx) => (
                    <tr key={idx} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                      <td className="py-2 px-3 border font-medium">{row.annee}</td>
                      <td className="py-2 px-3 border text-right">{formatCurrency(row.base_amortissable)}</td>
                      <td className="py-2 px-3 border text-right text-orange-600">{formatCurrency(row.annuite)}</td>
                      <td className="py-2 px-3 border text-right">{formatCurrency(row.cumul_amortissement)}</td>
                      <td className="py-2 px-3 border text-right font-bold text-green-600">{formatCurrency(row.vnc)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
