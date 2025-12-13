import React, { useState, useEffect, useCallback } from 'react';
import apiClient from '../services/apiClient';
import { Landmark, Plus, Check, X, AlertTriangle, FileText, Download, Calendar, RefreshCw, Search } from 'lucide-react';

export default function RapprochementBancairePage() {
  const [loading, setLoading] = useState(true);
  const [comptes, setComptes] = useState([]);
  const [selectedCompte, setSelectedCompte] = useState(null);
  const [releves, setReleves] = useState([]);
  const [ecritures, setEcritures] = useState([]);
  const [rapprochement, setRapprochement] = useState(null);
  const [showNewModal, setShowNewModal] = useState(false);
  const [dateRange, setDateRange] = useState({
    from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0],
  });
  const [newReleve, setNewReleve] = useState({
    date_releve: '',
    solde_releve: '',
    reference: '',
  });

  // Charger les comptes bancaires (classe 52 SYSCOHADA)
  const fetchComptes = useCallback(async () => {
    try {
      // Charger les comptes de classe 5 (comptes financiers) - sous-classe 52 pour banques
      const res = await apiClient.get('/chart-of-accounts?class=5');
      const allComptes = res.data?.data || [];
      // Filtrer pour ne garder que les comptes bancaires (52x)
      const compteBancaires = allComptes.filter(c => c.code?.startsWith('52'));
      setComptes(compteBancaires);
    } catch (error) {
      console.error('Erreur chargement comptes bancaires:', error);
      setComptes([]);
    }
  }, []);

  // Charger les écritures et relevés pour un compte
  const fetchRapprochement = useCallback(async () => {
    if (!selectedCompte) return;
    setLoading(true);
    try {
      const [ecrituresRes, relevesRes, rapprochementRes] = await Promise.all([
        apiClient.get(`/accounting/grand-livre/${selectedCompte.id}?from=${dateRange.from}&to=${dateRange.to}`),
        apiClient.get(`/accounting/bank-statements?account_id=${selectedCompte.id}`),
        apiClient.get(`/accounting/rapprochement?account_id=${selectedCompte.id}&date_fin=${dateRange.to}`),
      ]);
      
      // Transformer les écritures du grand livre pour le rapprochement
      const ecrituresData = (ecrituresRes.data?.data || []).map(e => ({
        id: e.id || e.source_id,
        date: e.date,
        libelle: e.description || e.libelle,
        debit: e.debit || 0,
        credit: e.credit || 0,
        rapproche: e.rapproche || false,
      }));
      
      setEcritures(ecrituresData);
      setReleves(relevesRes.data?.data || []);
      
      // Utiliser les données de rapprochement du backend si disponibles
      if (rapprochementRes.data?.data) {
        const rap = rapprochementRes.data.data;
        setRapprochement({
          soldeReleve: rap.solde_releve || 0,
          soldeComptable: rap.solde_comptable || selectedCompte.solde || 0,
          debitsNonRapproches: rap.debits_non_rapproches || 0,
          creditsNonRapproches: rap.credits_non_rapproches || 0,
          ecart: rap.ecart || 0,
          soldeRapproche: rap.solde_rapproche || 0,
          isRapproche: rap.is_rapproche || false,
          dateReleve: rap.date_releve,
        });
      } else {
        // Calculer localement si le backend ne renvoie pas les données
        calculateRapprochement(ecrituresData, relevesRes.data?.data || []);
      }
    } catch (error) {
      console.error('Erreur chargement rapprochement:', error);
      setEcritures([]);
      setReleves([]);
      setRapprochement(null);
    } finally {
      setLoading(false);
    }
  }, [selectedCompte, dateRange]);

  const calculateRapprochement = (ecritures, releves) => {
    const lastReleve = releves.length > 0 ? releves[releves.length - 1] : null;
    const soldeReleve = lastReleve?.solde_releve || 0;
    
    // Écritures non rapprochées
    const nonRapprochees = ecritures.filter(e => !e.rapproche);
    const debitsNonRapproches = nonRapprochees.reduce((sum, e) => sum + (e.debit || 0), 0);
    const creditsNonRapproches = nonRapprochees.reduce((sum, e) => sum + (e.credit || 0), 0);
    
    // Solde comptable
    const totalDebits = ecritures.reduce((sum, e) => sum + (e.debit || 0), 0);
    const totalCredits = ecritures.reduce((sum, e) => sum + (e.credit || 0), 0);
    const soldeComptable = selectedCompte?.solde || (totalDebits - totalCredits);
    
    // Écart
    const ecart = soldeComptable - soldeReleve;
    
    setRapprochement({
      soldeReleve,
      soldeComptable,
      debitsNonRapproches,
      creditsNonRapproches,
      ecart,
      soldeRapproche: soldeReleve + debitsNonRapproches - creditsNonRapproches,
      isRapproche: Math.abs(ecart) < 1,
      dateReleve: lastReleve?.date_releve,
    });
  };

  useEffect(() => {
    fetchComptes();
  }, [fetchComptes]);

  useEffect(() => {
    if (selectedCompte) {
      fetchRapprochement();
    }
  }, [selectedCompte, fetchRapprochement]);

  const handleToggleRapproche = async (ecritureId) => {
    try {
      await apiClient.post(`/accounting/entries/${ecritureId}/toggle-rapproche`);
      fetchRapprochement();
    } catch (error) {
      console.error('Erreur toggle rapprochement:', error);
      // Toggle local en cas d'erreur pour permettre le travail hors-ligne
      setEcritures(prev => {
        const updated = prev.map(e => 
          e.id === ecritureId ? { ...e, rapproche: !e.rapproche } : e
        );
        calculateRapprochement(updated, releves);
        return updated;
      });
    }
  };

  const handleAddReleve = async (e) => {
    e.preventDefault();
    try {
      await apiClient.post('/accounting/bank-statements', {
        account_id: selectedCompte.id,
        ...newReleve,
        solde_releve: parseFloat(newReleve.solde_releve),
      });
      setShowNewModal(false);
      setNewReleve({ date_releve: '', solde_releve: '', reference: '' });
      fetchRapprochement();
    } catch (error) {
      console.error('Erreur ajout relevé:', error);
      alert('Erreur lors de l\'enregistrement du relevé');
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR').format(val || 0) + ' FCFA';

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Landmark className="text-blue-600" /> État de Rapprochement Bancaire
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">Rapprochement des comptes bancaires - SYSCOHADA Révisé</p>
        </div>
        <button
          onClick={fetchRapprochement}
          disabled={loading || !selectedCompte}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          <RefreshCw size={16} className={loading ? 'animate-spin' : ''} /> Actualiser
        </button>
      </div>

      {/* Sélection compte et période */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Compte bancaire</label>
            <select
              value={selectedCompte?.id || ''}
              onChange={(e) => setSelectedCompte(comptes.find(c => c.id === parseInt(e.target.value)))}
              className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
            >
              <option value="">Sélectionner un compte</option>
              {comptes.map(compte => (
                <option key={compte.id} value={compte.id}>
                  {compte.code} - {compte.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Date début</label>
            <input
              type="date"
              value={dateRange.from}
              onChange={(e) => setDateRange({...dateRange, from: e.target.value})}
              className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Date fin</label>
            <input
              type="date"
              value={dateRange.to}
              onChange={(e) => setDateRange({...dateRange, to: e.target.value})}
              className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
            />
          </div>
          <div className="flex items-end">
            <button
              onClick={() => setShowNewModal(true)}
              disabled={!selectedCompte}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              <Plus size={16} /> Nouveau relevé
            </button>
          </div>
        </div>
      </div>

      {selectedCompte && rapprochement && (
        <>
          {/* État de rapprochement */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm overflow-hidden">
            <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600">
              <h2 className="font-semibold text-gray-800 dark:text-white">État de Rapprochement au {rapprochement.dateReleve || dateRange.to}</h2>
            </div>
            <div className="p-4">
              <table className="w-full text-sm">
                <tbody>
                  <tr className="border-b dark:border-gray-700">
                    <td className="py-3 font-medium text-gray-900 dark:text-white">Solde du relevé bancaire</td>
                    <td className="py-3 text-right font-bold text-blue-600 dark:text-blue-400">{formatCurrency(rapprochement.soldeReleve)}</td>
                  </tr>
                  <tr className="border-b dark:border-gray-700 bg-green-50 dark:bg-green-900/20">
                    <td className="py-3 pl-4 text-gray-700 dark:text-gray-300">+ Encaissements non encore crédités en banque</td>
                    <td className="py-3 text-right text-green-600 dark:text-green-400">+ {formatCurrency(rapprochement.debitsNonRapproches)}</td>
                  </tr>
                  <tr className="border-b dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                    <td className="py-3 pl-4 text-gray-700 dark:text-gray-300">- Décaissements non encore débités en banque</td>
                    <td className="py-3 text-right text-red-600 dark:text-red-400">- {formatCurrency(rapprochement.creditsNonRapproches)}</td>
                  </tr>
                  <tr className="border-b-2 border-gray-300 dark:border-gray-600">
                    <td className="py-3 font-bold text-gray-900 dark:text-white">= Solde rapproché</td>
                    <td className="py-3 text-right font-bold text-lg text-gray-900 dark:text-white">{formatCurrency(rapprochement.soldeRapproche)}</td>
                  </tr>
                  <tr className="border-b dark:border-gray-700">
                    <td className="py-3 font-medium text-gray-900 dark:text-white">Solde comptable (compte {selectedCompte.code})</td>
                    <td className="py-3 text-right font-bold text-gray-900 dark:text-white">{formatCurrency(rapprochement.soldeComptable)}</td>
                  </tr>
                  <tr className={rapprochement.isRapproche ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'}>
                    <td className="py-3 font-bold flex items-center gap-2">
                      {rapprochement.isRapproche ? (
                        <Check className="text-green-600" size={18} />
                      ) : (
                        <AlertTriangle className="text-red-600" size={18} />
                      )}
                      Écart de rapprochement
                    </td>
                    <td className={`py-3 text-right font-bold text-lg ${rapprochement.isRapproche ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                      {formatCurrency(rapprochement.ecart)}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* Écritures à rapprocher */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm overflow-hidden">
            <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600 flex justify-between items-center">
              <h2 className="font-semibold text-gray-800 dark:text-white">Écritures comptables</h2>
              <span className="text-sm text-gray-500 dark:text-gray-400">
                {ecritures.filter(e => !e.rapproche).length} non rapprochée(s)
              </span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600">
                  <tr>
                    <th className="py-3 px-4 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                    <th className="py-3 px-4 text-left font-medium text-gray-600 dark:text-gray-300">Libellé</th>
                    <th className="py-3 px-4 text-right font-medium text-gray-600 dark:text-gray-300">Débit</th>
                    <th className="py-3 px-4 text-right font-medium text-gray-600 dark:text-gray-300">Crédit</th>
                    <th className="py-3 px-4 text-center font-medium text-gray-600 dark:text-gray-300">Rapproché</th>
                  </tr>
                </thead>
                <tbody className="divide-y dark:divide-gray-700">
                  {ecritures.map(ecriture => (
                    <tr key={ecriture.id} className={`hover:bg-gray-50 dark:hover:bg-gray-700 ${ecriture.rapproche ? 'bg-green-50/50 dark:bg-green-900/20' : ''}`}>
                      <td className="py-3 px-4 text-gray-900 dark:text-white">{new Date(ecriture.date).toLocaleDateString('fr-FR')}</td>
                      <td className="py-3 px-4 text-gray-900 dark:text-white">{ecriture.libelle}</td>
                      <td className="py-3 px-4 text-right text-green-600 dark:text-green-400">
                        {ecriture.debit > 0 ? formatCurrency(ecriture.debit) : '-'}
                      </td>
                      <td className="py-3 px-4 text-right text-red-600 dark:text-red-400">
                        {ecriture.credit > 0 ? formatCurrency(ecriture.credit) : '-'}
                      </td>
                      <td className="py-3 px-4 text-center">
                        <button
                          onClick={() => handleToggleRapproche(ecriture.id)}
                          className={`p-1.5 rounded ${ecriture.rapproche ? 'bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-600 text-gray-400'}`}
                        >
                          {ecriture.rapproche ? <Check size={16} /> : <X size={16} />}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Historique des relevés */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm overflow-hidden">
            <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600">
              <h2 className="font-semibold text-gray-800 dark:text-white">Historique des relevés bancaires</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600">
                  <tr>
                    <th className="py-3 px-4 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                    <th className="py-3 px-4 text-left font-medium text-gray-600 dark:text-gray-300">Référence</th>
                    <th className="py-3 px-4 text-right font-medium text-gray-600 dark:text-gray-300">Solde</th>
                  </tr>
                </thead>
                <tbody className="divide-y dark:divide-gray-700">
                  {releves.length === 0 ? (
                    <tr><td colSpan={3} className="py-4 text-center text-gray-400">Aucun relevé enregistré</td></tr>
                  ) : releves.map(releve => (
                    <tr key={releve.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                      <td className="py-3 px-4 text-gray-900 dark:text-white">{new Date(releve.date_releve).toLocaleDateString('fr-FR')}</td>
                      <td className="py-3 px-4 text-gray-900 dark:text-white">{releve.reference}</td>
                      <td className="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">{formatCurrency(releve.solde_releve)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {!selectedCompte && (
        <div className="bg-gray-50 dark:bg-gray-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
          <Landmark size={48} className="mx-auto mb-4 text-gray-300 dark:text-gray-600" />
          <p>Sélectionnez un compte bancaire pour commencer le rapprochement</p>
        </div>
      )}

      {/* Modal nouveau relevé */}
      {showNewModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white">Nouveau Relevé Bancaire</h2>
              <button onClick={() => setShowNewModal(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
            </div>
            <form onSubmit={handleAddReleve} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Date du relevé *</label>
                <input
                  type="date"
                  value={newReleve.date_releve}
                  onChange={(e) => setNewReleve({...newReleve, date_releve: e.target.value})}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Solde du relevé (FCFA) *</label>
                <input
                  type="number"
                  value={newReleve.solde_releve}
                  onChange={(e) => setNewReleve({...newReleve, solde_releve: e.target.value})}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Référence</label>
                <input
                  type="text"
                  value={newReleve.reference}
                  onChange={(e) => setNewReleve({...newReleve, reference: e.target.value})}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                  placeholder="Ex: REL-2024-12"
                />
              </div>
              <div className="flex gap-3 pt-4">
                <button type="button" onClick={() => setShowNewModal(false)} className="flex-1 border dark:border-gray-600 dark:text-gray-300 py-2 rounded-lg">
                  Annuler
                </button>
                <button type="submit" className="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                  Enregistrer
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
