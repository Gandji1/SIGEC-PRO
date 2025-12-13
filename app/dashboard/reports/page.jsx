'use client'

import { useState } from 'react'

export default function ReportsPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900">Rapports</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <ReportCard
          title="Rapport de Ventes"
          description="Analyse complète des ventes par période"
          icon="📊"
          color="bg-blue-500"
        />
        <ReportCard
          title="Rapport d'Achats"
          description="Historique et analyse des achats"
          icon="📦"
          color="bg-green-500"
        />
        <ReportCard
          title="Rapport de Stock"
          description="État de l'inventaire et alertes"
          icon="📈"
          color="bg-purple-500"
        />
        <ReportCard
          title="Rapport Financier"
          description="Bilan et compte de résultat"
          icon="💰"
          color="bg-orange-500"
        />
        <ReportCard
          title="Rapport de Trésorerie"
          description="Flux de trésorerie et prévisions"
          icon="💳"
          color="bg-red-500"
        />
        <ReportCard
          title="Rapport des Clients"
          description="Analyse des clients et transactions"
          icon="👥"
          color="bg-pink-500"
        />
      </div>
    </div>
  )
}

function ReportCard({ title, description, icon, color }) {
  return (
    <div className={`${color} rounded-lg shadow p-6 text-white cursor-pointer hover:shadow-lg transition`}>
      <div className="text-4xl mb-4">{icon}</div>
      <h3 className="text-lg font-semibold mb-2">{title}</h3>
      <p className="text-sm opacity-90">{description}</p>
      <button className="mt-4 px-4 py-2 bg-white/20 hover:bg-white/30 rounded text-sm font-medium transition">
        Générer
      </button>
    </div>
  )
}
