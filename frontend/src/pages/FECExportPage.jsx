import React, { useState, useCallback } from 'react';
import { FileText, Download, Eye, Calendar, Loader2, CheckCircle, AlertCircle } from 'lucide-react';
import apiClient from '../services/apiClient';
import { useToast } from '../components/Toast';

/**
 * Page d'export FEC (Fichier des Écritures Comptables)
 * Conforme aux normes OHADA/SYSCOHADA
 */
export default function FECExportPage() {
  const { toast } = useToast();
  const [startDate, setStartDate] = useState(() => {
    const date = new Date();
    date.setMonth(0, 1); // 1er janvier de l'année en cours
    return date.toISOString().split('T')[0];
  });
  const [endDate, setEndDate] = useState(() => new Date().toISOString().split('T')[0]);
  const [format, setFormat] = useState('txt');
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);

  const handlePreview = useCallback(async () => {
    setPreviewLoading(true);
    try {
      const response = await apiClient.get('/fec/preview', {
        params: { start_date: startDate, end_date: endDate, limit: 20 }
      });
      setPreview(response.data);
    } catch (error) {
      toast('Erreur lors de la prévisualisation', 'error');
    } finally {
      setPreviewLoading(false);
    }
  }, [startDate, endDate, toast]);

  const handleExport = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiClient.get('/fec/export', {
        params: { start_date: startDate, end_date: endDate, format },
        responseType: 'blob'
      });
      
      // Créer le lien de téléchargement
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `FEC_${startDate}_${endDate}.${format}`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      toast('Export FEC téléchargé avec succès', 'success');
    } catch (error) {
      toast('Erreur lors de l\'export', 'error');
    } finally {
      setLoading(false);
    }
  }, [startDate, endDate, format, toast]);

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <FileText className="text-orange-500" />
            Export FEC
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            Fichier des Écritures Comptables - Format OHADA/SYSCOHADA
          </p>
        </div>
      </div>

      {/* Configuration */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Paramètres d'export
        </h2>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Date début */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              <Calendar size={14} className="inline mr-1" />
              Date de début
            </label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Date fin */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              <Calendar size={14} className="inline mr-1" />
              Date de fin
            </label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Format */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Format
            </label>
            <select
              value={format}
              onChange={(e) => setFormat(e.target.value)}
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-orange-500"
            >
              <option value="txt">TXT (Tabulation)</option>
              <option value="csv">CSV (Point-virgule)</option>
            </select>
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 mt-6">
          <button
            onClick={handlePreview}
            disabled={previewLoading}
            className="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
          >
            {previewLoading ? <Loader2 size={18} className="animate-spin" /> : <Eye size={18} />}
            Prévisualiser
          </button>
          <button
            onClick={handleExport}
            disabled={loading}
            className="flex items-center gap-2 px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors font-medium"
          >
            {loading ? <Loader2 size={18} className="animate-spin" /> : <Download size={18} />}
            Exporter FEC
          </button>
        </div>
      </div>

      {/* Info */}
      <div className="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 rounded-xl p-4">
        <h3 className="font-semibold text-blue-800 dark:text-blue-400 mb-2">
          À propos du FEC
        </h3>
        <p className="text-sm text-blue-700 dark:text-blue-300">
          Le Fichier des Écritures Comptables (FEC) est un export standardisé de vos écritures comptables.
          Il contient 18 colonnes conformes aux normes OHADA : JournalCode, JournalLib, EcritureNum, 
          EcritureDate, CompteNum, CompteLib, etc.
        </p>
      </div>

      {/* Preview */}
      {preview && (
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              Prévisualisation ({preview.total_entries} écritures)
            </h2>
            <span className="text-sm text-gray-500">
              Affichage des {preview.preview.length} premières lignes
            </span>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  {preview.columns?.slice(0, 8).map((col) => (
                    <th key={col} className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {preview.preview.map((entry, idx) => (
                  <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-3 py-2 text-gray-900 dark:text-gray-100">{entry.JournalCode}</td>
                    <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{entry.JournalLib}</td>
                    <td className="px-3 py-2 text-gray-900 dark:text-gray-100">{entry.EcritureNum}</td>
                    <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{entry.EcritureDate}</td>
                    <td className="px-3 py-2 font-mono text-gray-900 dark:text-gray-100">{entry.CompteNum}</td>
                    <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{entry.CompteLib}</td>
                    <td className="px-3 py-2 text-right text-green-600 dark:text-green-400">
                      {parseFloat(entry.Debit) > 0 ? entry.Debit : '-'}
                    </td>
                    <td className="px-3 py-2 text-right text-red-600 dark:text-red-400">
                      {parseFloat(entry.Credit) > 0 ? entry.Credit : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
