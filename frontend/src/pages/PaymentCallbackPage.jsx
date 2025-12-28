import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { CheckCircle, XCircle, Loader2, AlertCircle } from "lucide-react";
import apiClient from "../services/apiClient";

export default function PaymentCallbackPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState("processing"); // processing, success, error
  const [message, setMessage] = useState("Traitement du paiement en cours...");
  const [details, setDetails] = useState(null);

  const transactionId = searchParams.get("transactionId");
  const paymentStatus = searchParams.get("status");
  const paymentType = searchParams.get("paymentType");
  const subscriptionPlanId = searchParams.get("subscriptionPlanId");
  const eventId = searchParams.get("eventId");

  useEffect(() => {
    if (!transactionId || !paymentStatus || !paymentType) {
      setStatus("error");
      setMessage("Paramètres de paiement manquants");
      return;
    }

    processPaymentCallback();
  }, [transactionId, paymentStatus, paymentType]);

  const processPaymentCallback = async () => {
    try {
      if (paymentType === "subscription") {
        await processSubscriptionPayment();
      } else if (paymentType === "event") {
        await processEventPayment();
      } else {
        setStatus("error");
        setMessage("Type de paiement non reconnu");
      }
    } catch (error) {
      console.error("Payment callback error:", error);
      setStatus("error");
      setMessage(
        error.response?.data?.message ||
          "Une erreur est survenue lors du traitement du paiement"
      );
    }
  };

  const processSubscriptionPayment = async () => {
    try {
      const response = await apiClient.post("/subscription/payment-callback", {
        transaction_id: transactionId,
        status: paymentStatus,
        plan_id: subscriptionPlanId,
      });

      if (response.data?.success) {
        setStatus("success");
        setMessage("Abonnement activé avec succès!");
        setDetails(response.data?.data);

        setTimeout(() => {
          navigate("/dashboard");
        }, 3000);
      } else {
        setStatus("error");
        setMessage(
          response.data?.message || "Échec de l'activation de l'abonnement"
        );
      }
    } catch (error) {
      throw error;
    }
  };

  const processEventPayment = async () => {
    try {
      const response = await apiClient.post("/events/payment-callback", {
        transaction_id: transactionId,
        status: paymentStatus,
        event_id: eventId,
      });

      if (response.data?.success) {
        setStatus("success");
        setMessage("Paiement de l'événement réussi!");
        setDetails(response.data?.data);

        setTimeout(() => {
          navigate(`/events/${eventId}`);
        }, 3000);
      } else {
        setStatus("error");
        setMessage(
          response.data?.message || "Échec du paiement de l'événement"
        );
      }
    } catch (error) {
      throw error;
    }
  };

  const handleRetry = () => {
    if (paymentType === "subscription") {
      navigate("/subscription");
    } else if (paymentType === "event") {
      navigate(`/events/${eventId}`);
    } else {
      navigate("/dashboard");
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
        {status === "processing" && (
          <div className="text-center">
            <Loader2 className="w-16 h-16 text-blue-600 animate-spin mx-auto mb-4" />
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Traitement en cours
            </h2>
            <p className="text-gray-600">{message}</p>
          </div>
        )}

        {status === "success" && (
          <div className="text-center">
            <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <CheckCircle className="w-12 h-12 text-green-600" />
            </div>
            <h2 className="text-2xl font-bold text-green-600 mb-2">
              Paiement réussi!
            </h2>
            <p className="text-gray-600 mb-4">{message}</p>

            {details && (
              <div className="bg-gray-50 rounded-lg p-4 mb-4 text-left">
                <div className="space-y-2 text-sm">
                  {details.transaction_id && (
                    <div className="flex justify-between">
                      <span className="text-gray-600">Transaction:</span>
                      <span className="font-medium text-gray-900">
                        {details.transaction_id}
                      </span>
                    </div>
                  )}
                  {details.amount && (
                    <div className="flex justify-between">
                      <span className="text-gray-600">Montant:</span>
                      <span className="font-medium text-gray-900">
                        {new Intl.NumberFormat("fr-FR").format(details.amount)}{" "}
                        XOF
                      </span>
                    </div>
                  )}
                  {details.plan_name && (
                    <div className="flex justify-between">
                      <span className="text-gray-600">Plan:</span>
                      <span className="font-medium text-gray-900">
                        {details.plan_name}
                      </span>
                    </div>
                  )}
                </div>
              </div>
            )}

            <p className="text-sm text-gray-500">
              Redirection automatique dans quelques secondes...
            </p>
          </div>
        )}

        {status === "error" && (
          <div className="text-center">
            <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <XCircle className="w-12 h-12 text-red-600" />
            </div>
            <h2 className="text-2xl font-bold text-red-600 mb-2">
              Échec du paiement
            </h2>
            <p className="text-gray-600 mb-6">{message}</p>

            <div className="space-y-3">
              <button
                onClick={handleRetry}
                className="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
              >
                Réessayer
              </button>
              <button
                onClick={() => navigate("/dashboard")}
                className="w-full py-3 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-medium transition-colors"
              >
                Retour au tableau de bord
              </button>
            </div>
          </div>
        )}

        {transactionId && (
          <div className="mt-6 pt-6 border-t border-gray-200">
            <div className="flex items-center justify-center gap-2 text-sm text-gray-500">
              <AlertCircle size={16} />
              <span>ID Transaction: {transactionId}</span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
