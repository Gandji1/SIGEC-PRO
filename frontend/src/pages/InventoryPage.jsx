import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * Ancienne page d'inventaire - redirige vers l'inventaire enrichi
 * @deprecated Utiliser EnrichedInventoryPage à la place
 */
export default function InventoryPage() {
  const navigate = useNavigate();
  
  useEffect(() => {
    // Redirection automatique vers l'inventaire enrichi
    navigate('/inventory-enriched', { replace: true });
  }, [navigate]);

  return (
    <div className="p-8 text-center">
      <p>Redirection vers l'inventaire enrichi...</p>
    </div>
  );
}
