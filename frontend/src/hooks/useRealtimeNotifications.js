import { useEffect, useCallback, useState } from 'react';
import websocketService from '../services/websocket';
import { useTenantStore } from '../stores/tenantStore';

/**
 * Hook pour gérer les notifications temps réel via WebSocket
 * S'intègre avec le service WebSocket existant
 */
export function useRealtimeNotifications() {
  const { user, tenant } = useTenantStore();
  const [notifications, setNotifications] = useState([]);
  const [isConnected, setIsConnected] = useState(false);

  // Ajouter une notification
  const addNotification = useCallback((notification) => {
    const id = Date.now() + Math.random();
    const newNotification = {
      id,
      timestamp: new Date(),
      ...notification,
    };
    
    setNotifications((prev) => [...prev.slice(-9), newNotification]); // Garder max 10
    
    // Jouer un son si activé
    if (notification.sound !== false) {
      playNotificationSound(notification.type);
    }
    
    return id;
  }, []);

  // Supprimer une notification
  const removeNotification = useCallback((id) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  // Vider toutes les notifications
  const clearAll = useCallback(() => {
    setNotifications([]);
  }, []);

  // Connexion WebSocket
  useEffect(() => {
    if (!user?.id || !tenant?.id) return;

    const connect = async () => {
      try {
        await websocketService.connect(tenant.id, user.id);
        setIsConnected(true);
      } catch (error) {
        console.warn('[Notifications] WebSocket connection failed, using polling');
        setIsConnected(false);
      }
    };

    connect();

    // Listeners pour les événements WebSocket
    const unsubscribers = [
      websocketService.on('connected', () => setIsConnected(true)),
      websocketService.on('disconnected', () => setIsConnected(false)),
      
      // Commandes POS
      websocketService.on('orderCreated', (data) => {
        addNotification({
          type: 'order',
          title: 'Nouvelle commande',
          message: `Commande #${data.order_number || data.id} créée`,
          data,
        });
      }),
      
      websocketService.on('orderApproved', (data) => {
        addNotification({
          type: 'success',
          title: 'Commande approuvée',
          message: `Commande #${data.order_number || data.id} approuvée`,
          data,
        });
      }),
      
      websocketService.on('orderReady', (data) => {
        addNotification({
          type: 'success',
          title: 'Commande prête',
          message: `Commande #${data.order_number || data.id} prête à servir`,
          data,
        });
      }),
      
      websocketService.on('paymentValidated', (data) => {
        addNotification({
          type: 'payment',
          title: 'Paiement validé',
          message: `Paiement de ${data.amount} ${data.currency || 'XOF'} reçu`,
          data,
        });
      }),
      
      // Stock
      websocketService.on('stockUpdated', (data) => {
        if (data.alert) {
          addNotification({
            type: 'stock',
            title: 'Alerte stock',
            message: data.message || `Stock bas pour ${data.product_name}`,
            data,
          });
        }
      }),
      
      // Notifications génériques
      websocketService.on('notification', (data) => {
        addNotification({
          type: data.type || 'info',
          title: data.title,
          message: data.message,
          data,
        });
      }),
    ];

    return () => {
      unsubscribers.forEach((unsub) => unsub?.());
      websocketService.disconnect();
    };
  }, [user?.id, tenant?.id, addNotification]);

  return {
    notifications,
    isConnected,
    addNotification,
    removeNotification,
    clearAll,
  };
}

/**
 * Jouer un son de notification
 */
function playNotificationSound(type) {
  try {
    // Utiliser l'API Web Audio pour un son simple
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    // Fréquences selon le type
    const frequencies = {
      success: 880,
      error: 220,
      warning: 440,
      order: 660,
      payment: 1000,
      stock: 330,
      info: 550,
    };
    
    oscillator.frequency.value = frequencies[type] || 550;
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
  } catch (e) {
    // Silently fail if audio not supported
  }
}

export default useRealtimeNotifications;
