import { NextResponse } from 'next/server'

export function middleware(request) {
  // Rediriger les anciennes URLs
  if (request.nextUrl.pathname === '/old-path') {
    return NextResponse.redirect(new URL('/', request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    '/((?!_next/static|_next/image|favicon.ico).*)',
  ],
}
