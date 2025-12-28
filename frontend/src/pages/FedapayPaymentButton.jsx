import { useNavigate } from "react-router-dom";
import { FedaCheckoutButton } from "fedapay-reactjs";

export default function FedapayPaymentButton({
  amount,
  description,
  label,
  paymentType,
  metadata,
}) {
  const navigate = useNavigate();

  function handlePaymentResponse(paymentMethod, response) {
    console.log(`${paymentMethod} response:`, response);

    if (
      response.transaction.status === "success" ||
      response.transaction.status === "completed" ||
      response.transaction.status === "approved"
    ) {
      if (paymentType === "event") {
        const transactionId =
          response.transaction.id ||
          response?.transaction?.transaction_key ||
          "";
        const status = response?.status || response?.transaction?.status;
        const eventId = response?.transaction?.custom_metadata?.event_id || "";

        navigate(
          `/payment/callback?transactionId=${transactionId}&status=${status}&eventId=${eventId}&paymentType=${paymentType}`
        );
      } else if (paymentType === "subscription") {
        const transactionId =
          response.transaction.id ||
          response?.transaction?.transaction_key ||
          "";
        const status = response?.status || response?.transaction?.status;
        const subscriptionPlanId =
          response?.transaction?.custom_metadata?.subscription_plan_id || "";

        navigate(
          `/payment/callback?transactionId=${transactionId}&status=${status}&subscriptionPlanId=${subscriptionPlanId}&paymentType=${paymentType}`
        );
      }
    } else {
      alert(`${paymentMethod} : Échec du paiement. ${response.status} 🚨`);
    }
  }

  const checkoutButtonOptions = {
    public_key: import.meta.env.VITE_FEDAPAY_PUBLIC_KEY,
    transaction: {
      amount: Number.parseInt(amount.toString()),
      description: description,
      custom_metadata: metadata,
    },
    currency: {
      iso: "XOF",
    },
    button: {
      class: "btn btn-primary",
      text: label,
    },
    onComplete(resp) {
      const FedaPay = window.FedaPay;

      if (!FedaPay) {
        console.error("FedaPay n'est pas disponible dans la fenêtre globale");
        return;
      }

      if (resp.reason === FedaPay.DIALOG_DISMISSED) {
        console.log("Dialogue FedaPay fermé par l'utilisateur");
      } else {
        console.log("resp", resp);
        handlePaymentResponse("FedaPay", resp);
      }
    },
  };

  return (
    <button>
      <FedaCheckoutButton options={checkoutButtonOptions} />
    </button>
  );
}
