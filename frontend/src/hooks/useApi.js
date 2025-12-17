import { useState, useCallback } from "react";
import apiClient from "../services/apiClient";
import { useToast } from "../components/Toast";

/**
 * Hook personnalisé pour les appels API avec gestion d'erreurs
 * @returns {object} { loading, error, request, get, post, put, del }
 */
export function useApi() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const { toast } = useToast();

  // Fonction générique de requête
  const request = useCallback(
    async (method, url, data = null, options = {}) => {
      const {
        showSuccessToast = false,
        successMessage = "Opération réussie",
        showErrorToast = true,
        silent = false,
      } = options;

      setLoading(true);
      setError(null);

      try {
        let response;
        switch (method.toLowerCase()) {
          case "get":
            response = await apiClient.get(url);
            break;
          case "post":
            response = await apiClient.post(url, data);
            break;
          case "put":
            response = await apiClient.put(url, data);
            break;
          case "patch":
            response = await apiClient.patch(url, data);
            break;
          case "delete":
            response = await apiClient.delete(url);
            break;
          default:
            throw new Error(`Méthode HTTP non supportée: ${method}`);
        }

        if (showSuccessToast && !silent) {
          toast.success(successMessage);
        }

        return { data: response.data, success: true };
      } catch (err) {
        const errorMessage = getErrorMessage(err);
        setError(errorMessage);

        if (showErrorToast && !silent) {
          toast.error(errorMessage);
        }

        return { error: errorMessage, success: false };
      } finally {
        setLoading(false);
      }
    },
    [toast]
  );

  // Raccourcis pour les méthodes HTTP
  const get = useCallback(
    (url, options) => request("get", url, null, options),
    [request]
  );
  const post = useCallback(
    (url, data, options) => request("post", url, data, options),
    [request]
  );
  const put = useCallback(
    (url, data, options) => request("put", url, data, options),
    [request]
  );
  const patch = useCallback(
    (url, data, options) => request("patch", url, data, options),
    [request]
  );
  const del = useCallback(
    (url, options) => request("delete", url, null, options),
    [request]
  );

  return { loading, error, request, get, post, put, patch, del };
}

/**
 * Extrait un message d'erreur lisible depuis une erreur Axios
 * @param {Error} err - L'erreur Axios
 * @returns {string} Message d'erreur
 */
function getErrorMessage(err) {
  // Erreur de validation Laravel (422)
  if (err.response?.status === 422) {
    const errors = err.response.data?.errors;
    if (errors) {
      // Prendre le premier message d'erreur
      const firstField = Object.keys(errors)[0];
      return errors[firstField]?.[0] || "Données invalides";
    }
    return err.response.data?.message || "Données invalides";
  }

  // Erreur d'authentification (401)
  if (err.response?.status === 401) {
    return "Session expirée. Veuillez vous reconnecter.";
  }

  // Erreur d'autorisation (403)
  if (err.response?.status === 403) {
    return "Vous n'avez pas les permissions nécessaires.";
  }

  // Erreur serveur (500)
  if (err.response?.status >= 500) {
    return "Erreur serveur. Veuillez réessayer plus tard.";
  }

  // Erreur réseau
  if (err.code === "ECONNABORTED" || err.message === "Network Error") {
    return "Erreur de connexion. Vérifiez votre réseau.";
  }

  // Erreur CORS spécifique
  if (err.message.includes("CORS") || err.message.includes("cross-origin")) {
    return "Erreur CORS: Problème d'accès cross-origin. Vérifiez la configuration du serveur.";
  }

  // Message d'erreur du backend
  if (err.response?.data?.message) {
    return err.response.data.message;
  }

  // Message d'erreur générique
  return err.message || "Une erreur est survenue";
}

export default useApi;
