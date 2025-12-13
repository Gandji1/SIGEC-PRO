import apiClient from './apiClient';

/**
 * Service pour la gestion de l'authentification à deux facteurs (2FA)
 */
const twoFactorService = {
  /**
   * Obtenir le statut 2FA de l'utilisateur connecté
   */
  getStatus: async () => {
    const response = await apiClient.get('/2fa/status');
    return response.data;
  },

  /**
   * Activer le 2FA - génère un secret et QR code
   */
  enable: async () => {
    const response = await apiClient.post('/2fa/enable');
    return response.data;
  },

  /**
   * Confirmer l'activation du 2FA avec un code
   */
  confirm: async (code) => {
    const response = await apiClient.post('/2fa/confirm', { code });
    return response.data;
  },

  /**
   * Désactiver le 2FA
   */
  disable: async (password) => {
    const response = await apiClient.post('/2fa/disable', { password });
    return response.data;
  },

  /**
   * Vérifier un code 2FA lors de la connexion
   */
  verify: async (userId, code) => {
    const response = await apiClient.post('/2fa/verify', { user_id: userId, code });
    return response.data;
  },

  /**
   * Régénérer les codes de récupération
   */
  regenerateCodes: async (password) => {
    const response = await apiClient.post('/2fa/regenerate-codes', { password });
    return response.data;
  },

  /**
   * Générer une URL pour QR code (utilise une API gratuite)
   */
  generateQRCodeUrl: (otpauthUrl) => {
    return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(otpauthUrl)}`;
  },
};

export default twoFactorService;
