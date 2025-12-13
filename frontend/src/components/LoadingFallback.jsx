import React from 'react';
import { Loader2 } from 'lucide-react';

/**
 * Composant de chargement réutilisable pour éviter les pages blanches
 */
export const LoadingSpinner = ({ size = 'md', className = '' }) => {
  const sizes = {
    sm: 'w-4 h-4',
    md: 'w-8 h-8',
    lg: 'w-12 h-12',
  };

  return (
    <Loader2 className={`animate-spin text-orange-500 ${sizes[size]} ${className}`} />
  );
};

export const LoadingFallback = ({ message = 'Chargement...', fullScreen = true }) => {
  const content = (
    <div className="flex flex-col items-center justify-center gap-3">
      <LoadingSpinner size="lg" />
      <p className="text-gray-600 text-sm font-medium">{message}</p>
    </div>
  );

  if (fullScreen) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        {content}
      </div>
    );
  }

  return (
    <div className="flex items-center justify-center p-8">
      {content}
    </div>
  );
};

export const PageLoader = ({ message }) => (
  <div className="min-h-[400px] flex items-center justify-center">
    <div className="text-center">
      <div className="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p className="text-gray-500">{message || 'Chargement des données...'}</p>
    </div>
  </div>
);

export const SkeletonCard = ({ count = 1 }) => (
  <>
    {Array.from({ length: count }).map((_, i) => (
      <div key={i} className="bg-white rounded-xl p-4 border animate-pulse">
        <div className="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
        <div className="h-8 bg-gray-200 rounded w-1/2"></div>
      </div>
    ))}
  </>
);

export const SkeletonTable = ({ rows = 5, cols = 4 }) => (
  <div className="bg-white rounded-xl border overflow-hidden animate-pulse">
    <div className="bg-gray-100 p-4">
      <div className="flex gap-4">
        {Array.from({ length: cols }).map((_, i) => (
          <div key={i} className="h-4 bg-gray-200 rounded flex-1"></div>
        ))}
      </div>
    </div>
    <div className="divide-y">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="p-4 flex gap-4">
          {Array.from({ length: cols }).map((_, j) => (
            <div key={j} className="h-4 bg-gray-200 rounded flex-1"></div>
          ))}
        </div>
      ))}
    </div>
  </div>
);

export const EmptyState = ({ 
  icon: Icon, 
  title = 'Aucune donnée', 
  message = 'Aucun élément à afficher pour le moment.',
  action,
  actionLabel
}) => (
  <div className="text-center py-12">
    {Icon && (
      <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <Icon className="w-8 h-8 text-gray-400" />
      </div>
    )}
    <h3 className="text-lg font-medium text-gray-800 mb-1">{title}</h3>
    <p className="text-gray-500 mb-4">{message}</p>
    {action && actionLabel && (
      <button
        onClick={action}
        className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition"
      >
        {actionLabel}
      </button>
    )}
  </div>
);

export const ErrorMessage = ({ 
  message = 'Une erreur est survenue', 
  onRetry,
  className = ''
}) => (
  <div className={`bg-red-50 border border-red-200 rounded-lg p-4 ${className}`}>
    <div className="flex items-center gap-3">
      <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
        <span className="text-red-600 text-lg">!</span>
      </div>
      <div className="flex-1">
        <p className="text-red-700 font-medium">{message}</p>
      </div>
      {onRetry && (
        <button
          onClick={onRetry}
          className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition"
        >
          Réessayer
        </button>
      )}
    </div>
  </div>
);

export default LoadingFallback;
