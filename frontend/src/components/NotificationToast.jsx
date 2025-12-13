import { useState, useEffect, useCallback } from 'react';
import { X, Bell, CheckCircle, AlertTriangle, Info, ShoppingCart, Package, DollarSign } from 'lucide-react';

/**
 * Composant de notifications toast temps réel
 * S'intègre avec le WebSocket pour afficher les notifications
 */
export default function NotificationToast({ notifications = [], onDismiss }) {
  return (
    <div className="fixed top-4 right-4 z-50 space-y-2 max-w-sm">
      {notifications.map((notification) => (
        <Toast
          key={notification.id}
          notification={notification}
          onDismiss={() => onDismiss(notification.id)}
        />
      ))}
    </div>
  );
}

function Toast({ notification, onDismiss }) {
  const [isVisible, setIsVisible] = useState(false);
  const [isLeaving, setIsLeaving] = useState(false);

  useEffect(() => {
    // Animation d'entrée
    setTimeout(() => setIsVisible(true), 10);

    // Auto-dismiss après 5 secondes
    const timer = setTimeout(() => {
      handleDismiss();
    }, notification.duration || 5000);

    return () => clearTimeout(timer);
  }, []);

  const handleDismiss = useCallback(() => {
    setIsLeaving(true);
    setTimeout(() => {
      onDismiss();
    }, 300);
  }, [onDismiss]);

  const getIcon = () => {
    switch (notification.type) {
      case 'success':
        return <CheckCircle className="w-5 h-5 text-green-500" />;
      case 'warning':
        return <AlertTriangle className="w-5 h-5 text-yellow-500" />;
      case 'error':
        return <AlertTriangle className="w-5 h-5 text-red-500" />;
      case 'order':
        return <ShoppingCart className="w-5 h-5 text-blue-500" />;
      case 'stock':
        return <Package className="w-5 h-5 text-purple-500" />;
      case 'payment':
        return <DollarSign className="w-5 h-5 text-green-500" />;
      default:
        return <Info className="w-5 h-5 text-blue-500" />;
    }
  };

  const getBgColor = () => {
    switch (notification.type) {
      case 'success':
        return 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800';
      case 'warning':
        return 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800';
      case 'error':
        return 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
      case 'order':
        return 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800';
      case 'stock':
        return 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800';
      case 'payment':
        return 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800';
      default:
        return 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700';
    }
  };

  return (
    <div
      className={`
        ${getBgColor()}
        border rounded-lg shadow-lg p-4 
        transform transition-all duration-300 ease-out
        ${isVisible && !isLeaving ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'}
      `}
    >
      <div className="flex items-start gap-3">
        <div className="flex-shrink-0 mt-0.5">
          {getIcon()}
        </div>
        <div className="flex-1 min-w-0">
          {notification.title && (
            <p className="font-medium text-gray-900 dark:text-white text-sm">
              {notification.title}
            </p>
          )}
          <p className="text-sm text-gray-600 dark:text-gray-300">
            {notification.message}
          </p>
          {notification.action && (
            <button
              onClick={notification.action.onClick}
              className="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
            >
              {notification.action.label}
            </button>
          )}
        </div>
        <button
          onClick={handleDismiss}
          className="flex-shrink-0 p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition"
        >
          <X className="w-4 h-4 text-gray-500" />
        </button>
      </div>
    </div>
  );
}

/**
 * Hook pour gérer les notifications
 */
export function useNotifications() {
  const [notifications, setNotifications] = useState([]);

  const addNotification = useCallback((notification) => {
    const id = Date.now() + Math.random();
    setNotifications((prev) => [...prev, { ...notification, id }]);
    return id;
  }, []);

  const removeNotification = useCallback((id) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  const clearAll = useCallback(() => {
    setNotifications([]);
  }, []);

  // Helpers pour différents types
  const success = useCallback((message, title) => {
    return addNotification({ type: 'success', message, title });
  }, [addNotification]);

  const error = useCallback((message, title) => {
    return addNotification({ type: 'error', message, title, duration: 8000 });
  }, [addNotification]);

  const warning = useCallback((message, title) => {
    return addNotification({ type: 'warning', message, title });
  }, [addNotification]);

  const info = useCallback((message, title) => {
    return addNotification({ type: 'info', message, title });
  }, [addNotification]);

  const order = useCallback((message, title, action) => {
    return addNotification({ type: 'order', message, title, action, duration: 10000 });
  }, [addNotification]);

  const stock = useCallback((message, title) => {
    return addNotification({ type: 'stock', message, title, duration: 8000 });
  }, [addNotification]);

  const payment = useCallback((message, title) => {
    return addNotification({ type: 'payment', message, title });
  }, [addNotification]);

  return {
    notifications,
    addNotification,
    removeNotification,
    clearAll,
    success,
    error,
    warning,
    info,
    order,
    stock,
    payment,
  };
}
