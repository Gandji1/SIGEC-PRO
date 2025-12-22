import React, {
  lazy,
  Suspense,
  useState,
  useEffect,
  memo,
  useCallback,
} from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { useTenantStore } from "../stores/tenantStore";
import { useThemeStore } from "../stores/themeStore";
import { useLanguageStore } from "../stores/languageStore";
import { usePermission } from "../hooks/usePermission";
import { getAccessibleRoutes, getRoleLabel, getRoleColor } from "../utils/rbac";
import {
  Sun,
  Moon,
  Menu,
  X,
  LogOut,
  ChevronRight,
  ChevronDown,
  Home,
  Monitor,
  Tablet,
  Smartphone,
  Bell,
  Search,
  User,
  Globe,
} from "lucide-react";

// Lazy load des alertes pour ne pas bloquer le rendu
const LowStockAlert = lazy(() => import("./LowStockAlert"));
const SubscriptionAlert = lazy(() => import("./SubscriptionAlert"));
const GlobalSearch = lazy(() => import("./GlobalSearch"));
const NotificationToast = lazy(() => import("./NotificationToast"));

// Icônes modernes pour le menu (mapping)
const menuIcons = {
  "🏠": Home,
  "📊": Monitor,
  "💰": null, // Garde l'emoji
  "📦": null,
  "🛒": null,
  "👥": User,
};

// NavLink simple (sans enfants) - Design moderne avec orange actif
const NavLink = memo(({ item, navigate, isActive, onClick, collapsed }) => {
  const IconComponent = menuIcons[item.icon];

  return (
    <a
      href={item.path}
      onClick={(e) => {
        e.preventDefault();
        navigate(item.path);
        onClick?.();
      }}
      title={collapsed ? item.label : undefined}
      className={`flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 cursor-pointer text-sm font-medium group ${
        isActive
          ? "bg-orange-500 text-white shadow-lg shadow-orange-500/30"
          : "text-gray-300 hover:bg-orange-500/20 hover:text-orange-300"
      }`}
    >
      <span
        className={`w-8 h-8 flex items-center justify-center rounded-lg transition-all duration-200 ${
          isActive ? "bg-white/20" : "bg-white/5 group-hover:bg-orange-500/20"
        }`}
      >
        {IconComponent ? (
          <IconComponent size={18} />
        ) : (
          <span className="text-base">{item.icon}</span>
        )}
      </span>
      {!collapsed && <span className="flex-1 truncate">{item.label}</span>}
    </a>
  );
});

