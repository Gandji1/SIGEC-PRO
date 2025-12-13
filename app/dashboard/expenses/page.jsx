'use client'

import { useState } from 'react'

export default function ExpensesPage() {
  const [expenses, setExpenses] = useState([
    { id: 1, category: 'Salaires', amount: 5000, date: '2025-11-20', status: 'Payée' },
    { id: 2, category: 'Loyer', amount: 1500, date: '2025-11-01', status: 'Payée' },
    { id: 3, category: 'Électricité', amount: 300, date: '2025-11-15', status: 'En attente' }
  ])

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-gray-900">Charges</h1>
        <button className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
          + Ajouter Charge
        </button>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {expenses.map((expense) => (
              <tr key={expense.id}>
                <td className="px-6 py-4 text-sm font-medium text-gray-900">{expense.category}</td>
                <td className="px-6 py-4 text-sm text-gray-900 font-semibold">${expense.amount}</td>
                <td className="px-6 py-4 text-sm text-gray-600">{expense.date}</td>
                <td className="px-6 py-4 text-sm">
                  <span className={`px-2 py-1 rounded text-white ${expense.status === 'Payée' ? 'bg-green-500' : 'bg-orange-500'}`}>
                    {expense.status}
                  </span>
                </td>
                <td className="px-6 py-4 text-sm">
                  <button className="text-blue-600 hover:text-blue-700 mr-4">Éditer</button>
                  <button className="text-red-600 hover:text-red-700">Supprimer</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
