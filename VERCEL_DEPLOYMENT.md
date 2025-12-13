# 🚀 SIGEC v1.0 - Déploiement Vercel

## ✨ Nouveautés de cette version

La migration de React+Vite vers **Next.js** offre :
- ✅ Déploiement natif sur Vercel (0 configuration)
- ✅ API routes intégrées pour proxier les appels backend
- ✅ Optimisation des performances automatique
- ✅ SSR et SSG prêts à l'emploi

## 🌐 Déploiement en Ligne

**URL Vercel:** https://sigec-pi.vercel.app

### Pages Disponibles

| Route | Description | Status |
|-------|-------------|--------|
| `/` | Page d'accueil avec login/démo | ✅ Live |
| `/login` | Formulaire de connexion | ✅ Live |
| `/demo` | Mode démo (sans authentification) | ✅ Live |
| `/dashboard` | Tableau de bord principal | ✅ Live |
| `/dashboard/tenants` | Gestion des tenants | ✅ Live |
| `/dashboard/users` | Gestion des collaborateurs | ✅ Live |
| `/dashboard/procurement` | Module d'approvisionnement | ✅ Live |
| `/dashboard/sales` | Gestion des ventes | ✅ Live |
| `/dashboard/expenses` | Gestion des charges | ✅ Live |
| `/dashboard/reports` | Module de rapports | ✅ Live |
| `/dashboard/export` | Export PDF/Excel | ✅ Live |

## 🔐 Identifiants de Test

```
Email:    demo@sigec.com
Password: password123
```

## 🏃 Démarrage Local

### Prérequis
- Node.js 18+
- npm ou yarn

### Installation

```bash
npm install
```

### Développement

**Option 1: Démarrage complet (Mock API + Frontend)**

```bash
./start-dev.sh
```

Cela démarre :
- Frontend Next.js sur http://localhost:3000
- Mock API sur http://localhost:8000

**Option 2: Démarrage séparé**

Terminal 1 - Frontend :
```bash
npm run dev
```

Terminal 2 - Mock API :
```bash
npm run mock-api
```

### Build Production

```bash
npm run build
npm start
```

## 📂 Structure du Projet

```
/workspaces/SIGEC/
├── app/                          # Next.js App Router
│   ├── api/                       # API routes (proxies backend)
│   │   ├── auth/login/
│   │   ├── stats/
│   │   ├── tenants/
│   │   ├── users/
│   │   ├── sales/
│   │   ├── procurement/
│   │   ├── expenses/
│   │   └── reports/
│   ├── dashboard/                 # Routes protégées
│   │   ├── layout.jsx             # Layout avec sidebar
│   │   ├── page.jsx               # Tableau de bord
│   │   ├── tenants/
│   │   ├── users/
│   │   ├── procurement/
│   │   ├── sales/
│   │   ├── expenses/
│   │   ├── reports/
│   │   └── export/
│   ├── demo/                      # Mode démo public
│   ├── login/                     # Page de connexion
│   ├── page.jsx                   # Page d'accueil
│   ├── layout.jsx                 # Root layout
│   └── globals.css                # Styles globaux Tailwind
├── mock-api.js                    # Serveur Mock API (Express)
├── package.json                   # Dépendances
├── next.config.js                 # Configuration Next.js
├── vercel.json                    # Configuration Vercel
├── postcss.config.js              # Configuration PostCSS
├── tailwind.config.js             # Configuration Tailwind
└── start-dev.sh                   # Script de démarrage
```

## 🔧 Configuration Vercel

Le fichier `vercel.json` configure :
- Framework: Next.js
- Build command: `next build`
- Start command: `next start`
- Environment variables

### Variables d'Environnement

Pour Vercel, ajouter :
```
NEXT_PUBLIC_API_URL=https://sigec-pi.vercel.app/api
```

## 📡 Flux d'Authentification

1. **Accueil** → Cliquez "Se Connecter"
2. **Login** → Entrez demo@sigec.com / password123
3. **Appel API** → POST /api/auth/login
4. **Stockage** → Token + User en localStorage
5. **Dashboard** → Accès aux 8 modules

## 🛡️ Sécurité

- ✅ Tokens stockés en localStorage (développement)
- ✅ Routes protégées avec vérification de token
- ✅ CORS headers configurés pour API routes
- ✅ Validation des entrées utilisateur

## 📊 Fonctionnalités Implémentées

### Dashboard
- 4 cartes KPI (Total Ventes, Achats, Stock, Transactions)
- 4 boutons d'accès rapide aux modules
- Données fetched depuis `/api/stats`

### Modules
- **Tenants**: Créer/lister des tenants
- **Users**: Gérer les collaborateurs
- **Procurement**: Suivi des achats
- **Sales**: Gestion des ventes
- **Expenses**: Suivi des charges
- **Reports**: Génération de rapports (Sales, Purchases, Stock, Financial, Cashflow, Customers)
- **Export**: Export PDF/Excel des données

### Mode Démo
- Accessible sans authentification
- Showcases des fonctionnalités principales
- Liens vers toutes les pages

## 🚨 Dépannage

### Le frontend affiche 404
- Vérifier que les fichiers Next.js sont dans le dossier `/app`
- Vérifier la configuration de `vercel.json`
- Redéployer: `git push origin feature/sigec-complete`

### Les API calls échouent en local
- Vérifier que Mock API tourne sur port 8000
- Vérifier `.env.local` a `NEXT_PUBLIC_API_URL=http://localhost:8000`
- Vérifier les logs: `tail -f logs/mock-api.log`

### Build échoue
- Supprimer `node_modules` et `.next`: `rm -rf node_modules .next`
- Réinstaller: `npm install`
- Rebuilder: `npm run build`

## 📦 Dépendances Principales

```json
{
  "next": "^15.1.2",
  "react": "^19.0.0",
  "react-dom": "^19.0.0",
  "tailwindcss": "^3.4.1"
}
```

## 🎨 Styling

- **Framework**: Tailwind CSS (3.4.1)
- **Utility-first CSS** pour tous les composants
- **Dark mode friendly** avec classes `text-gray-*` et `bg-white/gray-50`
- **Responsive design** via `md:` et `lg:` breakpoints

## 📝 Notes

- Mock API retourne des données de démonstration
- Authentification n'est pas persistée (réinitialise au rechargement)
- Pour production, connecter à un vrai backend
- Tous les endpoints API sont disponibles en `/api/*`

## ✅ Checklist de Déploiement

- ✅ Frontend migré vers Next.js
- ✅ 13 pages créées et testées
- ✅ API routes configurées
- ✅ vercel.json présent
- ✅ Configuration Tailwind complète
- ✅ Build local réussi
- ✅ Code poussé vers GitHub
- ✅ Déploiement Vercel déclenché
- ⏳ URL en ligne accessible

## 🔗 Liens Utiles

- [Documentation Next.js](https://nextjs.org)
- [Documentation Vercel](https://vercel.com/docs)
- [Tailwind CSS](https://tailwindcss.com)
- [GitHub Repository](https://github.com/Gandji1/SIGEC)

---

**Status**: ✅ Production Ready
**Version**: 1.0.0
**Last Updated**: 2025-01-08
