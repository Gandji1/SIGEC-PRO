import { CreditCard } from "lucide-react";
import FedapayPaymentButton from "../pages/FedapayPaymentButton";

export default function SubscriptionPaymentModal({
  show,
  selectedPlan,
  onClose,
  onSubscribe,
  processing,
  paymentMethod,
  onPaymentMethodChange,
  formatPrice,
}) {
  if (!show || !selectedPlan) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-md w-full p-6">
        <h3 className="text-xl font-bold text-gray-900 mb-4">
          Confirmer l'abonnement
        </h3>

        <div className="bg-gray-50 rounded-lg p-4 mb-6">
          <div className="flex justify-between items-center mb-2">
            <span className="text-gray-600">Plan</span>
            <span className="font-semibold">
              {selectedPlan.display_name || selectedPlan.name}
            </span>
          </div>
          <div className="flex justify-between items-center mb-2">
            <span className="text-gray-600">Prix mensuel</span>
            <span className="font-semibold">
              {formatPrice(selectedPlan.price_monthly)} XOF
            </span>
          </div>
          {selectedPlan.trial_days > 0 && (
            <div className="flex justify-between items-center text-blue-600">
              <span>Période d'essai</span>
              <span className="font-semibold">
                {selectedPlan.trial_days} jours gratuits
              </span>
            </div>
          )}
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Mode de paiement
          </label>
          <div className="space-y-2">
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="payment"
                value="mobile_money"
                checked={paymentMethod === "mobile_money"}
                onChange={(e) => onPaymentMethodChange(e.target.value)}
                className="text-blue-600"
              />
              <span>Mobile Money (MTN, Moov)</span>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="payment"
                value="card"
                checked={paymentMethod === "card"}
                onChange={(e) => onPaymentMethodChange(e.target.value)}
                className="text-blue-600"
              />
              <span>Carte bancaire</span>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="payment"
                value="bank_transfer"
                checked={paymentMethod === "bank_transfer"}
                onChange={(e) => onPaymentMethodChange(e.target.value)}
                className="text-blue-600"
              />
              <span>Virement bancaire</span>
            </label>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50"
          >
            Annuler
          </button>
          <FedapayPaymentButton
            amount={1000}
            description={`Abonnement ${
              selectedPlan.display_name || selectedPlan.name
            }`}
            label={
              processing
                ? "Traitement..."
                : selectedPlan.trial_days > 0
                ? "Démarrer l'essai"
                : "Payer maintenant"
            }
            paymentType="subscription"
            metadata={{
              subscription_plan_id: selectedPlan.id,
              plan_name: selectedPlan.name,
              user_id: selectedPlan.user_id || "",
            }}
          />
        </div>
      </div>
    </div>
  );
}
