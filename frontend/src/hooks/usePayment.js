import { useState, useCallback, useEffect } from 'react';
import paymentService from '../services/paymentService';

/**
 * Hook React pour les paiements
 */
export function usePayment() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [availableMethods, setAvailableMethods] = useState([
    { id: 'cash', name: 'Espèces', icon: '💵' }
  ]);

  useEffect(() => {
    paymentService.loadTenantConfig().then(() => {
      setAvailableMethods(paymentService.getAvailableMethods());
    });
  }, []);

  const initializePayment = useCallback(async (data) => {
    setLoading(true);
    setError(null);
    try {
      const result = await paymentService.initializePayment(data);
      if (!result.success) {
        setError(result.error);
      }
      return result;
    } finally {
      setLoading(false);
    }
  }, []);

  const processCashPayment = useCallback(async (orderId, amount, receivedAmount) => {
    setLoading(true);
    setError(null);
    try {
      const result = await paymentService.processCashPayment(orderId, amount, receivedAmount);
      if (!result.success) {
        setError(result.error);
      }
      return result;
    } finally {
      setLoading(false);
    }
  }, []);

  const verifyPayment = useCallback(async (reference) => {
    setLoading(true);
    try {
      return await paymentService.verifyPayment(reference);
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    loading,
    error,
    availableMethods,
    initializePayment,
    processCashPayment,
    verifyPayment,
    openFedaPay: paymentService.openFedaPayWidget.bind(paymentService),
    openKakiaPay: paymentService.openKakiaPayWidget.bind(paymentService),
  };
}

export default usePayment;
