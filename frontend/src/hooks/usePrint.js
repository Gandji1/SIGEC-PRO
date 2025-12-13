import { useCallback } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import printService from '../services/printService';

/**
 * Hook React pour l'impression de tickets et factures
 */
export function usePrint() {
  const { tenant } = useTenantStore();

  const printReceipt = useCallback((order, options = {}) => {
    printService.printReceipt(order, tenant, options);
  }, [tenant]);

  const printInvoice = useCallback((sale, customer, options = {}) => {
    printService.printInvoice(sale, tenant, customer, options);
  }, [tenant]);

  const downloadInvoice = useCallback((sale, customer) => {
    printService.downloadInvoice(sale, tenant, customer);
  }, [tenant]);

  const generateReceipt = useCallback((order, options = {}) => {
    return printService.generateReceipt(order, tenant, options);
  }, [tenant]);

  const generateInvoice = useCallback((sale, customer, options = {}) => {
    return printService.generateInvoice(sale, tenant, customer, options);
  }, [tenant]);

  return {
    printReceipt,
    printInvoice,
    downloadInvoice,
    generateReceipt,
    generateInvoice,
  };
}

export default usePrint;
