import React from 'react';
import { formatCurrency } from './utils';

export const KPI = ({ title, value, format, color, icon }) => {
  const colors = {
    blue: 'from-blue-500 to-blue-600',
    green: 'from-green-500 to-green-600',
    yellow: 'from-yellow-500 to-yellow-600',
    red: 'from-red-500 to-red-600',
    purple: 'from-purple-500 to-purple-600',
    indigo: 'from-indigo-500 to-indigo-600',
    orange: 'from-orange-500 to-orange-600',
  };
  // Afficher la valeur correctement - 0 est une valeur valide
  const displayValue = value === undefined || value === null 
    ? '-' 
    : format === 'currency' 
      ? formatCurrency(value) 
      : value;
  return (
    <div className={`bg-gradient-to-br ${colors[color]} rounded-xl p-4 text-white shadow-lg`}>
      <div className="flex justify-between items-start">
        <div>
          <p className="text-white/80 text-sm font-medium">{title}</p>
          <p className="text-2xl font-bold mt-1">{displayValue}</p>
        </div>
        <span className="text-3xl opacity-80">{icon}</span>
      </div>
    </div>
  );
};

export const NavBtn = ({ active, onClick, icon, label, count, color = 'blue' }) => {
  const activeClass = color === 'green' 
    ? 'bg-green-600 text-white shadow-md' 
    : 'bg-blue-600 text-white shadow-md';
  const countClass = color === 'green' 
    ? 'bg-green-100 text-green-700' 
    : 'bg-blue-100 text-blue-700';
  return (
    <button
      onClick={onClick}
      className={`px-4 py-2 rounded-lg font-medium transition-all flex items-center gap-2 ${
        active ? activeClass : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
      }`}
    >
      <span>{icon}</span> {label}
      {count > 0 && (
        <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
          active ? 'bg-white/20 text-white' : countClass
        }`}>
          {count}
        </span>
      )}
    </button>
  );
};

export const Card = ({ title, subtitle, action, className, children }) => (
  <div className={`bg-white rounded-xl shadow-sm overflow-hidden ${className || ''}`}>
    <div className="p-4 border-b bg-gray-50 flex justify-between items-center">
      <div>
        <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
        {subtitle && <p className="text-sm text-gray-500">{subtitle}</p>}
      </div>
      {action && (
        <button
          onClick={action.onClick}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700"
        >
          {action.label}
        </button>
      )}
    </div>
    <div className="p-4">{children}</div>
  </div>
);

export const Empty = ({ msg }) => (
  <p className="text-center text-gray-500 py-8">{msg}</p>
);

export const StatusBadge = ({ status }) => {
  const cfg = {
    draft: 'bg-gray-100 text-gray-700',
    pending_approval: 'bg-orange-100 text-orange-700', // Nouveau: attente approbation Tenant
    submitted: 'bg-blue-100 text-blue-700', // Envoyé au fournisseur
    ordered: 'bg-blue-100 text-blue-700',
    confirmed: 'bg-indigo-100 text-indigo-700',
    shipped: 'bg-purple-100 text-purple-700', // Préparé par fournisseur
    delivered: 'bg-teal-100 text-teal-700', // Livré par fournisseur
    partial: 'bg-yellow-100 text-yellow-700',
    received: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    requested: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-red-100 text-red-700',
    executed: 'bg-indigo-100 text-indigo-700',
    validated: 'bg-green-100 text-green-700',
    active: 'bg-green-100 text-green-700',
    preparing: 'bg-yellow-100 text-yellow-700',
    served: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
  };
  const labels = {
    draft: 'Brouillon',
    pending_approval: 'Attente Approbation',
    submitted: 'Chez Fournisseur',
    ordered: 'Commandé',
    confirmed: 'Approuvé Fournisseur',
    shipped: 'En Préparation',
    delivered: '⏳ Attente Réception',
    partial: 'Partiel',
    received: '✓ Réceptionné',
    pending: 'En attente',
    requested: 'Demandé',
    approved: 'Approuvé',
    rejected: 'Rejeté',
    cancelled: 'Annulé',
    executed: 'Exécuté',
    validated: 'Validé',
    active: 'Actif',
    preparing: 'En préparation',
    served: 'Servi',
    paid: '✓ Payé',
  };
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium ${cfg[status] || 'bg-gray-100'}`}>
      {labels[status] || status}
    </span>
  );
};

export const PriorityBadge = ({ priority }) => {
  const cfg = {
    low: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
  };
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium ${cfg[priority] || cfg.normal}`}>
      {priority}
    </span>
  );
};

export const MovementBadge = ({ type }) => {
  const cfg = {
    purchase: 'bg-green-100 text-green-700',
    transfer: 'bg-blue-100 text-blue-700',
    sale: 'bg-purple-100 text-purple-700',
    adjustment: 'bg-yellow-100 text-yellow-700',
  };
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium ${cfg[type] || 'bg-gray-100'}`}>
      {type}
    </span>
  );
};

export const Modal = ({ title, children, onClose, wide }) => (
  <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div className={`bg-white rounded-xl shadow-2xl ${wide ? 'max-w-4xl' : 'max-w-lg'} w-full max-h-[90vh] overflow-hidden flex flex-col`}>
      <div className="p-4 border-b flex justify-between items-center bg-gray-50">
        <h2 className="font-semibold text-lg">{title}</h2>
        <button onClick={onClose} className="p-2 hover:bg-gray-200 rounded-lg text-gray-500">✕</button>
      </div>
      <div className="p-6 overflow-y-auto flex-1">{children}</div>
    </div>
  </div>
);

export const Loading = () => (
  <div className="flex justify-center items-center py-20">
    <div className="animate-spin h-12 w-12 border-4 border-blue-500 border-t-transparent rounded-full"></div>
  </div>
);
