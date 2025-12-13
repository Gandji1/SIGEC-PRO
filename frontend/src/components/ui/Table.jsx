import { memo, useState, useMemo } from 'react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Search } from 'lucide-react';

/**
 * Table responsive avec mode card mobile
 */
export const Table = memo(({ 
  columns, 
  data, 
  loading = false,
  emptyMessage = 'Aucune donnée disponible',
  onRowClick,
  mobileCardMode = true,
  className = ''
}) => {
  if (loading) {
    return <TableSkeleton columns={columns.length} />;
  }

  if (!data || data.length === 0) {
    return (
      <div className="text-center py-12 text-gray-500 dark:text-gray-400">
        <p>{emptyMessage}</p>
      </div>
    );
  }

  return (
    <div className={`overflow-x-auto ${className}`}>
      {/* Desktop Table */}
      <table className={`min-w-full divide-y divide-gray-200 dark:divide-gray-700 ${mobileCardMode ? 'hidden sm:table' : ''}`}>
        <thead className="bg-gray-50 dark:bg-gray-800/50">
          <tr>
            {columns.map((col, idx) => (
              <th 
                key={idx}
                className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
              >
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
          {data.map((row, rowIdx) => (
            <tr 
              key={rowIdx}
              onClick={() => onRowClick?.(row)}
              className={`hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
            >
              {columns.map((col, colIdx) => (
                <td key={colIdx} className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                  {col.render ? col.render(row[col.accessor], row) : row[col.accessor]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>

      {/* Mobile Card Mode */}
      {mobileCardMode && (
        <div className="sm:hidden space-y-3">
          {data.map((row, rowIdx) => (
            <div 
              key={rowIdx}
              onClick={() => onRowClick?.(row)}
              className={`bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700 ${onRowClick ? 'cursor-pointer active:scale-[0.98]' : ''} transition-transform`}
            >
              {columns.map((col, colIdx) => (
                <div key={colIdx} className="flex justify-between items-center py-1.5">
                  <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                    {col.header}
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white font-medium">
                    {col.render ? col.render(row[col.accessor], row) : row[col.accessor]}
                  </span>
                </div>
              ))}
            </div>
          ))}
        </div>
      )}
    </div>
  );
});

/**
 * Table Skeleton pour le chargement
 */
export const TableSkeleton = memo(({ columns = 5, rows = 5 }) => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded mb-2" />
    {Array.from({ length: rows }).map((_, i) => (
      <div key={i} className="h-12 bg-gray-100 dark:bg-gray-800 rounded mb-2" />
    ))}
  </div>
));

/**
 * Pagination component
 */
export const Pagination = memo(({ 
  currentPage, 
  totalPages, 
  onPageChange,
  totalItems,
  itemsPerPage,
  className = ''
}) => {
  const startItem = (currentPage - 1) * itemsPerPage + 1;
  const endItem = Math.min(currentPage * itemsPerPage, totalItems);

  return (
    <div className={`flex flex-col sm:flex-row items-center justify-between gap-4 ${className}`}>
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Affichage de <span className="font-medium">{startItem}</span> à <span className="font-medium">{endItem}</span> sur <span className="font-medium">{totalItems}</span> résultats
      </p>
      
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(1)}
          disabled={currentPage === 1}
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronsLeft size={18} className="text-gray-600 dark:text-gray-400" />
        </button>
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft size={18} className="text-gray-600 dark:text-gray-400" />
        </button>
        
        <span className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          {currentPage} / {totalPages}
        </span>
        
        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronRight size={18} className="text-gray-600 dark:text-gray-400" />
        </button>
        <button
          onClick={() => onPageChange(totalPages)}
          disabled={currentPage === totalPages}
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronsRight size={18} className="text-gray-600 dark:text-gray-400" />
        </button>
      </div>
    </div>
  );
});

/**
 * Search Input pour filtrer les tables
 */
export const SearchInput = memo(({ value, onChange, placeholder = 'Rechercher...', className = '' }) => (
  <div className={`relative ${className}`}>
    <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
    <input
      type="text"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder}
      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
    />
  </div>
));

export default Table;
