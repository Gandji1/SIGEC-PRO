import React, { useState, useEffect, memo } from 'react';
import { X, CreditCard, Banknote, Smartphone, Check, Loader2, AlertCircle } from 'lucide-react';
import { usePayment } from '../hooks/usePayment';

/**
 * Modal de paiement unifié
 * Supporte espèces, FedaPay, KakiaPay, Mobile Money
 */
const PaymentModal = memo(({ 
  isOpen, 
  onClose, 
  order, 
  onPaymentComplete,
  defaultMethod = 'cash'
}) => {
  const { availableMethods, processCashPayment, initializePayment, loading, error } = usePayment();
  
  const [selectedMethod, setSelectedMethod] = useState(defaultMethod);
  const [receivedAmount, setReceivedAmount] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [step, setStep] = useState('select'); // select, process, success, error
  const [paymentError, setPaymentError] = useState(null);

  const total = order?.total || 0;
  const change = parseFloat(receivedAmount || 0) - total;

  useEffect(() => {
    if (isOpen) {
      setStep('select');
      setPaymentError(null);
      setReceivedAmount(total.toString());
    }
  }, [isOpen, total]);

  const handleCashPayment = async () => {
    if (parseFloat(receivedAmount) < total) {
      setPaymentError('Montant insuffisant');
      return;
    }

    setStep('process');
    const result = await processCashPayment(order.id, total, parseFloat(receivedAmount));
    
    if (result.success) {
      setStep('success');
      setTimeout(() => {
        onPaymentComplete?.({ method: 'cash', ...result.data });
        onClose();
      }, 1500);
    } else {
      setPaymentError(result.error);
      setStep('error');
    }
  };

  const handleMobilePayment = async () => {
    if (!customerPhone) {
      setPaymentError('Numéro de téléphone requis');
      return;
    }

    setStep('process');
    const result = await initializePayment({
      amount: total,
      method: selectedMethod,
      orderId: order.id,
      customerPhone,
    });

    if (result.success) {
      // Rediriger vers la page de paiement ou afficher le widget
      if (result.paymentUrl) {
        window.open(result.paymentUrl, '_blank');
      }
      setStep('success');
      onPaymentComplete?.({ method: selectedMethod, reference: result.reference });
    } else {
      setPaymentError(result.error);
      setStep('error');
    }
  };

  const handleSubmit = () => {
    if (selectedMethod === 'cash') {
      handleCashPayment();
    } else {
      handleMobilePayment();
    }
  };

  const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
  };

  const getMethodIcon = (method) => {
    switch (method) {
      case 'cash': return <Banknote className="w-5 h-5" />;
      case 'card':
      case 'fedapay':
      case 'kakiapay': return <CreditCard className="w-5 h-5" />;
      default: return <Smartphone className="w-5 h-5" />;
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in"
        onClick={onClose}
      />
      
      {/* Modal */}
      <div className="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md animate-scale-in overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-orange-500">
          <h2 className="text-lg font-bold text-white">Paiement</h2>
          <button 
            onClick={onClose}
            className="p-1 rounded-lg hover:bg-white/20 text-white transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Montant total */}
          <div className="text-center mb-6">
            <p className="text-sm text-gray-500 dark:text-gray-400">Montant à payer</p>
            <p className="text-3xl font-bold text-gray-900 dark:text-white">{formatAmount(total)}</p>
            {order?.reference && (
              <p className="text-sm text-gray-500 mt-1">Commande {order.reference}</p>
            )}
          </div>

          {step === 'select' && (
            <>
              {/* Méthodes de paiement */}
              <div className="space-y-2 mb-6">
                <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Mode de paiement
                </p>
                <div className="grid grid-cols-2 gap-2">
                  {availableMethods.map((method) => (
                    <button
                      key={method.id}
                      onClick={() => setSelectedMethod(method.id)}
                      className={`flex items-center gap-3 p-3 rounded-xl border-2 transition-all ${
                        selectedMethod === method.id
                          ? 'border-orange-500 bg-orange-50 dark:bg-orange-500/20'
                          : 'border-gray-200 dark:border-gray-600 hover:border-orange-300'
                      }`}
                    >
                      <span className="text-xl">{method.icon}</span>
                      <span className="font-medium text-sm text-gray-900 dark:text-white">
                        {method.name}
                      </span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Champs selon méthode */}
              {selectedMethod === 'cash' && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Montant reçu
                    </label>
                    <input
                      type="number"
                      value={receivedAmount}
                      onChange={(e) => setReceivedAmount(e.target.value)}
                      className="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-lg font-bold text-center focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                      placeholder="0"
                    />
                  </div>
                  {parseFloat(receivedAmount) >= total && (
                    <div className="flex justify-between items-center p-3 bg-green-50 dark:bg-green-500/20 rounded-xl">
                      <span className="text-green-700 dark:text-green-400">Monnaie à rendre</span>
                      <span className="font-bold text-green-700 dark:text-green-400">
                        {formatAmount(change)}
                      </span>
                    </div>
                  )}
                </div>
              )}

              {['mtn_money', 'moov_money', 'fedapay', 'kakiapay'].includes(selectedMethod) && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Numéro de téléphone
                  </label>
                  <input
                    type="tel"
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    placeholder="+229 XX XX XX XX"
                  />
                </div>
              )}

              {paymentError && (
                <div className="mt-4 p-3 bg-red-50 dark:bg-red-500/20 rounded-xl flex items-center gap-2 text-red-700 dark:text-red-400">
                  <AlertCircle size={18} />
                  <span className="text-sm">{paymentError}</span>
                </div>
              )}
            </>
          )}

          {step === 'process' && (
            <div className="text-center py-8">
              <Loader2 className="w-12 h-12 text-orange-500 animate-spin mx-auto mb-4" />
              <p className="text-gray-600 dark:text-gray-400">Traitement en cours...</p>
            </div>
          )}

          {step === 'success' && (
            <div className="text-center py-8">
              <div className="w-16 h-16 bg-green-100 dark:bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Check className="w-8 h-8 text-green-600 dark:text-green-400" />
              </div>
              <p className="text-lg font-bold text-green-600 dark:text-green-400">Paiement réussi!</p>
              {selectedMethod === 'cash' && change > 0 && (
                <p className="text-gray-600 dark:text-gray-400 mt-2">
                  Monnaie: {formatAmount(change)}
                </p>
              )}
            </div>
          )}

          {step === 'error' && (
            <div className="text-center py-8">
              <div className="w-16 h-16 bg-red-100 dark:bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <AlertCircle className="w-8 h-8 text-red-600 dark:text-red-400" />
              </div>
              <p className="text-lg font-bold text-red-600 dark:text-red-400">Échec du paiement</p>
              <p className="text-gray-600 dark:text-gray-400 mt-2">{paymentError}</p>
              <button
                onClick={() => setStep('select')}
                className="mt-4 px-4 py-2 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-500/20 rounded-lg transition-colors"
              >
                Réessayer
              </button>
            </div>
          )}
        </div>

        {/* Footer */}
        {step === 'select' && (
          <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <button
              onClick={handleSubmit}
              disabled={loading || (selectedMethod === 'cash' && parseFloat(receivedAmount) < total)}
              className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              {loading ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                <>
                  {getMethodIcon(selectedMethod)}
                  <span>Confirmer le paiement</span>
                </>
              )}
            </button>
          </div>
        )}
      </div>
    </div>
  );
});

PaymentModal.displayName = 'PaymentModal';

export default PaymentModal;
