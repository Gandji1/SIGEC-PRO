import React, { useState, useEffect, memo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useThemeStore } from '../stores/themeStore';
import { getRoleLabel, getAccessibleRoutes } from '../utils/rbac';
import apiClient from '../services/apiClient';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';
import { 
  Building2, TrendingUp, Users, ShoppingCart, 
  ArrowRight, Sparkles, BarChart3, Package,
  CreditCard, Clock, ChefHat, Wallet,
  Sun, Moon, LogOut
} from 'lucide-react';
import sigecLogo from '../assets/SIGEC.jpg';

// Carte de statistique animée (compacte pour grille 2x2)
const StatCard = memo(({ icon: Icon, label, value, trend, color, delay }) => (
  <div 
    className={`relative overflow-hidden bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 animate-fade-in`}
    style={{ animationDelay: `${delay}ms` }}
  >
    <div className={`absolute top-0 right-0 w-16 h-16 ${color} opacity-10 rounded-full -translate-y-1/2 translate-x-1/2`} />
    <div className="relative">
      <div className="flex items-center gap-2 mb-1">
        <div className={`w-7 h-7 ${color} bg-opacity-20 rounded-md flex items-center justify-center flex-shrink-0`}>
          <Icon className={`${color.replace('bg-', 'text-')}`} size={14} />
        </div>
        <p className="text-[10px] text-gray-500 dark:text-gray-400 leading-tight">{label}</p>
      </div>
      <p className="text-base font-bold text-gray-900 dark:text-white truncate">{value}</p>
      {trend && (
        <div className="flex items-center gap-1 mt-0.5">
          <TrendingUp size={10} className="text-green-500" />
          <span className="text-[10px] text-green-500 font-medium">{trend}</span>
        </div>
      )}
    </div>
  </div>
));

// Bouton d'action rapide
const QuickActionButton = memo(({ icon: Icon, label, description, onClick, color, delay }) => (
  <button
    onClick={onClick}
    className={`group relative overflow-hidden bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 text-left w-full animate-fade-in`}
    style={{ animationDelay: `${delay}ms` }}
  >
    <div className={`absolute inset-0 ${color} opacity-0 group-hover:opacity-5 transition-opacity duration-300`} />
    <div className="flex items-start gap-4">
      <div className={`w-12 h-12 ${color} bg-opacity-20 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-300`}>
        <Icon className={`${color.replace('bg-', 'text-')}`} size={22} />
      </div>
      <div className="flex-1 min-w-0">
        <h3 className="font-bold text-gray-900 dark:text-white mb-1 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
          {label}
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{description}</p>
      </div>
      <ArrowRight size={20} className="text-gray-400 group-hover:text-brand-600 group-hover:translate-x-1 transition-all flex-shrink-0 mt-1" />
    </div>
  </button>
));

