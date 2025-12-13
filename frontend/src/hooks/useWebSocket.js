import { useEffect, useState, useCallback } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import websocketService from '../services/websocket';

/**
 * Hook React pour utiliser le WebSocket
 */
export function useWebSocket() {
  const { user, tenant } = useTenantStore();
  const [isConnected, setIsConnected] = useState(false);

  useEffect(() => {
    if (user?.id && tenant?.id) {
      websocketService.connect(tenant.id, user.id)
        .then(() => setIsConnected(true))
        .catch(() => setIsConnected(false));

      const unsubConnected = websocketService.on('connected', () => setIsConnected(true));
      const unsubDisconnected = websocketService.on('disconnected', () => setIsConnected(false));

      return () => {
        unsubConnected();
        unsubDisconnected();
      };
    }
  }, [user?.id, tenant?.id]);

  const subscribe = useCallback((event, callback) => {
    return websocketService.on(event, callback);
  }, []);

  const send = useCallback((type, data) => {
    websocketService.send(type, data);
  }, []);

  return { isConnected, subscribe, send, ws: websocketService };
}

/**
 * Hook pour les commandes POS en temps réel
 */
export function usePosOrdersRealtime(onOrderUpdate) {
  const { subscribe } = useWebSocket();

  useEffect(() => {
    if (!onOrderUpdate) return;
    
    const unsubs = [
      subscribe('orderCreated', onOrderUpdate),
      subscribe('orderUpdated', onOrderUpdate),
      subscribe('orderApproved', onOrderUpdate),
      subscribe('orderReady', onOrderUpdate),
      subscribe('orderServed', onOrderUpdate),
      subscribe('paymentValidated', onOrderUpdate),
    ];

    return () => unsubs.forEach(unsub => unsub?.());
  }, [subscribe, onOrderUpdate]);
}

export default useWebSocket;
