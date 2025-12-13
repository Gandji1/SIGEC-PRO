import { memo } from 'react';

// Composants skeleton ultra-légers pour chargement instantané avec dark mode
export const CardSkeleton = memo(() => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm animate-pulse border border-gray-100 dark:border-gray-700">
    <div className="flex items-center gap-4">
      <div className="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
      <div className="flex-1">
        <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2"></div>
        <div className="h-7 bg-gray-200 dark:bg-gray-700 rounded w-28"></div>
      </div>
    </div>
  </div>
));

export const TableSkeleton = memo(({ rows = 5 }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
    <div className="h-12 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700"></div>
    {Array.from({ length: rows }).map((_, i) => (
      <div key={i} className="h-14 border-b border-gray-100 dark:border-gray-700 flex items-center px-4 gap-4 animate-pulse">
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/6"></div>
      </div>
    ))}
  </div>
));

export const DashboardSkeleton = memo(() => (
  <div className="p-4 sm:p-6 space-y-6 animate-pulse bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div className="h-8 sm:h-10 bg-gray-200 dark:bg-gray-700 rounded-lg w-48 sm:w-64 mb-2"></div>
    <div className="h-4 bg-gray-100 dark:bg-gray-800 rounded w-32 sm:w-48"></div>
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      {[1, 2, 3, 4].map(i => <CardSkeleton key={i} />)}
    </div>
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
      <div className="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 dark:border-gray-700 h-48 sm:h-64">
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-32 mb-4"></div>
        <div className="h-32 sm:h-48 bg-gray-100 dark:bg-gray-700/50 rounded-lg"></div>
      </div>
      <div className="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 dark:border-gray-700 h-48 sm:h-64">
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-32 mb-4"></div>
        <div className="h-32 sm:h-48 bg-gray-100 dark:bg-gray-700/50 rounded-lg"></div>
      </div>
    </div>
  </div>
));

export const PageSkeleton = memo(() => (
  <div className="p-4 sm:p-6 animate-pulse bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-48 mb-6"></div>
    <TableSkeleton />
  </div>
));

export const Spinner = memo(({ size = 'md', className = '' }) => {
  const sizes = { sm: 'h-4 w-4', md: 'h-8 w-8', lg: 'h-12 w-12' };
  return (
    <div className={`${sizes[size]} animate-spin rounded-full border-2 border-gray-200 dark:border-gray-700 border-t-brand-600 ${className}`}></div>
  );
});

// Skeleton pour les stats
export const StatSkeleton = memo(() => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-5 animate-pulse border border-gray-100 dark:border-gray-700">
    <div className="flex items-center gap-4">
      <div className="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
      <div className="flex-1">
        <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2"></div>
        <div className="h-7 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
      </div>
    </div>
  </div>
));

// Skeleton pour les listes
export const ListSkeleton = memo(({ items = 5 }) => (
  <div className="space-y-3 animate-pulse">
    {Array.from({ length: items }).map((_, i) => (
      <div key={i} className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
          <div className="flex-1">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
            <div className="h-3 bg-gray-100 dark:bg-gray-700/50 rounded w-1/2"></div>
          </div>
        </div>
      </div>
    ))}
  </div>
));

export default {
  CardSkeleton,
  TableSkeleton,
  DashboardSkeleton,
  PageSkeleton,
  Spinner,
  StatSkeleton,
  ListSkeleton
};
