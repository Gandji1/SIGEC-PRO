import { memo } from 'react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';

/**
 * Composant Pagination réutilisable
 * @param {number} currentPage - Page actuelle (1-indexed)
 * @param {number} totalPages - Nombre total de pages
 * @param {number} totalItems - Nombre total d'éléments
 * @param {number} itemsPerPage - Éléments par page
 * @param {function} onPageChange - Callback changement de page
 * @param {boolean} showInfo - Afficher les infos (X sur Y)
 */
const Pagination = memo(({
  currentPage = 1,
  totalPages = 1,
  totalItems = 0,
  itemsPerPage = 10,
  onPageChange,
  showInfo = true,
}) => {
  if (totalPages <= 1) return null;

  const startItem = (currentPage - 1) * itemsPerPage + 1;
  const endItem = Math.min(currentPage * itemsPerPage, totalItems);

  // Générer les numéros de page à afficher
  const getPageNumbers = () => {
    const pages = [];
    const maxVisible = 5;
    
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    
    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }

    return pages;
  };

  const pageNumbers = getPageNumbers();

  const buttonClass = (active = false, disabled = false) => `
    w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition-all
    ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
    ${active 
      ? 'bg-brand-600 text-white shadow-md' 
      : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'
    }
  `;

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mt-4">
      {/* Info */}
      {showInfo && totalItems > 0 && (
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Affichage de <span className="font-medium text-gray-700 dark:text-gray-300">{startItem}</span> à{' '}
          <span className="font-medium text-gray-700 dark:text-gray-300">{endItem}</span> sur{' '}
          <span className="font-medium text-gray-700 dark:text-gray-300">{totalItems}</span> résultats
        </p>
      )}

      {/* Navigation */}
      <div className="flex items-center gap-1">
        {/* First page */}
        <button
          onClick={() => onPageChange(1)}
          disabled={currentPage === 1}
          className={buttonClass(false, currentPage === 1)}
          title="Première page"
        >
          <ChevronsLeft size={16} />
        </button>

        {/* Previous page */}
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className={buttonClass(false, currentPage === 1)}
          title="Page précédente"
        >
          <ChevronLeft size={16} />
        </button>

        {/* Page numbers */}
        {pageNumbers[0] > 1 && (
          <>
            <button onClick={() => onPageChange(1)} className={buttonClass(false)}>1</button>
            {pageNumbers[0] > 2 && <span className="px-2 text-gray-400">...</span>}
          </>
        )}

        {pageNumbers.map((page) => (
          <button
            key={page}
            onClick={() => onPageChange(page)}
            className={buttonClass(page === currentPage)}
          >
            {page}
          </button>
        ))}

        {pageNumbers[pageNumbers.length - 1] < totalPages && (
          <>
            {pageNumbers[pageNumbers.length - 1] < totalPages - 1 && (
              <span className="px-2 text-gray-400">...</span>
            )}
            <button onClick={() => onPageChange(totalPages)} className={buttonClass(false)}>
              {totalPages}
            </button>
          </>
        )}

        {/* Next page */}
        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className={buttonClass(false, currentPage === totalPages)}
          title="Page suivante"
        >
          <ChevronRight size={16} />
        </button>

        {/* Last page */}
        <button
          onClick={() => onPageChange(totalPages)}
          disabled={currentPage === totalPages}
          className={buttonClass(false, currentPage === totalPages)}
          title="Dernière page"
        >
          <ChevronsRight size={16} />
        </button>
      </div>
    </div>
  );
});

export default Pagination;
