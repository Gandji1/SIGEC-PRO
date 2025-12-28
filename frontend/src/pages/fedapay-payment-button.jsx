import { router, usePage } from "@inertiajs/react";
import { FedaCheckoutButton } from "fedapay-reactjs";

export default function FedapayPaymentButton({
  amount,
  description,
  label,
  paymentType,
  metadata,
}) {
  function handlePaymentResponse(paymentMethod, response) {
    console.log(`${paymentMethod} response:`, response);

    if (
      response.transaction.status === "success" ||
      response.transaction.status === "completed" ||
      response.transaction.status === "approved"
    ) {
      //alert(`${paymentMethod} : Paiement réussi ! 🎉`);
      if (paymentType === "event") {
        const transactionId =
          response.transaction.id ||
          response?.transaction?.transaction_key ||
          "";
        const status = response?.status || response?.transaction?.status;
        const eventId = response?.transaction?.custom_metadata?.event_id || "";
        router.visit(
          route("payment.callback", {
            transactionId,
            status,
            eventId,
            paymentType,
          }),
          {
            preserveState: true,
            preserveScroll: true,
          }
        );
      } else if (paymentType === "subscription") {
        const transactionId =
          response.transaction.id ||
          response?.transaction?.transaction_key ||
          "";
        const status = response?.status || response?.transaction?.status;
        const subscriptionPlanId =
          response?.transaction?.custom_metadata?.subscription_plan_id || "";
        router.visit(
          route("payment.callback", {
            transactionId,
            status,
            subscriptionPlanId,
            paymentType,
          }),
          {
            preserveState: true,
            preserveScroll: true,
          }
        );
      }
    } else {
      alert(`${paymentMethod} : Échec du paiement1. ${response.status} 🚨`);
    }
  }

  const checkoutButtonOptions = {
    public_key: process.env.FEDAPAY_PUBLIC_KEY,
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
      // On utilise une assertion de type sécurisée pour accéder à FedaPay depuis l'objet window
      const FedaPay = window.FedaPay;

      // Vérification que FedaPay est bien disponible
      if (!FedaPay) {
        console.error("FedaPay n'est pas disponible dans la fenêtre globale");
        return;
      }

      if (resp.reason === FedaPay.DIALOG_DISMISSED) {
        //alert('Vous avez fermé la boite de dialogue');
      } else {
        console.log("resp", resp);
        handlePaymentResponse("FedaPay", resp);
        //alert('Transaction terminée: ' + resp.reason);
      }

      //console.log(resp.transaction);
    },
  };

  return (
    <button>
      <FedaCheckoutButton options={checkoutButtonOptions} />
    </button>
  );
}
