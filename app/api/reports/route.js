export async function GET(req) {
  try {
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

    const response = await fetch(`${apiUrl}/api/reports`, {
      headers: {
        'Authorization': `Bearer ${req.headers.get('authorization')?.replace('Bearer ', '')}`
      }
    })

    if (!response.ok) {
      return new Response(JSON.stringify({ error: 'Impossible de récupérer les rapports' }), {
        status: response.status
      })
    }

    const data = await response.json()
    return new Response(JSON.stringify(data), { status: 200 })
  } catch (error) {
    console.error('Reports error:', error)
    return new Response(JSON.stringify({ error: 'Erreur serveur' }), {
      status: 500
    })
  }
}

export async function POST(req) {
  try {
    const body = await req.json()
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

    const response = await fetch(`${apiUrl}/api/reports/generate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${req.headers.get('authorization')?.replace('Bearer ', '')}`
      },
      body: JSON.stringify(body)
    })

    if (!response.ok) {
      return new Response(JSON.stringify({ error: 'Impossible de générer le rapport' }), {
        status: response.status
      })
    }

    const data = await response.json()
    return new Response(JSON.stringify(data), { status: 201 })
  } catch (error) {
    console.error('Reports POST error:', error)
    return new Response(JSON.stringify({ error: 'Erreur serveur' }), {
      status: 500
    })
  }
}
