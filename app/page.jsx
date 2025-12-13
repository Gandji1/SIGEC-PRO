'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'

export default function Home() {
  const router = useRouter()
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      router.push('/dashboard')
    }
  }, [router])

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-600 to-purple-700 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-xl p-8">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">SIGEC</h1>
          <p className="text-gray-600">Gestion des Stocks & Comptabilité</p>
        </div>

        <div className="space-y-4">
          <Link href="/login">
            <button className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">
              Se Connecter
            </button>
          </Link>

          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-300"></div>
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-2 bg-white text-gray-500">ou continuer avec</span>
            </div>
          </div>

          <Link href="/demo">
            <button className="w-full border-2 border-gray-300 hover:border-gray-400 text-gray-700 font-semibold py-3 px-4 rounded-lg transition">
              Mode Démonstration
            </button>
          </Link>
        </div>

        <div className="mt-8 pt-6 border-t border-gray-200">
          <p className="text-center text-sm text-gray-600 mb-4">Fonctionnalités principales :</p>
          <ul className="space-y-2 text-sm text-gray-700">
            <li className="flex items-center">
              <span className="text-blue-600 mr-2">✓</span> Gestion des stocks
            </li>
            <li className="flex items-center">
              <span className="text-blue-600 mr-2">✓</span> Gestion des ventes
            </li>
            <li className="flex items-center">
              <span className="text-blue-600 mr-2">✓</span> Gestion des achats
            </li>
            <li className="flex items-center">
              <span className="text-blue-600 mr-2">✓</span> Comptabilité intégrée
            </li>
          </ul>
        </div>
      </div>
    </div>
  )
}
