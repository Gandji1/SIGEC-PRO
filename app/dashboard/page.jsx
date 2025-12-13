'use client'

import { useState, useEffect } from 'react'

export default function DashboardPage() {
  const [stats, setStats] = useState({
    totalSales: 0,
    totalPurchases: 0,
    stockValue: 0,
    transactions: 0
  })
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const response = await fetch('/api/stats')
        if (response.ok) {
          const data = await response.json()
          setStats(data.data || stats)
        }
      } catch (err) {
        console.error('Error fetching stats:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchStats()
  }, [])

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900">Tableau de Bord</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Ventes"
          value={`$${stats.totalSales}`}
          icon="💰"
          color="bg-blue-500"
        />
        <StatCard
          title="Achats"
          value={`$${stats.totalPurchases}`}
          icon="📦"
          color="bg-green-500"
        />
        <StatCard
          title="Valeur Stock"
          value={`$${stats.stockValue}`}
          icon="📊"
          color="bg-purple-500"
        />
        <StatCard
          title="Transactions"
          value={stats.transactions}
          icon="💳"
          color="bg-orange-500"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        <QuickAccessCard
          title="Gestion Tenant"
          description="Créer et gérer les locataires"
          href="/dashboard/tenants"
          icon="🏢"
        />
        <QuickAccessCard
          title="Ventes"
          description="Enregistrer et suivre les ventes"
          href="/dashboard/sales"
          icon="💰"
        />
        <QuickAccessCard
          title="Approvisionnement"
          description="Gérer les stocks et commandes"
          href="/dashboard/procurement"
          icon="📦"
        />
        <QuickAccessCard
          title="Rapports"
          description="Voir les rapports et analyses"
          href="/dashboard/reports"
          icon="📈"
        />
      </div>
    </div>
  )
}

function StatCard({ title, value, icon, color }) {
  return (
    <div className={`${color} rounded-lg shadow p-6 text-white`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm opacity-75">{title}</p>
          <p className="text-2xl font-bold mt-2">{value}</p>
        </div>
        <div className="text-4xl">{icon}</div>
      </div>
    </div>
  )
}

function QuickAccessCard({ title, description, href, icon }) {
  return (
    <a href={href} className="bg-white rounded-lg shadow p-6 hover:shadow-lg transition cursor-pointer">
      <div className="flex items-start justify-between">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
          <p className="text-gray-600 mt-1">{description}</p>
        </div>
        <div className="text-3xl">{icon}</div>
      </div>
    </a>
  )
}
