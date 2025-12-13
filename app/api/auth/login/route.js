export async function POST(req) {
  try {
    const { email, password } = await req.json()

    // Vérifier les identifiants de démo
    if (email === 'demo@sigec.com' && password === 'password123') {
      return new Response(JSON.stringify({
        success: true,
        user: {
          id: 1,
          name: 'Demo User',
          email: 'demo@sigec.com',
          role: 'admin',
          tenant_id: 1
        },
        token: 'demo_token_' + Date.now()
      }), { status: 200 })
    }

    // En développement, essayer d'appeler le mock API si disponible
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'
    
    if (apiUrl && apiUrl !== 'http://localhost:8000') {
      try {
        const response = await fetch(`${apiUrl}/api/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password })
        })

        if (response.ok) {
          const data = await response.json()
          return new Response(JSON.stringify(data), { status: 200 })
        }
      } catch (e) {
        // Si backend n'est pas disponible, accepter les identifiants demo
      }
    }

    return new Response(JSON.stringify({ 
      success: false,
      error: 'Identifiants invalides. Utilisez: demo@sigec.com / password123' 
    }), { status: 401 })
  } catch (error) {
    console.error('Auth error:', error)
    return new Response(JSON.stringify({ 
      success: false,
      error: 'Erreur serveur' 
    }), { status: 500 })
  }
}
