import { Check } from "lucide-react";

export default function SubscriptionPlanCard({
  plan,
  onSelectPlan,
  formatPrice,
  getPlanIcon,
}) {
  const isRecommended = plan.name?.toLowerCase().includes("standard");

  return (
    <div
      className={`bg-white rounded-2xl shadow-lg overflow-hidden transition-all hover:shadow-xl ${
        isRecommended ? "ring-2 ring-blue-500 scale-105" : ""
      }`}
    >
      {isRecommended && (
        <div className="bg-blue-500 text-white text-center py-2 text-sm font-medium">
          Recommandé
        </div>
      )}

      <div className="p-6">
        <div className="flex items-center gap-3 mb-4">
          {getPlanIcon(plan.name)}
          <div>
            <h3 className="text-xl font-bold text-gray-900">
              {plan.display_name || plan.name}
            </h3>
            <p className="text-sm text-gray-500">{plan.description}</p>
          </div>
        </div>

        <div className="mb-6">
          <span className="text-4xl font-bold text-gray-900">
            {formatPrice(plan.price_monthly)}
          </span>
          <span className="text-gray-500 ml-2">XOF/mois</span>
        </div>

        <ul className="space-y-3 mb-6">
          <li className="flex items-center gap-2 text-gray-600">
            <Check className="text-green-500" size={18} />
            <span>{plan.max_users} utilisateurs</span>
          </li>
          <li className="flex items-center gap-2 text-gray-600">
            <Check className="text-green-500" size={18} />
            <span>{plan.max_products} produits</span>
          </li>
          <li className="flex items-center gap-2 text-gray-600">
            <Check className="text-green-500" size={18} />
            <span>{plan.max_pos} points de vente</span>
          </li>
          <li className="flex items-center gap-2 text-gray-600">
            <Check className="text-green-500" size={18} />
            <span>{plan.max_warehouses} entrepôt(s)</span>
          </li>
          <li className="flex items-center gap-2 text-gray-600">
            <Check className="text-green-500" size={18} />
            <span>{plan.storage_limit_mb} MB stockage</span>
          </li>
          {plan.trial_days > 0 && (
            <li className="flex items-center gap-2 text-blue-600 font-medium">
              <Check className="text-blue-500" size={18} />
              <span>{plan.trial_days} jours d'essai gratuit</span>
            </li>
          )}
        </ul>

        <button
          onClick={() => onSelectPlan(plan)}
          className={`w-full py-3 rounded-lg font-semibold transition-colors ${
            isRecommended
              ? "bg-blue-600 hover:bg-blue-700 text-white"
              : "bg-gray-100 hover:bg-gray-200 text-gray-800"
          }`}
        >
          {plan.trial_days > 0
            ? "Commencer l'essai gratuit"
            : "Choisir ce plan"}
        </button>
      </div>
    </div>
  );
}
