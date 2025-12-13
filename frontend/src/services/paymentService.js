/**
 * Service de paiement unifié pour SIGEC
 * Supporte FedaPay, Kakiapay, Mobile Money
 */

import apiClient from './apiClient';

// Configuration des passerelles
const PAYMENT_GATEWAYS = {
  fedapay: {
    name: 'FedaPay',
    icon: '💳',
    supportedMethods: ['card', 'mobile_money', 'mtn', 'moov'],
    currencies: ['XOF', 'XAF', 'GNF'],
  },
  kakiapay: {
    name: 'KakiaPay',
    icon: '📱',
    supportedMethods: ['mobile_money', 'mtn', 'moov', 'card'],
    currencies: ['XOF'],
  },
  cash: {
    name: 'Espèces',
    icon: '💵',
    supportedMethods: ['cash'],
    currencies: ['XOF', 'XAF', 'EUR', 'USD'],
  },
};

class PaymentService {
  constructor() {
    this.activeGateway = null;
    this.tenantConfig = null;
  }

  /**
   * Charger la configuration de paiement du tenant
   */
  async loadTenantConfig() {
    try {
      const response = await apiClient.get('/tenant-config/payment-methods');
      this.tenantConfig = response.data;
      return this.tenantConfig;
    } catch (error) {
      console.error('[PaymentService] Config load error:', error);
      return null;
    }
  }

  /**
   * Obtenir les méthodes de paiement disponibles
   */
  getAvailableMethods() {
    if (!this.tenantConfig) {
      return [{ id: 'cash', name: 'Espèces', icon: '💵' }];
    }

    const methods = [];
    
    if (this.tenantConfig.cash_enabled !== false) {
      methods.push({ id: 'cash', name: 'Espèces', icon: '💵' });
    }
    
    if (this.tenantConfig.fedapay_enabled && this.tenantConfig.fedapay_public_key) {
      methods.push({ id: 'fedapay', name: 'FedaPay', icon: '💳' });
      methods.push({ id: 'mtn_money', name: 'MTN Money', icon: '📱' });
      methods.push({ id: 'moov_money', name: 'Moov Money', icon: '📱' });
    }
    
    if (this.tenantConfig.kakiapay_enabled && this.tenantConfig.kakiapay_public_key) {
      methods.push({ id: 'kakiapay', name: 'KakiaPay', icon: '📲' });
    }

    return methods;
  }

  /**
   * Initialiser un paiement
   */
  async initializePayment(data) {
    const { amount, currency = 'XOF', method, orderId, customerEmail, customerPhone, description } = data;

    try {
      const response = await apiClient.post('/payments/initialize', {
        amount,
        currency,
        payment_method: method,
        order_id: orderId,
        customer_email: customerEmail,
        customer_phone: customerPhone,
        description: description || `Paiement commande #${orderId}`,
        callback_url: `${window.location.origin}/payment/callback`,
        return_url: `${window.location.origin}/payment/success`,
      });

      return {
        success: true,
        transactionId: response.data.transaction_id,
        paymentUrl: response.data.payment_url,
        reference: response.data.reference,
      };
    } catch (error) {
      console.error('[PaymentService] Init error:', error);
      return {
        success: false,
        error: error.response?.data?.message || 'Erreur lors de l\'initialisation du paiement',
      };
    }
  }

  /**
   * Vérifier le statut d'un paiement
   */
  async verifyPayment(reference) {
    try {
      const response = await apiClient.post('/payments/verify', { reference });
      return {
        success: true,
        status: response.data.status,
        paid: response.data.status === 'approved' || response.data.status === 'completed',
        data: response.data,
      };
    } catch (error) {
      console.error('[PaymentService] Verify error:', error);
      return {
        success: false,
        error: error.response?.data?.message || 'Erreur de vérification',
      };
    }
  }

  /**
   * Traiter un paiement en espèces
   */
  async processCashPayment(orderId, amount, receivedAmount) {
    try {
      const response = await apiClient.post(`/pos/orders/${orderId}/validate-payment`, {
        payment_method: 'cash',
        amount,
        received_amount: receivedAmount,
        change: receivedAmount - amount,
      });

      return {
        success: true,
        data: response.data,
      };
    } catch (error) {
      console.error('[PaymentService] Cash payment error:', error);
      return {
        success: false,
        error: error.response?.data?.message || 'Erreur de paiement',
      };
    }
  }

  /**
   * Ouvrir le widget FedaPay
   */
  openFedaPayWidget(config) {
    return new Promise((resolve, reject) => {
      if (!window.FedaPay) {
        // Charger le script FedaPay dynamiquement
        const script = document.createElement('script');
        script.src = 'https://cdn.fedapay.com/checkout.js?v=1.1.7';
        script.onload = () => this._initFedaPayWidget(config, resolve, reject);
        script.onerror = () => reject(new Error('Impossible de charger FedaPay'));
        document.head.appendChild(script);
      } else {
        this._initFedaPayWidget(config, resolve, reject);
      }
    });
  }

  _initFedaPayWidget(config, resolve, reject) {
    const { amount, currency, publicKey, transactionId, customer } = config;

    window.FedaPay.init({
      public_key: publicKey,
      transaction: {
        amount,
        currency,
        description: config.description,
        custom_metadata: {
          transaction_id: transactionId,
          order_id: config.orderId,
        },
      },
      customer: {
        email: customer?.email,
        phone_number: customer?.phone,
      },
      onComplete: (response) => {
        if (response.status === 'approved') {
          resolve({ success: true, response });
        } else {
          reject({ success: false, response });
        }
      },
      onClose: () => {
        reject({ success: false, cancelled: true });
      },
    });

    window.FedaPay.open();
  }

  /**
   * Ouvrir le widget KakiaPay
   */
  openKakiaPayWidget(config) {
    return new Promise((resolve, reject) => {
      if (!window.kakiapay) {
        const script = document.createElement('script');
        script.src = 'https://cdn.kakiapay.com/kakiapay.min.js';
        script.onload = () => this._initKakiaPayWidget(config, resolve, reject);
        script.onerror = () => reject(new Error('Impossible de charger KakiaPay'));
        document.head.appendChild(script);
      } else {
        this._initKakiaPayWidget(config, resolve, reject);
      }
    });
  }

  _initKakiaPayWidget(config, resolve, reject) {
    const { amount, publicKey, transactionId, customer } = config;

    window.kakiapay.open({
      key: publicKey,
      amount,
      name: customer?.name || 'Client',
      email: customer?.email,
      phone: customer?.phone,
      data: {
        transaction_id: transactionId,
        order_id: config.orderId,
      },
      callback: (response) => {
        if (response.status === 'success') {
          resolve({ success: true, response });
        } else {
          reject({ success: false, response });
        }
      },
      onClose: () => {
        reject({ success: false, cancelled: true });
      },
    });
  }
}

// Instance singleton
const paymentService = new PaymentService();
export default paymentService;
