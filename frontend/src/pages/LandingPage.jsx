import { memo, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useThemeStore } from '../stores/themeStore';
import { useLanguageStore } from '../stores/languageStore';
import { Sun, Moon, LogIn, UserPlus, BarChart3, Shield, Zap, Globe } from 'lucide-react';

// Logo SIGEC optimisé avec orange
const SigecLogo = memo(() => (
  <div className="relative">
    <div className="w-24 h-24 md:w-32 md:h-32 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-orange-500/30 animate-scale-in">
      <span className="text-4xl md:text-5xl font-black text-white tracking-tight">S</span>
    </div>
    <div className="absolute -bottom-2 -right-2 w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center shadow-lg">
      <Zap size={16} className="text-white" />
    </div>
  </div>
));

// Feature card avec orange
const FeatureCard = memo(({ icon: Icon, title, description, delay }) => (
  <div 
    className="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in"
    style={{ animationDelay: `${delay}ms` }}
  >
    <div className="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center mb-4">
      <Icon className="text-orange-600 dark:text-orange-400" size={24} />
    </div>
    <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">{title}</h3>
    <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{description}</p>
  </div>
));

export default function LandingPage() {
  const navigate = useNavigate();
  const { token } = useTenantStore();
  const { isDark, toggleTheme, initTheme } = useThemeStore();
  const { language, toggleLanguage, t } = useLanguageStore();

  // Initialiser le thème au chargement
  useEffect(() => {
    initTheme();
  }, [initTheme]);

  // Rediriger si déjà connecté
  useEffect(() => {
    if (token) {
      navigate('/dashboard');
    }
  }, [token, navigate]);

  const features = [
    {
      icon: BarChart3,
      title: 'Gestion Complète',
      description: 'Gérez vos ventes, stocks, achats et comptabilité en un seul endroit.',
    },
    {
      icon: Shield,
      title: 'Sécurisé',
      description: 'Vos données sont protégées avec les dernières technologies de sécurité.',
    },
    {
      icon: Globe,
      title: 'Multi-Tenant',
      description: 'Solution adaptée aux entreprises avec plusieurs points de vente.',
    },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 transition-colors duration-300">
      {/* Background pattern */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-80 h-80 bg-brand-400/20 dark:bg-brand-600/10 rounded-full blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-400/20 dark:bg-purple-600/10 rounded-full blur-3xl" />
      </div>

      {/* Header */}
      <header className="relative z-10">
        <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex justify-between items-center">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center">
                <span className="text-xl font-black text-white">S</span>
              </div>
              <span className="text-xl font-bold text-gray-900 dark:text-white">SIGEC</span>
            </div>

            {/* Actions header */}
            <div className="flex items-center gap-2">
              {/* Language toggle */}
              <button
                onClick={toggleLanguage}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-lg border border-gray-200 dark:border-gray-700 hover:scale-105 transition-transform text-sm font-medium text-gray-700 dark:text-gray-300"
              >
                <Globe size={16} />
                <span className="uppercase">{language}</span>
              </button>
              
              {/* Theme toggle */}
              <button
                onClick={toggleTheme}
                className="p-3 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-lg border border-gray-200 dark:border-gray-700 hover:scale-105 transition-transform"
                aria-label="Toggle theme"
              >
                {isDark ? (
                  <Sun className="text-yellow-500" size={20} />
                ) : (
                  <Moon className="text-gray-700" size={20} />
                )}
              </button>
            </div>
          </div>
        </nav>
      </header>

      {/* Hero Section */}
      <main className="relative z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24">
          <div className="text-center">
            {/* Logo */}
            <div className="flex justify-center mb-8 animate-fade-in">
              <SigecLogo />
            </div>

            {/* Title */}
            <h1 className="text-4xl sm:text-5xl md:text-6xl font-black text-gray-900 dark:text-white mb-6 animate-fade-in" style={{ animationDelay: '100ms' }}>
              <span className="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">
                SIGEC
              </span>
            </h1>

            <p className="text-xl sm:text-2xl text-gray-600 dark:text-gray-300 mb-4 animate-fade-in" style={{ animationDelay: '200ms' }}>
              {language === 'fr' ? 'Système Intégré de Gestion Commerciale' : 'Integrated Business Management System'}
            </p>

            <p className="text-gray-500 dark:text-gray-400 max-w-2xl mx-auto mb-12 animate-fade-in" style={{ animationDelay: '300ms' }}>
              {language === 'fr' 
                ? 'La solution complète pour gérer votre entreprise : ventes, stocks, comptabilité, point de vente et bien plus encore.'
                : 'The complete solution to manage your business: sales, inventory, accounting, point of sale and much more.'}
            </p>

            {/* CTA Buttons */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in" style={{ animationDelay: '400ms' }}>
              <button
                onClick={() => navigate('/login')}
                className="group flex items-center justify-center gap-3 px-8 py-4 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl font-bold text-lg shadow-xl shadow-orange-500/30 hover:shadow-2xl hover:shadow-orange-500/40 transition-all duration-300 hover:-translate-y-1"
              >
                <LogIn size={22} className="group-hover:scale-110 transition-transform" />
                {t('auth.login')}
              </button>

              <button
                onClick={() => navigate('/onboarding')}
                className="group flex items-center justify-center gap-3 px-8 py-4 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white rounded-2xl font-bold text-lg shadow-xl border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition-all duration-300 hover:-translate-y-1"
              >
                <UserPlus size={22} className="group-hover:scale-110 transition-transform" />
                {t('auth.register')}
              </button>
            </div>
          </div>

          {/* Features */}
          <div className="mt-24 grid grid-cols-1 md:grid-cols-3 gap-6">
            {features.map((feature, idx) => (
              <FeatureCard
                key={idx}
                icon={feature.icon}
                title={feature.title}
                description={feature.description}
                delay={500 + idx * 100}
              />
            ))}
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="relative z-10 border-t border-gray-200 dark:border-gray-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <p className="text-gray-500 dark:text-gray-400 text-sm">
              © {new Date().getFullYear()} SIGEC. Tous droits réservés.
            </p>
            <div className="flex items-center gap-6">
              <a href="#" className="text-gray-500 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 text-sm transition-colors">
                Conditions d'utilisation
              </a>
              <a href="#" className="text-gray-500 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 text-sm transition-colors">
                Confidentialité
              </a>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
