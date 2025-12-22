import React from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { getAccessibleRoutes } from '../utils/rbac';

export default function DebugPage() {
  const { user, tenant, token } = useTenantStore();
  
  const userRole = user?.role || 'auditor';
  const pos_option = user?.pos_option || 'A';
  const menuItems = Object.values(getAccessibleRoutes(userRole, pos_option));
  
  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="max-w-4xl mx-auto">
        <h1 className="text-4xl font-bold text-gray-900 mb-8">🔍 Debug Information</h1>
        
        {/* User Information */}
        <div className="bg-white rounded-lg shadow mb-8 p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">User Information</h2>
          <div className="space-y-3 text-sm font-mono">
            <div className="flex">
              <span className="w-32 text-gray-600">Name:</span>
              <span className="text-gray-900">{user?.name || 'N/A'}</span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">Email:</span>
              <span className="text-gray-900">{user?.email || 'N/A'}</span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">Role:</span>
              <span className={`px-3 py-1 rounded-full ${user?.role === 'owner' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'}`}>
                {user?.role || 'UNDEFINED'}
              </span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">ID:</span>
              <span className="text-gray-900">{user?.id || 'N/A'}</span>
            </div>
          </div>
        </div>

        {/* Tenant Information */}
        <div className="bg-white rounded-lg shadow mb-8 p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Tenant Information</h2>
          <div className="space-y-3 text-sm font-mono">
            <div className="flex">
              <span className="w-32 text-gray-600">Name:</span>
              <span className="text-gray-900">{tenant?.name || 'N/A'}</span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">ID:</span>
              <span className="text-gray-900">{tenant?.id || 'N/A'}</span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">Business Type:</span>
              <span className="text-gray-900">{tenant?.business_type || 'N/A'}</span>
            </div>
          </div>
        </div>

        {/* Authentication */}
        <div className="bg-white rounded-lg shadow mb-8 p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Authentication</h2>
          <div className="space-y-3 text-sm font-mono">
            <div className="flex">
              <span className="w-32 text-gray-600">Token Exists:</span>
              <span className={token ? 'text-green-600 font-bold' : 'text-red-600 font-bold'}>
                {token ? '✅ YES' : '❌ NO'}
              </span>
            </div>
            <div className="flex">
              <span className="w-32 text-gray-600">Token (first 20):</span>
              <span className="text-gray-900">{token ? token.substring(0, 20) + '...' : 'N/A'}</span>
            </div>
          </div>
        </div>

        {/* Menu Items */}
        <div className="bg-white rounded-lg shadow mb-8 p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Accessible Menu Items</h2>
          <p className="text-sm text-gray-600 mb-4">
            Role: <strong>{userRole}</strong> | Total items: <strong>{menuItems.length}</strong>
          </p>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {menuItems.map((item, idx) => (
              <div key={idx} className="bg-gray-50 p-4 rounded border border-gray-200">
                <div className="flex items-center gap-2 mb-2">
                  <span className="text-2xl">{item.icon}</span>
                  <span className="font-semibold text-gray-900">{item.label}</span>
                </div>
                <a 
                  href={item.path}
                  className="text-blue-600 hover:underline text-sm"
                >
                  {item.path}
                </a>
              </div>
            ))}
          </div>
          {menuItems.length === 0 && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
              ⚠️ No menu items available!
            </div>
          )}
        </div>

        {/* Navigation Link */}
        <div className="bg-white rounded-lg shadow p-6">
          <a 
            href="/dashboard"
            className="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition"
          >
            ← Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  );
}
