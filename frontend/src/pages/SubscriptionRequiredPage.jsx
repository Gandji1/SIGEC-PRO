import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import {
  CreditCard,
  Check,
  Star,
  Zap,
  Crown,
  AlertTriangle,
  LogOut,
} from "lucide-react";
import apiClient from "../services/apiClient";
import { useTenantStore } from "../stores/tenantStore";
import SubscriptionPaymentModal from "../components/SubscriptionPaymentModal";
import SubscriptionPlanCard from "../components/SubscriptionPlanCard";

export default function SubscriptionRequiredPage() {
  const navigate = useNavigate();
  const { user, tenant, logout } = useTenantStore();
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedPlan, setSelectedPlan] = useState(null);
  const [processing, setProcessing] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState("mobile_money");
  const [showPaymentModal, setShowPaymentModal] = useState(false);

  useEffect(() => {
    fetchPlans();
    checkSubscription();
  }, []);

  const checkSubscription = async () => {
    try {
      const res = await apiClient.get("/subscription/status");
      if (
        res.data?.data?.has_subscription &&
        res.data?.data?.status === "active"
      ) {
        // Déjà abonné, rediriger vers dashboard
        navigate("/dashboard");
      }
    } catch (err) {
      console.error("Error checking subscription:", err);
    }
  };

  const fetchPlans = async () => {
    try {
      const res = await apiClient.get("/subscription/plans");
      setPlans(res.data?.data || []);
    } catch (err) {
      console.error("Error fetching plans:", err);
    } finally {
      setLoading(false);
    }
  };

  const handleSelectPlan = (plan) => {
    setSelectedPlan(plan);
    setShowPaymentModal(true);
  };

  const handleSubscribe = async () => {
    if (!selectedPlan) return;

    setProcessing(true);
    try {
      const res = await apiClient.post("/subscription/subscribe", {
        plan_id: selectedPlan.id,
        payment_method: paymentMethod,
        duration_months: 1,
      });

      if (res.data?.success) {
        // Rediriger vers le dashboard après abonnement réussi
        navigate("/dashboard");
      }
    } catch (err) {
      console.error("Subscription error:", err);
      const status = err.response?.status;
      const errors = err.response?.data?.errors;

      if (status === 422 && errors && typeof errors === "object") {
        const firstField = Object.keys(errors)[0];
        const firstMessage = errors[firstField]?.[0];
        alert(
          firstMessage || err.response?.data?.message || "Données invalides"
        );
      } else {
        alert(err.response?.data?.message || "Erreur lors de l'abonnement");
      }
    } finally {
      setProcessing(false);
    }
  };

  const handleLogout = () => {
    logout();
    navigate("/login");
  };

  const getPlanIcon = (planName) => {
    const name = planName?.toLowerCase() || "";
    if (name.includes("premium") || name.includes("prenium"))
      return <Crown className="text-yellow-500" size={32} />;
    if (name.includes("standard") || name.includes("pro"))
      return <Zap className="text-blue-500" size={32} />;
    return <Star className="text-green-500" size={32} />;
  };

  const formatPrice = (price) => {
    return new Intl.NumberFormat("fr-FR").format(price || 0);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">SIGEC</h1>
            <p className="text-sm text-gray-500">
              Bienvenue, {user?.name || "Utilisateur"}
            </p>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 text-gray-600 hover:text-red-600"
          >
            <LogOut size={20} />
            Déconnexion
          </button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-12">
        {/* Alert */}
        <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8 flex items-start gap-4">
          <AlertTriangle className="text-yellow-500 flex-shrink-0" size={28} />
          <div>
            <h2 className="text-lg font-semibold text-yellow-800">
              Abonnement requis
            </h2>
            <p className="text-yellow-700 mt-1">
              Pour accéder à votre espace de travail et aux fonctionnalités de
              SIGEC, veuillez choisir un plan d'abonnement adapté à vos besoins.
            </p>
          </div>
        </div>

        {/* Plans */}
        <div className="text-center mb-10">
          <h2 className="text-3xl font-bold text-gray-900">
            Choisissez votre plan
          </h2>
          <p className="text-gray-600 mt-2">
            Sélectionnez le plan qui correspond à vos besoins
          </p>
        </div>

        <div className="grid md:grid-cols-3 gap-6">
          {plans.map((plan) => (
            <SubscriptionPlanCard
              key={plan.id}
              plan={plan}
              onSelectPlan={handleSelectPlan}
              formatPrice={formatPrice}
              getPlanIcon={getPlanIcon}
            />
          ))}
        </div>

        {plans.length === 0 && (
          <div className="text-center py-12 bg-white rounded-xl shadow">
            <CreditCard className="mx-auto text-gray-400 mb-4" size={48} />
            <p className="text-gray-500">
              Aucun plan disponible pour le moment.
            </p>
            <p className="text-gray-400 text-sm mt-2">
              Contactez l'administrateur.
            </p>
          </div>
        )}
      </main>

      {/* Payment Modal */}
      <SubscriptionPaymentModal
        show={showPaymentModal}
        selectedPlan={selectedPlan}
        onClose={() => setShowPaymentModal(false)}
        onSubscribe={handleSubscribe}
        processing={processing}
        paymentMethod={paymentMethod}
        onPaymentMethodChange={setPaymentMethod}
        formatPrice={formatPrice}
      />
    </div>
  );
}
