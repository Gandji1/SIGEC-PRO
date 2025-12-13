import { memo, useState, useEffect } from 'react';
import { Search, X } from 'lucide-react';

/**
 * Champ de recherche avec debounce
 * @param {string} value - Valeur contrôlée
 * @param {function} onChange - Callback de changement
 * @param {string} placeholder - Placeholder
 * @param {number} debounceMs - Délai de debounce en ms (0 = pas de debounce)
 * @param {boolean} autoFocus - Focus automatique
 * @param {string} className - Classes CSS additionnelles
 */
const SearchInput = memo(({
  value = '',
  onChange,
  placeholder = 'Rechercher...',
  debounceMs = 300,
  autoFocus = false,
  className = '',
}) => {
  const [localValue, setLocalValue] = useState(value);

  // Synchroniser avec la valeur externe
  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  // Debounce
  useEffect(() => {
    if (debounceMs === 0) return;
    
    const timer = setTimeout(() => {
      if (localValue !== value) {
        onChange?.(localValue);
      }
    }, debounceMs);

    return () => clearTimeout(timer);
  }, [localValue, debounceMs, onChange, value]);

  const handleChange = (e) => {
    const newValue = e.target.value;
    setLocalValue(newValue);
    
    // Si pas de debounce, appeler onChange immédiatement
    if (debounceMs === 0) {
      onChange?.(newValue);
    }
  };

  const handleClear = () => {
    setLocalValue('');
    onChange?.('');
  };

  return (
    <div className={`relative ${className}`}>
      <Search 
        size={18} 
        className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none" 
      />
      <input
        type="text"
        value={localValue}
        onChange={handleChange}
        placeholder={placeholder}
        autoFocus={autoFocus}
        className="w-full pl-10 pr-10 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
      />
      {localValue && (
        <button
          onClick={handleClear}
          className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
        >
          <X size={16} />
        </button>
      )}
    </div>
  );
});

export default SearchInput;
