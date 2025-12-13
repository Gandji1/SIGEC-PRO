import { useState, useEffect, useRef, memo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, X, Package, Users, ShoppingCart, FileText, Settings, BarChart3 } from 'lucide-react';

/**
 * Composant de recherche globale
 * Permet de naviguer rapidement vers les pages et fonctionnalités
 */
const GlobalSearch = memo(({ isOpen, onClose }) => {
  const navigate = useNavigate();
  const inputRef = useRef(null);
  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);

  // Liste des éléments recherchables
  const searchItems = [
    { icon: BarChart3, label: 'Dashboard', path: '/dashboard', keywords: ['accueil', 'tableau de bord', 'home'] },
    { icon: ShoppingCart, label: 'Point de Vente', path: '/pos', keywords: ['pos', 'vente', 'caisse', 'vendre'] },
    { icon: Package, label: 'Produits', path: '/products', keywords: ['article', 'stock', 'inventaire'] },
    { icon: Package, label: 'Inventaire', path: '/inventory-enriched', keywords: ['stock', 'quantité', 'entrepôt', 'cmm'] },
    { icon: Users, label: 'Clients', path: '/customers', keywords: ['client', 'acheteur', 'customer'] },
    { icon: Users, label: 'Fournisseurs', path: '/suppliers', keywords: ['fournisseur', 'supplier', 'achat'] },
    { icon: Users, label: 'Utilisateurs', path: '/users-management', keywords: ['user', 'collaborateur', 'équipe'] },
    { icon: FileText, label: 'Rapports', path: '/reports', keywords: ['rapport', 'statistique', 'analyse'] },
    { icon: FileText, label: 'Comptabilité', path: '/accounting', keywords: ['compte', 'finance', 'bilan'] },
    { icon: ShoppingCart, label: 'Achats', path: '/purchases', keywords: ['achat', 'commande', 'purchase'] },
    { icon: ShoppingCart, label: 'Ventes', path: '/sales', keywords: ['vente', 'facture', 'sale'] },
    { icon: Settings, label: 'Paramètres', path: '/settings', keywords: ['config', 'setting', 'préférence'] },
  ];

  // Filtrer les résultats
  const filteredItems = query.trim()
    ? searchItems.filter(item => {
        const q = query.toLowerCase();
        return (
          item.label.toLowerCase().includes(q) ||
          item.keywords.some(k => k.includes(q))
        );
      })
    : searchItems.slice(0, 6); // Afficher les 6 premiers par défaut

  // Focus sur l'input à l'ouverture
  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  // Reset à la fermeture
  useEffect(() => {
    if (!isOpen) {
      setQuery('');
      setSelectedIndex(0);
    }
  }, [isOpen]);

  // Navigation clavier
  const handleKeyDown = useCallback((e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex(prev => Math.min(prev + 1, filteredItems.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex(prev => Math.max(prev - 1, 0));
    } else if (e.key === 'Enter' && filteredItems[selectedIndex]) {
      e.preventDefault();
      navigate(filteredItems[selectedIndex].path);
      onClose();
    } else if (e.key === 'Escape') {
      onClose();
    }
  }, [filteredItems, selectedIndex, navigate, onClose]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50">
      {/* Overlay */}
      <div 
        className="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative max-w-xl mx-auto mt-20 animate-scale-in">
        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
          {/* Search input */}
          <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <Search className="text-gray-400" size={20} />
            <input
              ref={inputRef}
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value);
                setSelectedIndex(0);
              }}
              onKeyDown={handleKeyDown}
              placeholder="Rechercher une page, fonctionnalité..."
              className="flex-1 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 outline-none text-sm"
            />
            {query && (
              <button onClick={() => setQuery('')} className="text-gray-400 hover:text-gray-600">
                <X size={18} />
              </button>
            )}
            <kbd className="hidden sm:inline-flex items-center px-2 py-1 text-xs font-medium text-gray-400 bg-gray-100 dark:bg-gray-700 rounded">
              ESC
            </kbd>
          </div>

          {/* Results */}
          <div className="max-h-80 overflow-y-auto py-2">
            {filteredItems.length === 0 ? (
              <div className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <Search className="mx-auto mb-2 opacity-50" size={24} />
                <p className="text-sm">Aucun résultat pour "{query}"</p>
              </div>
            ) : (
              filteredItems.map((item, index) => {
                const Icon = item.icon;
                return (
                  <button
                    key={item.path}
                    onClick={() => {
                      navigate(item.path);
                      onClose();
                    }}
                    onMouseEnter={() => setSelectedIndex(index)}
                    className={`w-full flex items-center gap-3 px-4 py-3 text-left transition-colors ${
                      index === selectedIndex
                        ? 'bg-brand-50 dark:bg-brand-900/20 text-brand-700 dark:text-brand-300'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                    }`}
                  >
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                      index === selectedIndex
                        ? 'bg-brand-100 dark:bg-brand-800/30'
                        : 'bg-gray-100 dark:bg-gray-700'
                    }`}>
                      <Icon size={16} />
                    </div>
                    <span className="font-medium text-sm">{item.label}</span>
                  </button>
                );
              })
            )}
          </div>

          {/* Footer */}
          <div className="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <div className="flex items-center gap-4 text-xs text-gray-400">
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded">↑↓</kbd>
                naviguer
              </span>
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded">↵</kbd>
                ouvrir
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
});

export default GlobalSearch;
