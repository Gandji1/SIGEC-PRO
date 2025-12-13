import { memo, forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

/**
 * Button component optimisé
 */
export const Button = memo(forwardRef(({ 
  children, 
  variant = 'primary', 
  size = 'md',
  loading = false,
  disabled = false,
  icon: Icon,
  iconPosition = 'left',
  className = '',
  ...props 
}, ref) => {
  const variants = {
    primary: 'bg-brand-600 hover:bg-brand-700 text-white focus:ring-brand-500 shadow-sm hover:shadow',
    secondary: 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 focus:ring-gray-500',
    success: 'bg-green-600 hover:bg-green-700 text-white focus:ring-green-500 shadow-sm',
    danger: 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500 shadow-sm',
    warning: 'bg-yellow-500 hover:bg-yellow-600 text-white focus:ring-yellow-500 shadow-sm',
    ghost: 'bg-transparent hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300',
    outline: 'border-2 border-brand-600 text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20',
  };

  const sizes = {
    xs: 'px-2.5 py-1.5 text-xs',
    sm: 'px-3 py-2 text-sm',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-6 py-3 text-base',
    xl: 'px-8 py-4 text-lg',
  };

  const isDisabled = disabled || loading;

  return (
    <button
      ref={ref}
      disabled={isDisabled}
      className={`
        inline-flex items-center justify-center gap-2 rounded-xl font-medium 
        transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 
        dark:focus:ring-offset-gray-900
        disabled:opacity-50 disabled:cursor-not-allowed
        ${variants[variant]}
        ${sizes[size]}
        ${className}
      `}
      {...props}
    >
      {loading && <Loader2 size={size === 'xs' ? 14 : size === 'sm' ? 16 : 18} className="animate-spin" />}
      {!loading && Icon && iconPosition === 'left' && <Icon size={size === 'xs' ? 14 : size === 'sm' ? 16 : 18} />}
      {children}
      {!loading && Icon && iconPosition === 'right' && <Icon size={size === 'xs' ? 14 : size === 'sm' ? 16 : 18} />}
    </button>
  );
}));

/**
 * Icon Button - bouton avec icône uniquement
 */
export const IconButton = memo(forwardRef(({ 
  icon: Icon, 
  variant = 'ghost',
  size = 'md',
  loading = false,
  disabled = false,
  className = '',
  ...props 
}, ref) => {
  const variants = {
    primary: 'bg-brand-600 hover:bg-brand-700 text-white',
    secondary: 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200',
    ghost: 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300',
    danger: 'hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400',
  };

  const sizes = {
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-3',
  };

  const iconSizes = {
    sm: 16,
    md: 20,
    lg: 24,
  };

  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={`
        rounded-lg transition-all duration-200 
        disabled:opacity-50 disabled:cursor-not-allowed
        ${variants[variant]}
        ${sizes[size]}
        ${className}
      `}
      {...props}
    >
      {loading ? (
        <Loader2 size={iconSizes[size]} className="animate-spin" />
      ) : (
        <Icon size={iconSizes[size]} />
      )}
    </button>
  );
}));

/**
 * Button Group
 */
export const ButtonGroup = memo(({ children, className = '' }) => (
  <div className={`inline-flex rounded-xl overflow-hidden shadow-sm ${className}`}>
    {children}
  </div>
));

export default Button;
