/**
 * Composant ExportButton - Bouton d'export universel
 * Permet d'exporter des données en Excel, PDF ou Word
 */

import { useState, useRef, useEffect, memo } from 'react';
import { Download, FileSpreadsheet, FileText, File, ChevronDown, Loader2 } from 'lucide-react';
import { exportToExcel, exportToPDF, exportToWord } from '../services/exportService';

const ExportButton = memo(({ 
  data = [], 
  columns = [], 
  filename = 'export',
  title = '',
  subtitle = '',
  formats = ['excel', 'pdf', 'word'],
  disabled = false,
  variant = 'primary', // primary, secondary, ghost
  size = 'md', // sm, md, lg
  className = '',
  onExportStart,
  onExportEnd,
  onError
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(null);
  const dropdownRef = useRef(null);

  // Fermer le dropdown au clic extérieur
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleExport = async (format) => {
    if (loading || disabled || !data.length) return;
    
    setLoading(format);
    onExportStart?.(format);
    
    try {
      const options = { title, subtitle };
      
      switch (format) {
        case 'excel':
          await exportToExcel(data, columns, filename, options);
          break;
        case 'pdf':
          await exportToPDF(data, columns, filename, options);
          break;
        case 'word':
          await exportToWord(data, columns, filename, options);
          break;
      }
      
      onExportEnd?.(format, true);
    } catch (error) {
      console.error(`Erreur export ${format}:`, error);
      onError?.(format, error);
      onExportEnd?.(format, false);
    } finally {
      setLoading(null);
      setIsOpen(false);
    }
  };

  const formatConfig = {
    excel: { 
      icon: FileSpreadsheet, 
      label: 'Excel (.xlsx)', 
      color: 'text-green-600 dark:text-green-400',
      bg: 'hover:bg-green-50 dark:hover:bg-green-900/20'
    },
    pdf: { 
      icon: FileText, 
      label: 'PDF', 
      color: 'text-red-600 dark:text-red-400',
      bg: 'hover:bg-red-50 dark:hover:bg-red-900/20'
    },
    word: { 
      icon: File, 
      label: 'Word (.docx)', 
      color: 'text-blue-600 dark:text-blue-400',
      bg: 'hover:bg-blue-50 dark:hover:bg-blue-900/20'
    }
  };

  const sizeClasses = {
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
    lg: 'px-5 py-2.5 text-base gap-2'
  };

  const variantClasses = {
    primary: 'bg-brand-600 hover:bg-brand-700 text-white shadow-sm',
    secondary: 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700',
    ghost: 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
  };

  const isDisabled = disabled || !data.length;

  return (
    <div className="relative inline-block" ref={dropdownRef}>
      <button
        onClick={() => !isDisabled && setIsOpen(!isOpen)}
        disabled={isDisabled}
        className={`
          inline-flex items-center justify-center font-medium rounded-lg transition-all
          ${sizeClasses[size]}
          ${variantClasses[variant]}
          ${isDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
          ${className}
        `}
      >
        {loading ? (
          <Loader2 size={size === 'sm' ? 14 : 18} className="animate-spin" />
        ) : (
          <Download size={size === 'sm' ? 14 : 18} />
        )}
        <span>Exporter</span>
        <ChevronDown size={size === 'sm' ? 12 : 16} className={`transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 py-1 z-50 animate-scale-in origin-top-right">
          <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
              Format d'export
            </p>
          </div>
          
          {formats.map((format) => {
            const config = formatConfig[format];
            if (!config) return null;
            
            const Icon = config.icon;
            const isLoading = loading === format;
            
            return (
              <button
                key={format}
                onClick={() => handleExport(format)}
                disabled={isLoading}
                className={`
                  w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors
                  ${config.bg}
                  ${isLoading ? 'opacity-50' : ''}
                `}
              >
                {isLoading ? (
                  <Loader2 size={18} className={`animate-spin ${config.color}`} />
                ) : (
                  <Icon size={18} className={config.color} />
                )}
                <span className="text-sm text-gray-700 dark:text-gray-200">{config.label}</span>
              </button>
            );
          })}
          
          {!data.length && (
            <div className="px-3 py-2 text-xs text-gray-400 dark:text-gray-500 text-center">
              Aucune donnée à exporter
            </div>
          )}
        </div>
      )}
    </div>
  );
});

ExportButton.displayName = 'ExportButton';

export default ExportButton;

/**
 * Hook pour faciliter l'utilisation de l'export
 */
export const useExport = () => {
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState(null);

  const exportData = async (format, data, columns, filename, options = {}) => {
    setExporting(true);
    setError(null);
    
    try {
      switch (format) {
        case 'excel':
          await exportToExcel(data, columns, filename, options);
          break;
        case 'pdf':
          await exportToPDF(data, columns, filename, options);
          break;
        case 'word':
          await exportToWord(data, columns, filename, options);
          break;
        default:
          throw new Error(`Format non supporté: ${format}`);
      }
      return true;
    } catch (err) {
      setError(err.message);
      return false;
    } finally {
      setExporting(false);
    }
  };

  return { exportData, exporting, error };
};
