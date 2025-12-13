'use client'

import { useState } from 'react'

export default function SalesPage() {
  const [sales, setSales] = useState([
    { id: 1, invoice: 'INV-001', customer: 'Client A', amount: 1500, items: 5, status: 'Complétée', date: '2025-11-24' },
    { id: 2, invoice: 'INV-002', customer: 'Client B', amount: 2300, items: 8, status: 'En cours', date: '2025-11-24' }
  ])

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-gray-900">Gestion des Ventes</h1>
        <button className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
          + Nouvelle Vente
        </button>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Facture</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {sales.map((sale) => (
              <tr key={sale.id}>
                <td className="px-6 py-4 text-sm font-medium text-gray-900">{sale.invoice}</td>
                <td className="px-6 py-4 text-sm text-gray-600">{sale.customer}</td>
                <td className="px-6 py-4 text-sm text-gray-900 font-semibold">${sale.amount}</td>
                <td className="px-6 py-4 text-sm text-gray-600">{sale.items}</td>
                <td className="px-6 py-4 text-sm">
                  <span className={`px-2 py-1 rounded text-white ${sale.status === 'Complétée' ? 'bg-green-500' : 'bg-yellow-500'}`}>
                    {sale.status}
                  </span>
                </td>
                <td className="px-6 py-4 text-sm text-gray-600">{sale.date}</td>
                <td className="px-6 py-4 text-sm">
                  <button className="text-blue-600 hover:text-blue-700 mr-4">Voir</button>
                  <button className="text-red-600 hover:text-red-700">Annuler</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
