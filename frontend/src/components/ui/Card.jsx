import { memo } from 'react';

/**
 * Card component optimisé avec support dark mode
 */
export const Card = memo(({ children, className = '', hover = true, padding = 'p-6', ...props }) => (
  <div 
    className={`bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 transition-all duration-200 ${hover ? 'hover:shadow-md' : ''} ${padding} ${className}`}
    {...props}
  >
    {children}
  </div>
));

/**
 * Card Header
 */
export const CardHeader = memo(({ children, className = '' }) => (
  <div className={`border-b border-gray-100 dark:border-gray-700 pb-4 mb-4 ${className}`}>
    {children}
  </div>
));

/**
 * Card Title
 */
export const CardTitle = memo(({ children, className = '' }) => (
  <h3 className={`text-lg font-bold text-gray-900 dark:text-white ${className}`}>
    {children}
  </h3>
));

/**
 * Card Description
 */
export const CardDescription = memo(({ children, className = '' }) => (
  <p className={`text-sm text-gray-500 dark:text-gray-400 mt-1 ${className}`}>
    {children}
  </p>
));

/**
 * Card Content
 */
export const CardContent = memo(({ children, className = '' }) => (
  <div className={className}>
    {children}
  </div>
));

/**
 * Card Footer
 */
export const CardFooter = memo(({ children, className = '' }) => (
  <div className={`border-t border-gray-100 dark:border-gray-700 pt-4 mt-4 ${className}`}>
    {children}
  </div>
));

/**
 * Stat Card - pour les KPIs
 */
export const StatCard = memo(({ 
  title, 
  value, 
  icon: Icon, 
  trend, 
  trendValue, 
  color = 'blue',
  className = '' 
}) => {
  const colorMap = {
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
    purple: 'bg-purple-500',
    orange: 'bg-orange-500',
    indigo: 'bg-indigo-500',
    pink: 'bg-pink-500',
  };

  const trendColors = {
    up: 'text-green-600 dark:text-green-400',
    down: 'text-red-600 dark:text-red-400',
    neutral: 'text-gray-500 dark:text-gray-400',
  };

  return (
    <Card className={`animate-fade-in ${className}`} padding="p-5">
      <div className="flex items-center gap-4">
        {Icon && (
          <div className={`w-12 h-12 ${colorMap[color]} rounded-xl flex items-center justify-center shadow-lg`}>
            <Icon className="text-white" size={24} />
          </div>
        )}
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{title}</p>
          <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">{value}</p>
          {trend && (
            <p className={`text-xs mt-1 ${trendColors[trend]}`}>
              {trend === 'up' ? '↑' : trend === 'down' ? '↓' : '→'} {trendValue}
            </p>
          )}
        </div>
      </div>
    </Card>
  );
});

export default Card;
