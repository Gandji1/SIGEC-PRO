import './globals.css'

export const metadata = {
  title: 'SIGEC - Gestion des Stocks & Comptabilité',
  description: 'Système complet de gestion des stocks, ventes, achats et comptabilité',
}

export default function RootLayout({ children }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  )
}