export default function HomePage() {
  const navigate = useNavigate();
  const { user, tenant, logout } = useTenantStore();
  const { isDark, toggleTheme } = useThemeStore();
  const { get: getCache, set: setCache } = useCacheStore();
  const fetchedRef = useRef(false);
  
  // Initialiser avec le cache pour affichage instantané
  const cachedStats = getCache(CACHE_KEYS.DASHBOARD_STATS);
  const [stats, setStats] = useState(cachedStats || {
    entreprises: 0,
    chiffreAffaires: 0,
    commandesJour: 0,
    utilisateursActifs: 0,
  });
  const [loading, setLoading] = useState(!cachedStats);
  const [currentTime, setCurrentTime] = useState(new Date());

  // Mise à jour de l'heure
  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  // Charger les statistiques avec cache
  useEffect(() => {
    if (fetchedRef.current) return;
    fetchedRef.current = true;
    
    const fetchStats = async () => {
      // Si on a déjà des stats en cache, ne pas afficher le loading
      if (cachedStats) {
        setLoading(false);
      }
      
      try {
        const [dashboardRes] = await Promise.all([
          apiClient.get('/dashboard/stats').catch(() => ({ data: {} })),
        ]);
        
        const data = dashboardRes.data?.data || dashboardRes.data || {};
        
        const newStats = {
          entreprises: data.tenants_count || data.entreprises || 1,
          chiffreAffaires: data.total_revenue || data.chiffre_affaires || data.today_sales || 0,
          commandesJour: data.orders_today || data.commandes || data.sales_count || 0,
          utilisateursActifs: data.active_users || data.users_count || 1,
        };
        
        setStats(newStats);
        setCache(CACHE_KEYS.DASHBOARD_STATS, newStats);
      } catch (error) {
        console.error('Stats error:', error);
      } finally {
        setLoading(false);
      }
    };
    fetchStats();
  }, [cachedStats, setCache]);

  const userRole = user?.role || 'pos_server';
  const menuItems = getAccessibleRoutes(userRole);

  // Formater le montant
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('fr-FR', {
      style: 'decimal',
      maximumFractionDigits: 0,
    }).format(amount || 0) + ' FCFA';
  };

  // Obtenir le message de bienvenue selon l'heure
  const getGreeting = () => {
    const hour = currentTime.getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  };

  // Actions rapides selon le rôle
  const getQuickActions = () => {
    const baseActions = [
      { icon: BarChart3, label: 'Mon Dashboard', description: 'Voir mes statistiques et activités', path: '/dashboard', color: 'bg-blue-500' },
    ];

    const roleActions = {
      super_admin: [
        { icon: Building2, label: 'Gérer les Tenants', description: 'Administrer les entreprises', path: '/tenant-management', color: 'bg-purple-500' },
        { icon: CreditCard, label: 'Abonnements', description: 'Gérer les plans et paiements', path: '/subscriptions', color: 'bg-green-500' },
        { icon: BarChart3, label: 'Monitoring', description: 'Surveiller la plateforme', path: '/monitoring', color: 'bg-orange-500' },
      ],
      owner: [
        { icon: Users, label: 'Collaborateurs', description: 'Gérer utilisateurs et équipes', path: '/users-management', color: 'bg-purple-500' },
        { icon: Package, label: 'Approvisionnement', description: 'Produits et inventaire', path: '/products', color: 'bg-green-500' },
        { icon: Wallet, label: 'Comptabilité', description: 'Caisse, charges et rapports', path: '/cash-register', color: 'bg-orange-500' },
      ],
      admin: [
        { icon: Users, label: 'Collaborateurs', description: 'Gérer utilisateurs et équipes', path: '/users-management', color: 'bg-purple-500' },
        { icon: Package, label: 'Approvisionnement', description: 'Produits et inventaire', path: '/products', color: 'bg-green-500' },
        { icon: Wallet, label: 'Comptabilité', description: 'Caisse, charges et rapports', path: '/cash-register', color: 'bg-orange-500' },
      ],
      manager: [
        { icon: ChefHat, label: 'Commandes POS', description: 'Approuver et servir les commandes', path: '/pos/manager-orders', color: 'bg-purple-500' },
        { icon: Package, label: 'Inventaire', description: 'Gérer les stocks', path: '/inventory-enriched', color: 'bg-green-500' },
        { icon: Wallet, label: 'Gestion Caisse', description: 'Suivre les encaissements', path: '/cash-register', color: 'bg-orange-500' },
      ],
      pos_server: [
        { icon: ShoppingCart, label: 'Point de Vente', description: 'Créer une nouvelle commande', path: '/pos', color: 'bg-purple-500' },
        { icon: Clock, label: 'Mes Commandes', description: 'Suivre mes commandes en cours', path: '/pos/my-orders', color: 'bg-green-500' },
        { icon: TrendingUp, label: 'Mes Ventes', description: 'Voir mon chiffre d\'affaires', path: '/sales', color: 'bg-orange-500' },
      ],
      caissier: [
        { icon: CreditCard, label: 'Encaissement', description: 'Traiter les paiements', path: '/pos', color: 'bg-purple-500' },
        { icon: Wallet, label: 'Ma Caisse', description: 'Gérer ma session de caisse', path: '/cash-register', color: 'bg-green-500' },
      ],
      accountant: [
        { icon: BarChart3, label: 'Journaux', description: 'Consulter les écritures', path: '/journaux', color: 'bg-purple-500' },
        { icon: TrendingUp, label: 'Rapports', description: 'Générer des rapports', path: '/reports', color: 'bg-green-500' },
        { icon: Wallet, label: 'Charges', description: 'Suivre les dépenses', path: '/expense-tracking', color: 'bg-orange-500' },
      ],
      magasinier_gros: [
        { icon: Package, label: 'Réceptions', description: 'Réceptionner les commandes', path: '/approvisionnement', color: 'bg-purple-500' },
        { icon: BarChart3, label: 'Inventaire', description: 'Faire l\'inventaire', path: '/inventory-enriched', color: 'bg-green-500' },
      ],
      magasinier_detail: [
        { icon: Package, label: 'Stock', description: 'Gérer le stock détail', path: '/approvisionnement', color: 'bg-purple-500' },
        { icon: BarChart3, label: 'Inventaire', description: 'Faire l\'inventaire', path: '/inventory-enriched', color: 'bg-green-500' },
      ],
    };

    return [...baseActions, ...(roleActions[userRole] || [])];
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-indigo-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 transition-colors duration-300">
      {/* Background decorations */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-96 h-96 bg-brand-400/20 dark:bg-brand-600/10 rounded-full blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-96 h-96 bg-purple-400/20 dark:bg-purple-600/10 rounded-full blur-3xl" />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gradient-to-r from-brand-400/5 to-purple-400/5 rounded-full blur-3xl" />
      </div>

      {/* Header */}
      <header className="relative z-20 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-700/50 sticky top-0">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex justify-between items-center">
            {/* Logo et titre */}
            <div className="flex items-center gap-4">
              <div className="relative">
                <img 
                  src={sigecLogo} 
                  alt="SIGEC" 
                  className="w-14 h-14 rounded-2xl object-cover shadow-lg ring-2 ring-white dark:ring-gray-700"
                />
                <div className="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white dark:border-gray-900 flex items-center justify-center">
                  <Sparkles size={10} className="text-white" />
                </div>
              </div>
              <div>
                <h1 className="text-2xl font-black bg-gradient-to-r from-brand-600 to-purple-600 bg-clip-text text-transparent">
                  SIGEC
                </h1>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {tenant?.name || 'Système de Gestion'}
                </p>
              </div>
            </div>

            {/* Heure et actions */}
            <div className="flex items-center gap-4">
              <div className="hidden sm:block text-right">
                <p className="text-sm font-medium text-gray-900 dark:text-white">
                  {currentTime.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}
                </p>
                <p className="text-2xl font-bold text-brand-600 dark:text-brand-400 tabular-nums">
                  {currentTime.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                </p>
              </div>
              
              <button
                onClick={toggleTheme}
                className="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
              >
                {isDark ? <Sun size={20} className="text-yellow-500" /> : <Moon size={20} className="text-gray-600" />}
              </button>

              <button
                onClick={handleLogout}
                className="p-3 rounded-xl bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors"
              >
                <LogOut size={20} />
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        {/* Hero Section - Image + Stats côte à côte */}
        <div className="mb-8 animate-fade-in">
          <div className="flex flex-col lg:flex-row gap-6 items-stretch">
            
            {/* Image SIGEC - 20cm x 11cm (environ 756px x 416px) - réduit de 4cm */}
            <div className="flex-shrink-0">
              <div className="relative overflow-hidden rounded-3xl shadow-2xl" style={{ width: '756px', maxWidth: '100%', height: '416px' }}>
                <img 
                  src={sigecLogo} 
                  alt="SIGEC - Système Intégré de Gestion Commerciale" 
                  className="w-full h-full object-cover"
                />
                {/* Overlay gradient */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                
                {/* Texte sur l'image */}
                <div className="absolute bottom-0 left-0 right-0 p-5 text-white">
                  <h1 className="text-2xl md:text-3xl font-black mb-2">
                    {getGreeting()}, {user?.name || 'Utilisateur'} 👋
                  </h1>
                  <div className="flex items-center gap-3">
                    <span className={`px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm ${
                      userRole === 'super_admin' ? 'bg-red-500/80 text-white' :
                      userRole === 'owner' || userRole === 'admin' ? 'bg-purple-500/80 text-white' :
                      userRole === 'manager' ? 'bg-orange-500/80 text-white' :
                      'bg-blue-500/80 text-white'
                    }`}>
                      {getRoleLabel(userRole)}
                    </span>
                    <span className="text-white/80 text-sm">
                      {tenant?.name || 'SIGEC'}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Stats à droite de l'image - 2 par ligne */}
            <div className="flex-1 flex flex-col gap-3 min-w-[320px] max-w-[400px]">
              {/* Titre stats */}
              <div className="bg-gradient-to-r from-brand-600 to-purple-600 rounded-xl p-3 text-white">
                <h2 className="text-base font-bold flex items-center gap-2">
                  <BarChart3 size={18} />
                  Tableau de bord
                </h2>
                <p className="text-white/80 text-xs">Statistiques en temps réel</p>
              </div>

              {/* Stats cards - grille 2x2 */}
              <div className="grid grid-cols-2 gap-3">
                <StatCard
                  icon={Building2}
                  label="Entreprises actives"
                  value={stats.entreprises}
                  color="bg-blue-500"
                  delay={100}
                />
                <StatCard
                  icon={TrendingUp}
                  label="Chiffre d'affaires"
                  value={formatCurrency(stats.chiffreAffaires)}
                  trend="+12% ce mois"
                  color="bg-green-500"
                  delay={200}
                />
                <StatCard
                  icon={ShoppingCart}
                  label="Commandes du jour"
                  value={stats.commandesJour}
                  color="bg-purple-500"
                  delay={300}
                />
                <StatCard
                  icon={Users}
                  label="Utilisateurs actifs"
                  value={stats.utilisateursActifs}
                  color="bg-orange-500"
                  delay={400}
                />
              </div>

              {/* Bouton accès espace */}
              <button
                onClick={() => navigate('/dashboard')}
                className="group flex items-center justify-center gap-2 px-5 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-lg shadow-brand-500/30 hover:shadow-xl transition-all duration-300"
              >
                Accéder à mon espace
                <ArrowRight size={18} className="group-hover:translate-x-1 transition-transform" />
              </button>
            </div>
          </div>
        </div>

        {/* Quick Actions - Juste en dessous de l'image */}
        <div className="mb-8">
          <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <Sparkles className="text-brand-500" size={24} />
            Actions rapides
          </h3>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {getQuickActions().map((action, idx) => (
              <QuickActionButton
                key={idx}
                icon={action.icon}
                label={action.label}
                description={action.description}
                onClick={() => navigate(action.path)}
                color={action.color}
                delay={500 + idx * 100}
              />
            ))}
          </div>
        </div>

        {/* Info Banner */}
        <div className="relative overflow-hidden bg-gradient-to-r from-brand-600 via-brand-700 to-purple-700 rounded-2xl p-6 shadow-xl animate-fade-in" style={{ animationDelay: '800ms' }}>
          <div className="absolute inset-0 overflow-hidden">
            <div className="absolute -top-20 -right-20 w-64 h-64 bg-white/10 rounded-full blur-2xl" />
          </div>
          
          <div className="relative flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="text-center md:text-left">
              <h3 className="text-xl font-bold text-white mb-1">
                Bienvenue sur SIGEC
              </h3>
              <p className="text-white/80 text-sm">
                Votre système intégré de gestion commerciale - Ventes, stocks, comptabilité et équipes.
              </p>
            </div>
            
            <div className="flex gap-3">
              <button
                onClick={() => navigate('/dashboard')}
                className="px-5 py-2.5 bg-white text-brand-700 rounded-lg font-bold hover:bg-gray-100 transition-colors shadow-lg text-sm"
              >
                Commencer
              </button>
              <button
                onClick={() => window.open('https://docs.sigec.com', '_blank')}
                className="px-5 py-2.5 bg-white/20 text-white rounded-lg font-bold hover:bg-white/30 transition-colors backdrop-blur-sm text-sm"
              >
                Documentation
              </button>
            </div>
          </div>
        </div>
      </main>

      {/* Footer avec liens utiles */}
      <footer className="relative z-10 mt-12 bg-gray-900 dark:bg-black text-white">
        {/* Liens utiles */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            {/* Lien 1 - Connexion */}
            <a 
              href="/login" 
              onClick={(e) => { e.preventDefault(); navigate('/login'); }}
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">🔐</span>
              </div>
              <span className="text-sm font-medium">Connexion</span>
            </a>

            {/* Lien 2 - Inscription */}
            <a 
              href="/onboarding" 
              onClick={(e) => { e.preventDefault(); navigate('/onboarding'); }}
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">📝</span>
              </div>
              <span className="text-sm font-medium">Inscription</span>
            </a>

            {/* Lien 3 - Dashboard */}
            <a 
              href="/dashboard" 
              onClick={(e) => { e.preventDefault(); navigate('/dashboard'); }}
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">📊</span>
              </div>
              <span className="text-sm font-medium">Dashboard</span>
            </a>

            {/* Lien 4 - Support */}
            <a 
              href="mailto:support@sigec.com" 
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">💬</span>
              </div>
              <span className="text-sm font-medium">Support</span>
            </a>

            {/* Lien 5 - Documentation */}
            <a 
              href="https://docs.sigec.com" 
              target="_blank" 
              rel="noopener noreferrer"
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">📚</span>
              </div>
              <span className="text-sm font-medium">Documentation</span>
            </a>

            {/* Lien 6 - À propos */}
            <a 
              href="#about" 
              className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors group"
            >
              <div className="w-10 h-10 bg-pink-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                <span className="text-lg">ℹ️</span>
              </div>
              <span className="text-sm font-medium">À propos</span>
            </a>
          </div>
        </div>

        {/* Copyright et status */}
        <div className="border-t border-white/10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
              <p className="text-sm text-gray-400">
                © {new Date().getFullYear()} SIGEC - Système Intégré de Gestion Commerciale
              </p>
              <div className="flex items-center gap-6">
                <a href="#privacy" className="text-xs text-gray-400 hover:text-white transition-colors">
                  Confidentialité
                </a>
                <a href="#terms" className="text-xs text-gray-400 hover:text-white transition-colors">
                  Conditions
                </a>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-gray-500">v2.0</span>
                  <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                  <span className="text-xs text-green-400 font-medium">En ligne</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
