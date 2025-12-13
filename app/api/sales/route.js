export async function GET(req) {
  try {
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

    const response = await fetch(`${apiUrl}/api/sales`, {
      headers: {
        'Authorization': `Bearer ${req.headers.get('authorization')?.replace('Bearer ', '')}`
      }
    })

    if (!response.ok) {
      return new Response(JSON.stringify({ error: 'Impossible de récupérer les ventes' }), {
        status: response.status
      })
    }

    const data = await response.json()
    return new Response(JSON.stringify(data), { status: 200 })
  } catch (error) {
    console.error('Sales error:', error)
    return new Response(JSON.stringify({ error: 'Erreur serveur' }), {
      status: 500
    })
  }
}

export async function POST(req) {
  try {
    const body = await req.json()
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

    const response = await fetch(`${apiUrl}/api/sales`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${req.headers.get('authorization')?.replace('Bearer ', '')}`
      },
      body: JSON.stringify(body)
    })

    if (!response.ok) {
      return new Response(JSON.stringify({ error: 'Impossible de créer la vente' }), {
        status: response.status
      })
    }

    const data = await response.json()
    return new Response(JSON.stringify(data), { status: 201 })
  } catch (error) {
    console.error('Sales POST error:', error)
    return new Response(JSON.stringify({ error: 'Erreur serveur' }), {
      status: 500
    })
  }
}