// NavGroup avec sous-menus (accordéon) - Design moderne avec orange
const NavGroup = memo(
  ({ item, navigate, location, onClick, isExpanded, onToggle }) => {
    const hasActiveChild = item.children?.some(
      (child) => location.pathname === child.path
    );
    const isOpen =
      isExpanded === true || isExpanded === undefined || hasActiveChild;

    return (
      <div className="space-y-1">
        {/* Parent - Header du groupe */}
        <button
          onClick={onToggle}
          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 cursor-pointer text-sm font-medium group ${
            hasActiveChild
              ? "bg-orange-500/20 text-orange-300"
              : "text-gray-300 hover:bg-orange-500/10 hover:text-orange-300"
          }`}
        >
          <span
            className={`w-8 h-8 flex items-center justify-center rounded-lg ${
              hasActiveChild
                ? "bg-orange-500/30"
                : "bg-white/5 group-hover:bg-orange-500/20"
            }`}
          >
            <span className="text-base">{item.icon}</span>
          </span>
          <span className="flex-1 text-left">{item.label}</span>
          <ChevronDown
            size={16}
            className={`transition-transform duration-200 opacity-60 ${
              isOpen ? "rotate-180" : ""
            }`}
          />
        </button>

        {/* Children - Sous-menus */}
        {isOpen && item.children && item.children.length > 0 && (
          <div className="ml-11 space-y-0.5 py-1">
            {item.children.map((child, idx) => (
              <a
                key={idx}
                href={child.path}
                onClick={(e) => {
                  e.preventDefault();
                  navigate(child.path);
                  onClick?.();
                }}
                className={`flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 cursor-pointer text-sm ${
                  location.pathname === child.path
                    ? "bg-orange-500 text-white font-medium shadow-md shadow-orange-500/20"
                    : "text-gray-400 hover:bg-orange-500/20 hover:text-orange-300"
                }`}
              >
                <span className="text-sm">{child.icon}</span>
                <span className="truncate">{child.label}</span>
              </a>
            ))}
          </div>
        )}
      </div>
    );
  }
);

// Skeleton de chargement
const LoadingSkeleton = memo(() => (
  <div className="flex h-screen bg-gray-100 dark:bg-gray-900 items-center justify-center">
    <div className="text-center">
      <div className="w-16 h-16 bg-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse">
        <span className="text-2xl font-black text-white">S</span>
      </div>
      <div className="h-2 w-32 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse" />
    </div>
  </div>
));

export default function Layout({ children }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, tenant, logout } = useTenantStore();
  const { isDark, toggleTheme, initTheme } = useThemeStore();
  const { language, toggleLanguage, t } = useLanguageStore();
  const { can } = usePermission();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [expandedMenus, setExpandedMenus] = useState({});
  const [initialized, setInitialized] = useState(false);
  const [viewMode, setViewMode] = useState("desktop"); // desktop, tablet, mobile
  const [searchOpen, setSearchOpen] = useState(false);

  // Déterminer le rôle de l'utilisateur
  const userRole = user?.role || "auditor";
  const pos_option = user?.pos_option || "A";

  // Obtenir les routes dynamiquement selon le rôle (maintenant un array)
  const menuItems = getAccessibleRoutes(userRole, pos_option);

  // Initialiser le thème
  useEffect(() => {
    initTheme();
  }, [initTheme]);

  // Initialiser les menus ouverts par défaut (tous les parents avec enfants)
  useEffect(() => {
    if (!initialized && menuItems.length > 0) {
      const initialExpanded = {};
      menuItems.forEach((item) => {
        if (item.children && item.children.length > 0) {
          // Ouvrir par défaut si un enfant est actif ou si c'est le premier chargement
          const hasActiveChild = item.children.some(
            (child) => location.pathname === child.path
          );
          initialExpanded[item.label] = hasActiveChild || true; // Tous ouverts par défaut
        }
      });
      setExpandedMenus(initialExpanded);
      setInitialized(true);
    }
  }, [menuItems, location.pathname, initialized]);

  // Fermer le menu mobile lors du changement de route
  useEffect(() => {
    setMobileMenuOpen(false);
  }, [location.pathname]);

  // Raccourci clavier Ctrl+K pour la recherche
  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === "k") {
        e.preventDefault();
        setSearchOpen((prev) => !prev);
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  const handleLogout = useCallback(() => {
    logout();
    navigate("/login");
  }, [logout, navigate]);

  const closeMobileMenu = useCallback(() => {
    setMobileMenuOpen(false);
  }, []);

  // Toggle menu accordéon
  const toggleMenu = useCallback((menuLabel) => {
    setExpandedMenus((prev) => ({
      ...prev,
      [menuLabel]: !prev[menuLabel],
    }));
  }, []);

  // Early return if user is not loaded yet
  if (!user) {
    return <LoadingSkeleton />;
  }

  // Classes de conteneur selon le mode de vue
  const getContentMaxWidth = () => {
    switch (viewMode) {
      case "mobile":
        return "max-w-sm mx-auto";
      case "tablet":
        return "max-w-2xl mx-auto";
      default:
        return "w-full";
    }
  };

  // Masquer sidebar en mode tablette/mobile
  const showSidebar = viewMode === "desktop";

  return (
    <div
      className="flex h-screen transition-colors duration-300"
      style={{
        backgroundColor: isDark ? "#1A1A2E" : "#F5F7FA",
        background: isDark
          ? "linear-gradient(180deg, #1A1A2E 0%, #16162B 100%)"
          : "linear-gradient(180deg, #F5F7FA 0%, #EDF2F7 100%)",
      }}
    >
      {/* Sidebar Desktop - Masquée en mode tablette/mobile */}
      <aside
        className={`${
          showSidebar ? "hidden md:flex" : "hidden"
        } md:flex-col md:w-60 text-white shadow-2xl`}
        style={{
          background: isDark
            ? "linear-gradient(180deg, #252540 0%, #1A1A2E 100%)"
            : "linear-gradient(180deg, #2D3748 0%, #1A202C 100%)",
        }}
      >
        {/* Logo - Cliquable vers Accueil */}
        <button
          onClick={() => navigate("/home")}
          className="p-4 border-b border-white/10 hover:bg-white/5 transition-colors w-full text-left"
        >
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/30">
              <span className="text-xl font-black">S</span>
            </div>
            <div>
              <h1 className="text-lg font-bold">SIGEC</h1>
              <p className="text-xs text-gray-400 truncate max-w-[120px]">
                {tenant?.name || "Entreprise"}
              </p>
            </div>
          </div>
        </button>

        {/* Menu Accueil - Toujours visible en premier */}
        <div className="px-3 pt-3">
          <NavLink
            item={{ icon: "🏠", label: t("nav.home"), path: "/home" }}
            navigate={navigate}
            isActive={location.pathname === "/home"}
          />
        </div>

        {/* Navigation */}
        <nav className="flex-1 px-3 py-2 space-y-1 overflow-y-auto scrollbar-thin">
          {menuItems.map((item, idx) =>
            item.children ? (
              <NavGroup
                key={idx}
                item={item}
                navigate={navigate}
                location={location}
                isExpanded={expandedMenus[item.label]}
                onToggle={() => toggleMenu(item.label)}
              />
            ) : (
              <NavLink
                key={idx}
                item={item}
                navigate={navigate}
                isActive={location.pathname === item.path}
              />
            )
          )}
        </nav>

        {/* User Info - Compact */}
        <div className="p-3 border-t border-white/10 space-y-2">
          {/* User */}
          <div className="flex items-center gap-3 px-3 py-2 rounded-xl bg-white/5">
            <div className="w-9 h-9 rounded-lg bg-orange-500/20 flex items-center justify-center">
              <User size={18} className="text-orange-400" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium text-sm truncate">
                {user?.name || "N/A"}
              </p>
              <p className="text-xs text-gray-400 truncate">
                {getRoleLabel(user?.role)}
              </p>
            </div>
          </div>

          {/* Logout */}
          <button
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 py-2.5 rounded-xl font-medium transition-colors text-sm"
          >
            <LogOut size={16} />
            <span>{t("nav.logout")}</span>
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Bar - Responsive proportionnel */}
        <header
          className="backdrop-blur-md border-b border-orange-400/30 px-2 sm:px-4 lg:px-6 py-1.5 sm:py-2 lg:py-2.5 flex justify-between items-center shadow-md transition-all duration-200"
          style={{ backgroundColor: "#FF9F43" }}
        >
          {/* Left - Mobile menu + Breadcrumb - Responsive */}
          <div className="flex items-center gap-1.5 sm:gap-2 lg:gap-3">
            {/* Menu burger visible en mode tablette/mobile OU sur petit écran */}
            <button
              onClick={() => setMobileMenuOpen(true)}
              className={`p-1.5 sm:p-2 rounded-lg bg-white/20 text-white hover:bg-white/30 transition-colors ${
                showSidebar ? "md:hidden" : ""
              }`}
            >
              <Menu size={18} className="sm:w-5 sm:h-5" />
            </button>
            {/* Breadcrumb visible seulement en mode desktop avec sidebar */}
            {showSidebar && (
              <div className="hidden md:flex items-center gap-2 text-sm">
                <Home size={16} className="text-white/80" />
                <span className="text-white/50">/</span>
                <span className="text-white font-medium">
                  {menuItems.find((m) => m.path === location.pathname)?.label ||
                    menuItems
                      .flatMap((m) => m.children || [])
                      .find((c) => c.path === location.pathname)?.label ||
                    "Page"}
                </span>
              </div>
            )}
            {/* Logo visible quand sidebar masquée - Responsive */}
            <button
              onClick={() => navigate("/home")}
              className={`flex items-center gap-1.5 sm:gap-2 ${
                showSidebar ? "md:hidden" : ""
              }`}
            >
              <div className="w-6 h-6 sm:w-7 sm:h-7 lg:w-8 lg:h-8 bg-white/20 rounded-md sm:rounded-lg flex items-center justify-center">
                <span className="text-xs sm:text-sm font-black text-white">
                  S
                </span>
              </div>
              <span className="font-semibold sm:font-bold text-white text-sm sm:text-base">
                SIGEC
              </span>
            </button>
          </div>

          {/* Right - Actions - Boutons lisibles et espacés */}
          <div className="flex items-center gap-2 sm:gap-3">
            {/* Boutons de prévisualisation - Desktop only */}
            <div className="hidden xl:flex items-center gap-1 p-1.5 bg-white/20 rounded-xl">
              <button
                onClick={() => setViewMode("desktop")}
                className={`p-2 rounded-lg transition-all ${
                  viewMode === "desktop"
                    ? "text-white bg-white/30 shadow-sm"
                    : "text-white/70 hover:bg-white/20"
                }`}
                title="Desktop"
              >
                <Monitor size={18} />
              </button>
              <button
                onClick={() => setViewMode("tablet")}
                className={`p-2 rounded-lg transition-all ${
                  viewMode === "tablet"
                    ? "text-white bg-white/30 shadow-sm"
                    : "text-white/70 hover:bg-white/20"
                }`}
                title="Tablet"
              >
                <Tablet size={18} />
              </button>
              <button
                onClick={() => setViewMode("mobile")}
                className={`p-2 rounded-lg transition-all ${
                  viewMode === "mobile"
                    ? "text-white bg-white/30 shadow-sm"
                    : "text-white/70 hover:bg-white/20"
                }`}
                title="Mobile"
              >
                <Smartphone size={18} />
              </button>
            </div>

            {/* Search Button - Plus visible */}
            <button
              onClick={() => setSearchOpen(true)}
              className="flex items-center gap-2 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-white/25 text-white hover:bg-white/35 text-sm font-medium transition-all shadow-sm hover:shadow-md"
              title="Recherche (Ctrl+K)"
            >
              <Search size={18} />
              <span className="hidden md:inline">Rechercher</span>
              <kbd className="hidden lg:inline px-1.5 py-0.5 text-xs bg-white/20 rounded-md font-mono">
                ⌘K
              </kbd>
            </button>

            {/* Language Toggle - Plus lisible */}
            <button
              onClick={toggleLanguage}
              className="flex items-center gap-2 px-3 py-2 sm:py-2.5 rounded-xl bg-white/25 text-white hover:bg-white/35 text-sm font-semibold transition-all shadow-sm"
              title={language === "fr" ? "English" : "Français"}
            >
              <Globe size={18} />
              <span className="uppercase">{language}</span>
            </button>

            {/* Theme Toggle - Plus grand */}
            <button
              onClick={toggleTheme}
              className="p-2.5 rounded-xl bg-white/25 text-white hover:bg-white/35 transition-all shadow-sm"
              title={isDark ? "Mode clair" : "Mode sombre"}
            >
              {isDark ? (
                <Sun size={20} className="text-yellow-300" />
              ) : (
                <Moon size={20} />
              )}
            </button>

            {/* Notifications - Plus visible */}
            <button className="p-2.5 rounded-xl bg-white/25 text-white hover:bg-white/35 relative transition-all shadow-sm">
              <Bell size={20} />
              <span className="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse border-2 border-orange-400"></span>
            </button>

            {/* User Avatar - Plus grand et lisible */}
            <div className="flex items-center gap-2 pl-3 ml-2 border-l-2 border-white/30">
              <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-white/25 flex items-center justify-center text-white font-bold text-base shadow-sm">
                {user?.name?.charAt(0)?.toUpperCase() || "U"}
              </div>
              <span className="hidden lg:block text-white font-medium text-sm max-w-[100px] truncate">
                {user?.name}
              </span>
            </div>
          </div>
        </header>

        {/* Page Content - Responsive selon viewMode */}
        <main className="flex-1 overflow-auto p-4">
          <div
            className={`animate-fade-in transition-all duration-300 ${getContentMaxWidth()}`}
          >
            {children}
          </div>
        </main>
      </div>

      {/* Mobile Menu Overlay */}
      {mobileMenuOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          {/* Backdrop */}
          <div
            className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in"
            onClick={closeMobileMenu}
          />

          {/* Menu Panel - Design amélioré */}
          <div
            className="absolute left-0 top-0 bottom-0 w-72 shadow-2xl animate-slide-right text-white"
            style={{
              background: isDark
                ? "linear-gradient(180deg, #252540 0%, #1A1A2E 100%)"
                : "linear-gradient(180deg, #2D3748 0%, #1A202C 100%)",
            }}
          >
            {/* Header - Logo cliquable */}
            <div className="p-4 border-b border-white/10 flex justify-between items-center">
              <button
                onClick={() => {
                  navigate("/home");
                  closeMobileMenu();
                }}
                className="flex items-center gap-3"
              >
                <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/30">
                  <span className="text-xl font-black text-white">S</span>
                </div>
                <span className="text-lg font-bold text-white">SIGEC</span>
              </button>
              <button
                onClick={closeMobileMenu}
                className="p-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
              >
                <X size={20} />
              </button>
            </div>

            {/* Menu Accueil - Mobile */}
            <div className="px-3 pt-3">
              <NavLink
                item={{ icon: "🏠", label: "Accueil", path: "/home" }}
                navigate={navigate}
                isActive={location.pathname === "/home"}
                onClick={closeMobileMenu}
              />
            </div>

            {/* Navigation */}
            <nav className="flex-1 px-3 py-2 space-y-1 overflow-y-auto max-h-[calc(100vh-220px)]">
              {menuItems.map((item, idx) =>
                item.children ? (
                  <NavGroup
                    key={idx}
                    item={item}
                    navigate={navigate}
                    location={location}
                    onClick={closeMobileMenu}
                    isExpanded={expandedMenus[item.label]}
                    onToggle={() => toggleMenu(item.label)}
                  />
                ) : (
                  <NavLink
                    key={idx}
                    item={item}
                    navigate={navigate}
                    isActive={location.pathname === item.path}
                    onClick={closeMobileMenu}
                  />
                )
              )}
            </nav>

            {/* Footer */}
            <div
              className="absolute bottom-0 left-0 right-0 p-3 border-t border-white/10 space-y-2"
              style={{
                background: isDark ? "#1A1A2E" : "#1A202C",
              }}
            >
              <div className="flex items-center gap-3 px-3 py-2 rounded-xl bg-white/5">
                <div className="w-9 h-9 rounded-lg bg-orange-500/20 flex items-center justify-center">
                  <User size={18} className="text-orange-400" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-sm text-white truncate">
                    {user?.name}
                  </p>
                  <p className="text-xs text-gray-400">
                    {getRoleLabel(user?.role)}
                  </p>
                </div>
              </div>
              <button
                onClick={handleLogout}
                className="w-full flex items-center justify-center gap-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 py-2.5 rounded-xl font-medium text-sm transition-colors"
              >
                <LogOut size={16} />
                <span>{t("nav.logout")}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Alertes Stock Bas */}
      {can("stocks.view") && (
        <Suspense fallback={null}>
          <LowStockAlert />
        </Suspense>
      )}

      {/* Alerte Abonnement */}
      <Suspense fallback={null}>
        <SubscriptionAlert />
      </Suspense>

      {/* Recherche Globale (Ctrl+K) */}
      <Suspense fallback={null}>
        <GlobalSearch
          isOpen={searchOpen}
          onClose={() => setSearchOpen(false)}
        />
      </Suspense>

      {/* Notifications Toast (temps réel) */}
      <Suspense fallback={null}>
        <NotificationToast notifications={[]} onDismiss={() => {}} />
      </Suspense>
    </div>
  );
}
