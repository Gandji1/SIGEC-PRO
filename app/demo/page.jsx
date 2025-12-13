'use client'

import Link from 'next/link'
import { useState } from 'react'

export default function DemoPage() {
  const [demoMode, setDemoMode] = useState(false)

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-2xl p-8 max-w-2xl w-full">
        <h1 className="text-4xl font-bold text-center text-gray-900 mb-4">Bienvenue en Mode Démo</h1>
        <p className="text-center text-gray-600 mb-8">
          Découvrez les fonctionnalités de SIGEC sans créer de compte
        </p>

        {!demoMode ? (
          <div className="space-y-6">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 space-y-4">
              <h2 className="text-xl font-semibold text-gray-900">Fonctionnalités Disponibles</h2>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-3">✓</span>
                  <span className="text-gray-700">Tableau de bord avec statistiques en temps réel</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-3">✓</span>
                  <span className="text-gray-700">Gestion des stocks et inventaires</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-3">✓</span>
                  <span className="text-gray-700">Suivi des ventes et achats</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-3">✓</span>
                  <span className="text-gray-700">Rapports et analyses comptables</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-3">✓</span>
                  <span className="text-gray-700">Export PDF et Excel</span>
                </li>
              </ul>
            </div>

            <div className="bg-purple-50 border border-purple-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">Identifiants de Test</h2>
              <div className="space-y-2">
                <p className="text-gray-700">
                  <span className="font-semibold">Email:</span> <code className="bg-gray-200 px-2 py-1 rounded">demo@sigec.com</code>
                </p>
                <p className="text-gray-700">
                  <span className="font-semibold">Mot de passe:</span> <code className="bg-gray-200 px-2 py-1 rounded">password123</code>
                </p>
              </div>
            </div>

            <div className="flex gap-4">
              <button
                onClick={() => setDemoMode(true)}
                className="flex-1 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition"
              >
                Démarrer la Démo
              </button>
              <Link
                href="/login"
                className="flex-1 px-6 py-3 border-2 border-blue-600 text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition text-center"
              >
                Se Connecter
              </Link>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="bg-green-50 border border-green-200 rounded-lg p-6">
              <p className="text-green-800">✓ Mode démo activé ! Vous pouvez explorer librement.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <DemoCard title="Tableau de Bord" href="/dashboard" icon="📊" />
              <DemoCard title="Tenants" href="/dashboard/tenants" icon="🏢" />
              <DemoCard title="Collaborateurs" href="/dashboard/users" icon="👥" />
              <DemoCard title="Approvisionnement" href="/dashboard/procurement" icon="📦" />
              <DemoCard title="Ventes" href="/dashboard/sales" icon="💰" />
              <DemoCard title="Charges" href="/dashboard/expenses" icon="💳" />
              <DemoCard title="Rapports" href="/dashboard/reports" icon="📈" />
              <DemoCard title="Export" href="/dashboard/export" icon="📄" />
            </div>

            <button
              onClick={() => setDemoMode(false)}
              className="w-full px-6 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition"
            >
              Retour
            </button>
          </div>
        )}

        <div className="mt-8 text-center">
          <Link href="/" className="text-blue-600 hover:text-blue-700 font-semibold">
            ← Retour à l'accueil
          </Link>
        </div>
      </div>
    </div>
  )
}

function DemoCard({ title, href, icon }) {
  return (
    <Link
      href={href}
      className="bg-gray-50 hover:bg-blue-50 border border-gray-200 rounded-lg p-4 text-center transition"
    >
      <div className="text-3xl mb-2">{icon}</div>
      <div className="font-semibold text-gray-900">{title}</div>
    </Link>
  )
}
