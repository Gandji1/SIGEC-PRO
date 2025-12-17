import https from "https";

/**
 * Service de proxy CORS pour contourner les erreurs CORS en développement
 * En production, ce service peut être remplacé par une configuration serveur
 */

// URLs des APIs externes
const EXTERNAL_APIS = {
  "https://api.sigec.artbenshow.com": "/external-api",
  "api.sigec.artbenshow.com": "/external-api",
};

/**
 * Détermine si l'URL nécessite un proxy CORS
 * @param {string} url - URL de la requête
 * @returns {boolean} - True si un proxy est nécessaire
 */
export const needsCorsProxy = (url) => {
  try {
    const urlObj = new URL(url);
    const hostname = urlObj.hostname;

    return Object.keys(EXTERNAL_APIS).some(
      (apiHost) => hostname === apiHost || hostname.endsWith(apiHost)
    );
  } catch (error) {
    return false;
  }
};

/**
 * Obtient l'URL de proxy pour une URL donnée
 * @param {string} url - URL originale
 * @returns {string} - URL de proxy ou URL originale
 */
export const getProxyUrl = (url) => {
  if (needsCorsProxy(url)) {
    try {
      const urlObj = new URL(url);
      const path = urlObj.pathname + urlObj.search + urlObj.hash;
      return `/external-api${path}`;
    } catch (error) {
      console.warn("Invalid URL for proxy:", url);
      return url;
    }
  }
  return url;
};

/**
 * Configuration Axios avec support du proxy CORS
 * @param {string} url - URL de la requête
 * @param {object} config - Configuration Axios
 * @returns {object} - Configuration modifiée
 */
export const configureCorsProxy = (url, config = {}) => {
  const proxyUrl = getProxyUrl(url);

  return {
    ...config,
    url: proxyUrl,
    // En développement, on peut désactiver la vérification SSL pour certains proxies
    ...(import.meta.env.DEV && needsCorsProxy(url)
      ? {
          httpsAgent: new https.Agent({
            rejectUnauthorized: false, // À utiliser uniquement en développement
          }),
        }
      : {}),
  };
};

/**
 * Middleware pour intercepter les requêtes et appliquer le proxy CORS si nécessaire
 * @param {object} axiosInstance - Instance Axios
 */
export const setupCorsProxyMiddleware = (axiosInstance) => {
  axiosInstance.interceptors.request.use(
    (config) => {
      // Appliquer le proxy CORS si nécessaire
      const proxiedConfig = configureCorsProxy(config.url, config);
      return proxiedConfig;
    },
    (error) => {
      return Promise.reject(error);
    }
  );
};

export default {
  needsCorsProxy,
  getProxyUrl,
  configureCorsProxy,
  setupCorsProxyMiddleware,
};
