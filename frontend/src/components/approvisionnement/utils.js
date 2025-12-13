export const API_URL = '/api';

export const formatCurrency = (v) => 
  v != null ? new Intl.NumberFormat('fr-FR').format(Math.round(v)) + ' FCFA' : '-';

export const formatDate = (d) => 
  d ? new Date(d).toLocaleDateString('fr-FR') : '-';

export const formatDateTime = (d) => 
  d ? new Date(d).toLocaleString('fr-FR') : '-';

// Helper pour obtenir les headers d'authentification depuis localStorage
const getAuthHeaders = () => {
  const token = localStorage.getItem('token');
  let tenantId = localStorage.getItem('tenant_id');
  if (!tenantId) {
    try {
      const tenant = JSON.parse(localStorage.getItem('tenant') || '{}');
      tenantId = tenant?.id;
    } catch {}
  }
  
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = String(tenantId);
  
  return headers;
};

// Helper pour faire des requêtes API avec authentification
export const api = {
  get: async (url) => {
    const headers = getAuthHeaders();
    console.log('[API GET]', url, 'Headers:', Object.keys(headers));
    const res = await fetch(`${API_URL}${url}`, { headers });
    if (!res.ok) {
      console.error('[API GET Error]', url, res.status);
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  },
  post: async (url, data) => {
    const headers = getAuthHeaders();
    console.log('[API POST]', url);
    const res = await fetch(`${API_URL}${url}`, {
      method: 'POST',
      headers,
      body: data ? JSON.stringify(data) : undefined,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      console.error('[API POST Error]', url, res.status, err);
      const error = new Error(err.message || err.error || `HTTP ${res.status}`);
      error.response = { data: err, status: res.status };
      throw error;
    }
    return res.json();
  },
  put: async (url, data) => {
    const headers = getAuthHeaders();
    const res = await fetch(`${API_URL}${url}`, {
      method: 'PUT',
      headers,
      body: JSON.stringify(data),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  },
  delete: async (url) => {
    const headers = getAuthHeaders();
    const res = await fetch(`${API_URL}${url}`, {
      method: 'DELETE',
      headers,
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  },
};
