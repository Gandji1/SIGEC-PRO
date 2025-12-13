import { create } from 'zustand';
import { persist } from 'zustand/middleware';

// Traductions FR/EN
const translations = {
  fr: {
    // Navigation
    'nav.home': 'Accueil',
    'nav.dashboard': 'Tableau de bord',
    'nav.pos': 'Point de Vente',
    'nav.orders': 'Commandes',
    'nav.myOrders': 'Mes Commandes',
    'nav.products': 'Produits',
    'nav.inventory': 'Inventaire',
    'nav.suppliers': 'Fournisseurs',
    'nav.customers': 'Clients',
    'nav.users': 'Utilisateurs',
    'nav.settings': 'Paramètres',
    'nav.reports': 'Rapports',
    'nav.accounting': 'Comptabilité',
    'nav.cashRegister': 'Caisse',
    'nav.expenses': 'Charges',
    'nav.logout': 'Déconnexion',
    
    // Actions communes
    'action.add': 'Ajouter',
    'action.edit': 'Modifier',
    'action.delete': 'Supprimer',
    'action.save': 'Enregistrer',
    'action.cancel': 'Annuler',
    'action.confirm': 'Confirmer',
    'action.search': 'Rechercher',
    'action.filter': 'Filtrer',
    'action.export': 'Exporter',
    'action.import': 'Importer',
    'action.print': 'Imprimer',
    'action.refresh': 'Actualiser',
    'action.back': 'Retour',
    'action.next': 'Suivant',
    'action.previous': 'Précédent',
    'action.submit': 'Soumettre',
    'action.validate': 'Valider',
    'action.approve': 'Approuver',
    'action.reject': 'Rejeter',
    'action.close': 'Fermer',
    'action.open': 'Ouvrir',
    'action.view': 'Voir',
    'action.download': 'Télécharger',
    'action.upload': 'Téléverser',
    'action.send': 'Envoyer',
    'action.reset': 'Réinitialiser',
    'action.clear': 'Effacer',
    'action.select': 'Sélectionner',
    'action.selectAll': 'Tout sélectionner',
    'action.deselectAll': 'Tout désélectionner',
    
    // POS
    'pos.title': 'Point de Vente',
    'pos.products': 'Produits',
    'pos.cart': 'Panier',
    'pos.total': 'Total',
    'pos.subtotal': 'Sous-total',
    'pos.tax': 'Taxes',
    'pos.discount': 'Remise',
    'pos.quantity': 'Quantité',
    'pos.price': 'Prix',
    'pos.unitPrice': 'Prix unitaire',
    'pos.addToCart': 'Ajouter au panier',
    'pos.removeFromCart': 'Retirer du panier',
    'pos.clearCart': 'Vider le panier',
    'pos.checkout': 'Passer la commande',
    'pos.payment': 'Paiement',
    'pos.cash': 'Espèces',
    'pos.card': 'Carte',
    'pos.mobile': 'Mobile Money',
    'pos.change': 'Monnaie à rendre',
    'pos.received': 'Montant reçu',
    'pos.table': 'Table',
    'pos.customer': 'Client',
    'pos.notes': 'Notes',
    'pos.searchProducts': 'Rechercher des produits...',
    'pos.noProducts': 'Aucun produit trouvé',
    'pos.emptyCart': 'Panier vide',
    'pos.orderSuccess': 'Commande créée avec succès',
    'pos.orderError': 'Erreur lors de la création de la commande',
    'pos.selectTable': 'Sélectionner une table',
    'pos.selectCustomer': 'Sélectionner un client',
    'pos.manual': 'Manuel',
    'pos.facturette': 'Facturette',
    
    // Commandes
    'orders.title': 'Commandes',
    'orders.pending': 'En attente',
    'orders.approved': 'Approuvée',
    'orders.preparing': 'En préparation',
    'orders.ready': 'Prête',
    'orders.served': 'Servie',
    'orders.paid': 'Payée',
    'orders.cancelled': 'Annulée',
    'orders.status': 'Statut',
    'orders.date': 'Date',
    'orders.reference': 'Référence',
    'orders.items': 'Articles',
    'orders.approve': 'Approuver',
    'orders.prepare': 'Préparer',
    'orders.markReady': 'Marquer prête',
    'orders.serve': 'Servir',
    'orders.pay': 'Payer',
    
    // Produits
    'products.title': 'Produits',
    'products.name': 'Nom',
    'products.code': 'Code',
    'products.category': 'Catégorie',
    'products.price': 'Prix',
    'products.stock': 'Stock',
    'products.description': 'Description',
    'products.addProduct': 'Ajouter un produit',
    'products.editProduct': 'Modifier le produit',
    'products.deleteProduct': 'Supprimer le produit',
    
    // Utilisateurs
    'users.title': 'Utilisateurs',
    'users.name': 'Nom',
    'users.email': 'Email',
    'users.role': 'Rôle',
    'users.status': 'Statut',
    'users.active': 'Actif',
    'users.inactive': 'Inactif',
    'users.addUser': 'Ajouter un utilisateur',
    'users.editUser': 'Modifier l\'utilisateur',
    
    // Authentification
    'auth.login': 'Connexion',
    'auth.logout': 'Déconnexion',
    'auth.email': 'Email',
    'auth.password': 'Mot de passe',
    'auth.confirmPassword': 'Confirmer le mot de passe',
    'auth.forgotPassword': 'Mot de passe oublié ?',
    'auth.register': 'S\'inscrire',
    'auth.loginButton': 'Se connecter',
    'auth.registerButton': 'Créer un compte',
    'auth.noAccount': 'Pas encore de compte ?',
    'auth.hasAccount': 'Déjà un compte ?',
    
    // Messages
    'msg.success': 'Succès',
    'msg.error': 'Erreur',
    'msg.warning': 'Attention',
    'msg.info': 'Information',
    'msg.loading': 'Chargement...',
    'msg.noData': 'Aucune donnée',
    'msg.confirmDelete': 'Êtes-vous sûr de vouloir supprimer ?',
    'msg.saved': 'Enregistré avec succès',
    'msg.deleted': 'Supprimé avec succès',
    'msg.updated': 'Mis à jour avec succès',
    'msg.created': 'Créé avec succès',
    
    // Dates et heures
    'date.today': 'Aujourd\'hui',
    'date.yesterday': 'Hier',
    'date.thisWeek': 'Cette semaine',
    'date.thisMonth': 'Ce mois',
    'date.thisYear': 'Cette année',
    
    // Divers
    'misc.language': 'Langue',
    'misc.french': 'Français',
    'misc.english': 'Anglais',
    'misc.theme': 'Thème',
    'misc.lightMode': 'Mode clair',
    'misc.darkMode': 'Mode sombre',
    'misc.all': 'Tous',
    'misc.none': 'Aucun',
    'misc.yes': 'Oui',
    'misc.no': 'Non',
    'misc.or': 'ou',
    'misc.and': 'et',
    'misc.loading': 'Chargement...',
    'misc.welcome': 'Bienvenue',
  },
  
  en: {
    // Navigation
    'nav.home': 'Home',
    'nav.dashboard': 'Dashboard',
    'nav.pos': 'Point of Sale',
    'nav.orders': 'Orders',
    'nav.myOrders': 'My Orders',
    'nav.products': 'Products',
    'nav.inventory': 'Inventory',
    'nav.suppliers': 'Suppliers',
    'nav.customers': 'Customers',
    'nav.users': 'Users',
    'nav.settings': 'Settings',
    'nav.reports': 'Reports',
    'nav.accounting': 'Accounting',
    'nav.cashRegister': 'Cash Register',
    'nav.expenses': 'Expenses',
    'nav.logout': 'Logout',
    
    // Common actions
    'action.add': 'Add',
    'action.edit': 'Edit',
    'action.delete': 'Delete',
    'action.save': 'Save',
    'action.cancel': 'Cancel',
    'action.confirm': 'Confirm',
    'action.search': 'Search',
    'action.filter': 'Filter',
    'action.export': 'Export',
    'action.import': 'Import',
    'action.print': 'Print',
    'action.refresh': 'Refresh',
    'action.back': 'Back',
    'action.next': 'Next',
    'action.previous': 'Previous',
    'action.submit': 'Submit',
    'action.validate': 'Validate',
    'action.approve': 'Approve',
    'action.reject': 'Reject',
    'action.close': 'Close',
    'action.open': 'Open',
    'action.view': 'View',
    'action.download': 'Download',
    'action.upload': 'Upload',
    'action.send': 'Send',
    'action.reset': 'Reset',
    'action.clear': 'Clear',
    'action.select': 'Select',
    'action.selectAll': 'Select All',
    'action.deselectAll': 'Deselect All',
    
    // POS
    'pos.title': 'Point of Sale',
    'pos.products': 'Products',
    'pos.cart': 'Cart',
    'pos.total': 'Total',
    'pos.subtotal': 'Subtotal',
    'pos.tax': 'Tax',
    'pos.discount': 'Discount',
    'pos.quantity': 'Quantity',
    'pos.price': 'Price',
    'pos.unitPrice': 'Unit Price',
    'pos.addToCart': 'Add to Cart',
    'pos.removeFromCart': 'Remove from Cart',
    'pos.clearCart': 'Clear Cart',
    'pos.checkout': 'Checkout',
    'pos.payment': 'Payment',
    'pos.cash': 'Cash',
    'pos.card': 'Card',
    'pos.mobile': 'Mobile Money',
    'pos.change': 'Change',
    'pos.received': 'Amount Received',
    'pos.table': 'Table',
    'pos.customer': 'Customer',
    'pos.notes': 'Notes',
    'pos.searchProducts': 'Search products...',
    'pos.noProducts': 'No products found',
    'pos.emptyCart': 'Cart is empty',
    'pos.orderSuccess': 'Order created successfully',
    'pos.orderError': 'Error creating order',
    'pos.selectTable': 'Select a table',
    'pos.selectCustomer': 'Select a customer',
    'pos.manual': 'Manual',
    'pos.facturette': 'Receipt',
    
    // Orders
    'orders.title': 'Orders',
    'orders.pending': 'Pending',
    'orders.approved': 'Approved',
    'orders.preparing': 'Preparing',
    'orders.ready': 'Ready',
    'orders.served': 'Served',
    'orders.paid': 'Paid',
    'orders.cancelled': 'Cancelled',
    'orders.status': 'Status',
    'orders.date': 'Date',
    'orders.reference': 'Reference',
    'orders.items': 'Items',
    'orders.approve': 'Approve',
    'orders.prepare': 'Prepare',
    'orders.markReady': 'Mark Ready',
    'orders.serve': 'Serve',
    'orders.pay': 'Pay',
    
    // Products
    'products.title': 'Products',
    'products.name': 'Name',
    'products.code': 'Code',
    'products.category': 'Category',
    'products.price': 'Price',
    'products.stock': 'Stock',
    'products.description': 'Description',
    'products.addProduct': 'Add Product',
    'products.editProduct': 'Edit Product',
    'products.deleteProduct': 'Delete Product',
    
    // Users
    'users.title': 'Users',
    'users.name': 'Name',
    'users.email': 'Email',
    'users.role': 'Role',
    'users.status': 'Status',
    'users.active': 'Active',
    'users.inactive': 'Inactive',
    'users.addUser': 'Add User',
    'users.editUser': 'Edit User',
    
    // Authentication
    'auth.login': 'Login',
    'auth.logout': 'Logout',
    'auth.email': 'Email',
    'auth.password': 'Password',
    'auth.confirmPassword': 'Confirm Password',
    'auth.forgotPassword': 'Forgot Password?',
    'auth.register': 'Register',
    'auth.loginButton': 'Sign In',
    'auth.registerButton': 'Create Account',
    'auth.noAccount': 'Don\'t have an account?',
    'auth.hasAccount': 'Already have an account?',
    
    // Messages
    'msg.success': 'Success',
    'msg.error': 'Error',
    'msg.warning': 'Warning',
    'msg.info': 'Information',
    'msg.loading': 'Loading...',
    'msg.noData': 'No data',
    'msg.confirmDelete': 'Are you sure you want to delete?',
    'msg.saved': 'Saved successfully',
    'msg.deleted': 'Deleted successfully',
    'msg.updated': 'Updated successfully',
    'msg.created': 'Created successfully',
    
    // Dates and times
    'date.today': 'Today',
    'date.yesterday': 'Yesterday',
    'date.thisWeek': 'This Week',
    'date.thisMonth': 'This Month',
    'date.thisYear': 'This Year',
    
    // Misc
    'misc.language': 'Language',
    'misc.french': 'French',
    'misc.english': 'English',
    'misc.theme': 'Theme',
    'misc.lightMode': 'Light Mode',
    'misc.darkMode': 'Dark Mode',
    'misc.all': 'All',
    'misc.none': 'None',
    'misc.yes': 'Yes',
    'misc.no': 'No',
    'misc.or': 'or',
    'misc.and': 'and',
    'misc.loading': 'Loading...',
    'misc.welcome': 'Welcome',
  }
};

export const useLanguageStore = create(
  persist(
    (set, get) => ({
      language: 'fr', // Français par défaut
      
      setLanguage: (lang) => set({ language: lang }),
      
      toggleLanguage: () => set((state) => ({ 
        language: state.language === 'fr' ? 'en' : 'fr' 
      })),
      
      // Fonction de traduction
      t: (key) => {
        const { language } = get();
        return translations[language]?.[key] || translations['fr']?.[key] || key;
      },
      
      // Obtenir toutes les traductions pour la langue courante
      getTranslations: () => {
        const { language } = get();
        return translations[language] || translations['fr'];
      }
    }),
    {
      name: 'sigec-language',
    }
  )
);

// Hook utilitaire pour la traduction
export const useTranslation = () => {
  const { t, language, setLanguage, toggleLanguage } = useLanguageStore();
  return { t, language, setLanguage, toggleLanguage };
};

export default useLanguageStore;
