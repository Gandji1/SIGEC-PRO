import { create } from 'zustand';

// Helper to safely get from localStorage
const getFromLocalStorage = (key, defaultValue = null) => {
  if (typeof window === 'undefined') return defaultValue;
  try {
    const item = localStorage.getItem(key);
    if (key === 'user' || key === 'tenant') {
      return item ? JSON.parse(item) : defaultValue;
    }
    return item || defaultValue;
  } catch (e) {
    console.warn(`Error reading localStorage[${key}]:`, e);
    return defaultValue;
  }
};

// Create store with proper hydration and migration support
export const useTenantStore = create((set) => {
  // Get initial values from localStorage
  let initialTenant = getFromLocalStorage('tenant');
  let initialUser = getFromLocalStorage('user');
  let initialToken = getFromLocalStorage('token');

  // Migration: if user.role is 'admin', it's outdated data. Clear it.
  if (initialUser && initialUser.role === 'admin') {
    console.warn('🔄 [Zustand] Detected outdated user role (admin). Clearing stale data.');
    initialTenant = null;
    initialUser = null;
    initialToken = null;
    localStorage.removeItem('tenant');
    localStorage.removeItem('user');
    localStorage.removeItem('token');
  }

  // Debug initial state
  if (typeof window !== 'undefined') {
    console.log('🟢 [Zustand] Store initialized:', {
      hasUser: !!initialUser,
      userRole: initialUser?.role,
      userName: initialUser?.name,
      userEmail: initialUser?.email,
      hasToken: !!initialToken,
      hasTenant: !!initialTenant,
      tenantName: initialTenant?.name,
      timestamp: new Date().toISOString()
    });
  }

  return {
    tenant: initialTenant,
    user: initialUser,
    token: initialToken,

    setTenant: (tenant) => {
      set({ tenant });
      if (tenant) {
        localStorage.setItem('tenant', JSON.stringify(tenant));
        console.log('🔵 [Zustand] Tenant set:', tenant.name);
      } else {
        localStorage.removeItem('tenant');
      }
    },
    
    setUser: (user) => {
      set({ user });
      if (user) {
        localStorage.setItem('user', JSON.stringify(user));
        console.log('🔵 [Zustand] User set:', { 
          email: user.email, 
          role: user.role,
          name: user.name 
        });
      } else {
        localStorage.removeItem('user');
      }
    },
    
    setToken: (token) => {
      set({ token });
      if (token) {
        localStorage.setItem('token', token);
        console.log('🔵 [Zustand] Token set (first 20 chars):', token.substring(0, 20) + '...');
      } else {
        localStorage.removeItem('token');
      }
    },

    logout: () => {
      set({ tenant: null, user: null, token: null });
      localStorage.removeItem('tenant');
      localStorage.removeItem('user');
      localStorage.removeItem('token');
      console.log('🔴 [Zustand] User logged out, store cleared');
    },

    // Helper function to manually sync from localStorage (for debugging/edge cases)
    hydrate: () => {
      const tenant = getFromLocalStorage('tenant');
      const user = getFromLocalStorage('user');
      const token = getFromLocalStorage('token');
      
      set({ tenant, user, token });
      
      console.log('🟣 [Zustand] Manually hydrated from localStorage:', {
        hasUser: !!user,
        userRole: user?.role,
        hasToken: !!token,
        hasTenant: !!tenant
      });
    }
  };
});
