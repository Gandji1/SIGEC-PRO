import React, { memo } from 'react';

/**
 * Composants Skeleton pour affichage instantané pendant le chargement
 */

// Skeleton de base
export const Skeleton = memo(({ className = '', width, height, rounded = 'md' }) => {
  const roundedClass = {
    none: '',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full',
  }[rounded] || 'rounded-md';

  return (
    <div 
      className={`animate-pulse bg-gray-200 dark:bg-gray-700 ${roundedClass} ${className}`}
      style={{ width, height }}
    />
  );
});

// Skeleton pour texte
export const TextSkeleton = memo(({ lines = 1, className = '' }) => (
  <div className={`space-y-2 ${className}`}>
    {Array.from({ length: lines }).map((_, i) => (
      <Skeleton 
        key={i} 
        className="h-4" 
        width={i === lines - 1 && lines > 1 ? '60%' : '100%'} 
      />
    ))}
  </div>
));

// Skeleton pour carte de statistique
export const StatCardSkeleton = memo(({ count = 4 }) => (
  <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
    {Array.from({ length: count }).map((_, i) => (
      <div key={i} className="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm animate-pulse">
        <div className="flex items-center gap-3 mb-3">
          <Skeleton className="w-10 h-10" rounded="lg" />
          <Skeleton className="h-3 w-20" />
        </div>
        <Skeleton className="h-6 w-24 mb-1" />
        <Skeleton className="h-3 w-16" />
      </div>
    ))}
  </div>
));

// Skeleton pour tableau
export const TableSkeleton = memo(({ rows = 5, cols = 5 }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
    {/* Header */}
    <div className="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 flex gap-4 border-b border-gray-200 dark:border-gray-700">
      {Array.from({ length: cols }).map((_, i) => (
        <Skeleton key={i} className="h-4 flex-1" />
      ))}
    </div>
    {/* Rows */}
    {Array.from({ length: rows }).map((_, rowIdx) => (
      <div key={rowIdx} className="px-4 py-3 flex gap-4 border-b border-gray-100 dark:border-gray-700/50">
        {Array.from({ length: cols }).map((_, colIdx) => (
          <Skeleton key={colIdx} className="h-4 flex-1" />
        ))}
      </div>
    ))}
  </div>
));

// Skeleton pour liste de produits (POS)
export const ProductGridSkeleton = memo(({ count = 12 }) => (
  <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2">
    {Array.from({ length: count }).map((_, i) => (
      <div key={i} className="bg-white dark:bg-gray-800 rounded-lg p-3 animate-pulse">
        <Skeleton className="w-full h-16 mb-2" rounded="lg" />
        <Skeleton className="h-3 w-full mb-1" />
        <Skeleton className="h-4 w-16" />
      </div>
    ))}
  </div>
));

// Skeleton pour carte de commande
export const OrderCardSkeleton = memo(({ count = 3 }) => (
  <div className="space-y-4">
    {Array.from({ length: count }).map((_, i) => (
      <div key={i} className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden animate-pulse">
        <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-between">
          <Skeleton className="h-5 w-24" />
          <div className="flex gap-2">
            <Skeleton className="h-5 w-20" rounded="full" />
            <Skeleton className="h-5 w-20" rounded="full" />
          </div>
        </div>
        <div className="p-4 space-y-2">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-3/4" />
          <Skeleton className="h-4 w-1/2" />
        </div>
        <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-end gap-2">
          <Skeleton className="h-8 w-24" rounded="lg" />
          <Skeleton className="h-8 w-24" rounded="lg" />
        </div>
      </div>
    ))}
  </div>
));

// Skeleton pour formulaire
export const FormSkeleton = memo(({ fields = 4 }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm space-y-4">
    {Array.from({ length: fields }).map((_, i) => (
      <div key={i}>
        <Skeleton className="h-4 w-24 mb-2" />
        <Skeleton className="h-10 w-full" rounded="lg" />
      </div>
    ))}
    <div className="flex justify-end gap-3 pt-4">
      <Skeleton className="h-10 w-24" rounded="lg" />
      <Skeleton className="h-10 w-32" rounded="lg" />
    </div>
  </div>
));

// Skeleton pour dashboard
export const DashboardSkeleton = memo(() => (
  <div className="space-y-6 animate-fade-in">
    {/* Stats */}
    <StatCardSkeleton count={4} />
    
    {/* Charts */}
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
        <Skeleton className="h-5 w-40 mb-4" />
        <Skeleton className="h-64 w-full" rounded="lg" />
      </div>
      <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
        <Skeleton className="h-5 w-40 mb-4" />
        <Skeleton className="h-64 w-full" rounded="lg" />
      </div>
    </div>
    
    {/* Table */}
    <TableSkeleton rows={5} cols={5} />
  </div>
));

// Skeleton pour page de liste
export const ListPageSkeleton = memo(() => (
  <div className="space-y-4 animate-fade-in">
    {/* Header */}
    <div className="flex justify-between items-center">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-10 w-32" rounded="lg" />
    </div>
    
    {/* Filters */}
    <div className="flex gap-4">
      <Skeleton className="h-10 w-64" rounded="lg" />
      <Skeleton className="h-10 w-32" rounded="lg" />
      <Skeleton className="h-10 w-32" rounded="lg" />
    </div>
    
    {/* Table */}
    <TableSkeleton rows={10} cols={6} />
    
    {/* Pagination */}
    <div className="flex justify-between items-center">
      <Skeleton className="h-4 w-40" />
      <div className="flex gap-2">
        <Skeleton className="h-8 w-8" rounded="md" />
        <Skeleton className="h-8 w-8" rounded="md" />
        <Skeleton className="h-8 w-8" rounded="md" />
      </div>
    </div>
  </div>
));

// Skeleton pour POS
export const POSSkeleton = memo(() => (
  <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 animate-fade-in">
    {/* Products */}
    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl p-4">
      <div className="flex gap-4 mb-4">
        <Skeleton className="h-10 flex-1" rounded="lg" />
        <Skeleton className="h-10 w-32" rounded="lg" />
      </div>
      <ProductGridSkeleton count={12} />
    </div>
    
    {/* Cart */}
    <div className="bg-white dark:bg-gray-800 rounded-xl p-4">
      <Skeleton className="h-6 w-32 mb-4" />
      <div className="space-y-3 mb-4">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="flex justify-between items-center">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-4 w-20" />
          </div>
        ))}
      </div>
      <Skeleton className="h-px w-full mb-4" />
      <div className="flex justify-between mb-4">
        <Skeleton className="h-6 w-20" />
        <Skeleton className="h-6 w-24" />
      </div>
      <Skeleton className="h-12 w-full" rounded="lg" />
    </div>
  </div>
));

export default {
  Skeleton,
  TextSkeleton,
  StatCardSkeleton,
  TableSkeleton,
  ProductGridSkeleton,
  OrderCardSkeleton,
  FormSkeleton,
  DashboardSkeleton,
  ListPageSkeleton,
  POSSkeleton,
};
