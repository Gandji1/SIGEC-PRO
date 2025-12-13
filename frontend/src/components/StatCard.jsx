import { memo } from 'react';
import { TrendingUp, TrendingDown } from 'lucide-react';

/**
 * Carte de statistique réutilisable
 * @param {string} title - Titre de la statistique
 * @param {string|number} value - Valeur à afficher
 * @param {ReactNode} icon - Icône Lucide
 * @param {string} color - Couleur (blue, green, orange, purple, red)
 * @param {string} trend - Tendance ('up' | 'down' | null)
 * @param {string} trendValue - Valeur de la tendance (ex: '+12%')
 * @param {string} subtitle - Sous-titre optionnel
 * @param {boolean} loading - État de chargement
 */
const StatCard = memo(({
  title,
  value,
  icon: Icon,
  color = 'blue',
  trend,
  trendValue,
  subtitle,
  loading = false,
}) => {
  const colors = {
    blue: {
      bg: 'bg-blue-50 dark:bg-blue-900/20',
      icon: 'bg-blue-100 dark:bg-blue-800/30 text-blue-600 dark:text-blue-400',
      trend: 'text-blue-600',
    },
    green: {
      bg: 'bg-green-50 dark:bg-green-900/20',
      icon: 'bg-green-100 dark:bg-green-800/30 text-green-600 dark:text-green-400',
      trend: 'text-green-600',
    },
    orange: {
      bg: 'bg-orange-50 dark:bg-orange-900/20',
      icon: 'bg-orange-100 dark:bg-orange-800/30 text-orange-600 dark:text-orange-400',
      trend: 'text-orange-600',
    },
    purple: {
      bg: 'bg-purple-50 dark:bg-purple-900/20',
      icon: 'bg-purple-100 dark:bg-purple-800/30 text-purple-600 dark:text-purple-400',
      trend: 'text-purple-600',
    },
    red: {
      bg: 'bg-red-50 dark:bg-red-900/20',
      icon: 'bg-red-100 dark:bg-red-800/30 text-red-600 dark:text-red-400',
      trend: 'text-red-600',
    },
  };

  const colorConfig = colors[color] || colors.blue;

  if (loading) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700 animate-pulse">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-xl" />
          <div className="flex-1">
            <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2" />
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-24" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-center gap-4">
        {/* Icon */}
        <div className={`w-12 h-12 ${colorConfig.icon} rounded-xl flex items-center justify-center flex-shrink-0`}>
          {Icon && <Icon size={22} />}
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">
            {title}
          </p>
          <p className="text-xl font-bold text-gray-900 dark:text-white mt-0.5 truncate">
            {value}
          </p>
          
          {/* Trend or Subtitle */}
          {(trend || subtitle) && (
            <div className="flex items-center gap-1 mt-1">
              {trend && (
                <>
                  {trend === 'up' ? (
                    <TrendingUp size={14} className="text-green-500" />
                  ) : (
                    <TrendingDown size={14} className="text-red-500" />
                  )}
                  <span className={`text-xs font-medium ${trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
                    {trendValue}
                  </span>
                </>
              )}
              {subtitle && !trend && (
                <span className="text-xs text-gray-400 dark:text-gray-500">{subtitle}</span>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
});

export default StatCard;
