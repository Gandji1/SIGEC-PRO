/**
 * Service WebSocket pour SIGEC
 * Gère les connexions temps réel pour POS, notifications, etc.
 */

class WebSocketService {
  constructor() {
    this.ws = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 1000;
    this.listeners = new Map();
    this.isConnected = false;
    this.pendingMessages = [];
  }

  /**
   * Connexion au serveur WebSocket
   */
  connect(tenantId, userId) {
    if (this.ws?.readyState === WebSocket.OPEN) {
      return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
      const wsUrl = import.meta.env.VITE_WS_URL || 
        `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws`;
      
      try {
        this.ws = new WebSocket(`${wsUrl}?tenant=${tenantId}&user=${userId}`);
        
        this.ws.onopen = () => {
          console.log('[WebSocket] Connected');
          this.isConnected = true;
          this.reconnectAttempts = 0;
          
          // Envoyer les messages en attente
          this.pendingMessages.forEach(msg => this.send(msg.type, msg.data));
          this.pendingMessages = [];
          
          this.emit('connected', { tenantId, userId });
          resolve();
        };

        this.ws.onmessage = (event) => {
          try {
            const message = JSON.parse(event.data);
            this.handleMessage(message);
          } catch (e) {
            console.error('[WebSocket] Parse error:', e);
          }
        };

        this.ws.onclose = (event) => {
          console.log('[WebSocket] Disconnected:', event.code);
          this.isConnected = false;
          this.emit('disconnected', { code: event.code });
          
          // Reconnexion automatique
          if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => this.connect(tenantId, userId), 
              this.reconnectDelay * this.reconnectAttempts);
          }
        };

        this.ws.onerror = (error) => {
          console.error('[WebSocket] Error:', error);
          this.emit('error', error);
          reject(error);
        };
      } catch (error) {
        // Fallback: utiliser polling si WebSocket non disponible
        console.warn('[WebSocket] Not available, using polling fallback');
        this.useFallbackPolling(tenantId, userId);
        resolve();
      }
    });
  }

  /**
   * Fallback polling si WebSocket non disponible
   */
  useFallbackPolling(tenantId, userId) {
    this.pollingInterval = setInterval(() => {
      this.emit('poll', { tenantId, userId });
    }, 5000); // Poll toutes les 5 secondes
  }

  /**
   * Déconnexion
   */
  disconnect() {
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
    }
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.isConnected = false;
    this.listeners.clear();
  }

  /**
   * Envoyer un message
   */
  send(type, data) {
    const message = { type, data, timestamp: Date.now() };
    
    if (this.ws?.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
    } else {
      // Stocker pour envoi ultérieur
      this.pendingMessages.push(message);
    }
  }

  /**
   * Gérer les messages reçus
   */
  handleMessage(message) {
    const { type, data } = message;
    
    switch (type) {
      case 'pos_order_created':
        this.emit('orderCreated', data);
        break;
      case 'pos_order_updated':
        this.emit('orderUpdated', data);
        break;
      case 'pos_order_approved':
        this.emit('orderApproved', data);
        break;
      case 'pos_order_ready':
        this.emit('orderReady', data);
        break;
      case 'pos_order_served':
        this.emit('orderServed', data);
        break;
      case 'pos_payment_validated':
        this.emit('paymentValidated', data);
        break;
      case 'stock_updated':
        this.emit('stockUpdated', data);
        break;
      case 'notification':
        this.emit('notification', data);
        break;
      case 'ping':
        this.send('pong', {});
        break;
      default:
        this.emit(type, data);
    }
  }

  /**
   * Écouter un événement
   */
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event).add(callback);
    
    // Retourner une fonction pour se désabonner
    return () => this.off(event, callback);
  }

  /**
   * Arrêter d'écouter un événement
   */
  off(event, callback) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).delete(callback);
    }
  }

  /**
   * Émettre un événement
   */
  emit(event, data) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach(callback => {
        try {
          callback(data);
        } catch (e) {
          console.error('[WebSocket] Listener error:', e);
        }
      });
    }
  }

  /**
   * Abonnement aux commandes POS
   */
  subscribeToPosOrders(posId) {
    this.send('subscribe', { channel: 'pos_orders', posId });
  }

  /**
   * Désabonnement des commandes POS
   */
  unsubscribeFromPosOrders(posId) {
    this.send('unsubscribe', { channel: 'pos_orders', posId });
  }

  /**
   * Notifier une nouvelle commande
   */
  notifyNewOrder(order) {
    this.send('pos_order_created', order);
  }

  /**
   * Notifier un changement de statut
   */
  notifyOrderStatusChange(orderId, status, data = {}) {
    this.send('pos_order_updated', { orderId, status, ...data });
  }
}

// Instance singleton
const websocketService = new WebSocketService();

export default websocketService;
