import { memo } from 'react';
import { Package, Users, ShoppingCart, FileText, Search, Plus } from 'lucide-react';

/**
 * Composant EmptyState réutilisable
 * Affiche un message quand il n'y a pas de données
 */
const EmptyState = memo(({
  icon: CustomIcon,
  title = 'Aucune donnée',
  description = 'Il n\'y a rien à afficher pour le moment.',
  actionLabel,
  onAction,
  variant = 'default', // default, search, products, users, orders, documents
}) => {
  // Icônes prédéfinies par variant
  const variantIcons = {
    default: Package,
    search: Search,
    products: Package,
    users: Users,
    orders: ShoppingCart,
    documents: FileText,
  };

  const Icon = CustomIcon || variantIcons[variant] || Package;

  // Messages par défaut selon le variant
  const defaultMessages = {
    search: { title: 'Aucun résultat', description: 'Essayez avec d\'autres termes de recherche.' },
    products: { title: 'Aucun produit', description: 'Commencez par ajouter votre premier produit.' },
    users: { title: 'Aucun utilisateur', description: 'Invitez des collaborateurs à rejoindre votre équipe.' },
    orders: { title: 'Aucune commande', description: 'Les commandes apparaîtront ici.' },
    documents: { title: 'Aucun document', description: 'Les documents seront listés ici.' },
  };

  const message = defaultMessages[variant] || { title, description };
  const displayTitle = title !== 'Aucune donnée' ? title : message.title;
  const displayDesc = description !== 'Il n\'y a rien à afficher pour le moment.' ? description : message.description;

  return (
    <div className="flex flex-col items-center justify-center py-12 px-4">
      {/* Icon container */}
      <div className="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-2xl flex items-center justify-center mb-4">
        <Icon className="text-gray-400 dark:text-gray-500" size={32} />
      </div>

      {/* Title */}
      <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2 text-center">
        {displayTitle}
      </h3>

      {/* Description */}
      <p className="text-gray-500 dark:text-gray-400 text-sm text-center max-w-sm mb-6">
        {displayDesc}
      </p>

      {/* Action button */}
      {actionLabel && onAction && (
        <button
          onClick={onAction}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors"
        >
          <Plus size={18} />
          {actionLabel}
        </button>
      )}
    </div>
  );
});

export default EmptyState;
