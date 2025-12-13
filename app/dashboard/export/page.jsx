'use client'

export default function ExportPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900">Export PDF</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <ExportCard
          title="Export Factures"
          description="Exporter les factures en PDF"
          formats={['PDF', 'Excel']}
        />
        <ExportCard
          title="Export Rapports"
          description="Exporter les rapports générés"
          formats={['PDF', 'Excel', 'CSV']}
        />
        <ExportCard
          title="Export Inventaire"
          description="Exporter l'état du stock"
          formats={['PDF', 'Excel']}
        />
        <ExportCard
          title="Export Comptabilité"
          description="Exporter les écritures comptables"
          formats={['PDF', 'Excel', 'CSV']}
        />
      </div>
    </div>
  )
}

function ExportCard({ title, description, formats }) {
  return (
    <div className="bg-white rounded-lg shadow p-6">
      <h3 className="text-lg font-semibold text-gray-900 mb-2">{title}</h3>
      <p className="text-gray-600 mb-4">{description}</p>
      <div className="space-y-2">
        {formats.map((format) => (
          <button
            key={format}
            className="w-full px-4 py-2 border-2 border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white rounded-lg font-medium transition"
          >
            Exporter en {format}
          </button>
        ))}
      </div>
    </div>
  )
}
