# 🏪 SIGEC - Système Intégré de Gestion Efficace et de la Comptabilité

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-Beta-yellow)

SIGEC est une **plateforme SaaS multi-locataire** complète pour la gestion de points de vente (POS), d'inventaire et de comptabilité. Conçue pour les restaurants, boutiques de détail et petites entreprises.

## 🎯 Fonctionnalités Principales

### 📱 Point de Vente (POS)
- **Mode Manuel**: Saisie rapide des articles et paiements
- **Mode Facturette**: Génération automatique de documents
- **Hors Ligne**: Synchronisation bidirectionnelle des ventes
- **Multi-paiement**: Espèces, cartes, chèques, virements

### 📦 Gestion d'Inventaire
- **Stocks en Temps Réel**: CMP (Coût Moyen Pondéré) automatique
- **Multi-entrepôt**: Transferts entre magasins
- **Codes-barres**: Scan rapide articles
- **Alertes Rupture**: Stock minimum configurable

### 🛒 Achats & Ventes
- **Commandes Fournisseurs**: Suivi état commande
- **Bons de Réception**: Validation entrées stock
- **Devis Clients**: Conversion en commandes
- **Historique Complet**: Traçabilité totale

### 💰 Comptabilité
- **Journaux**: Ventes, achats, caisse, banque
- **Écritures Automatiques**: Via services comptables
- **Balance**: Vérification débit/crédit
- **Export**: Excel, PDF, fichiers XML pour expert-comptable

### 👥 Contrôle d'Accès
- **8 Rôles Prédéfinis**: Admin, Manager, Vendeur, etc.
- **Permissions Granulaires**: Par fonctionnalité
- **Audit Trail**: Historique modifications utilisateurs

### 📊 Tableaux de Bord
- **Ventes**: CA, ticket moyen, produits populaires
- **Stocks**: Valeurs, rotations, alertes
- **Trésorerie**: Encaissements, paiements, soldes

## 🚀 Stack Technologique

| Composant | Technology | Version |
|-----------|-----------|---------|
| **Backend** | Laravel | 11.x |
| **Frontend** | React + Vite | 18.x |
| **Database** | PostgreSQL | 16 |
| **Cache** | Redis | 7 |
| **PHP** | PHP | 8.2+ |
| **Node.js** | Node | 20+ |
| **Docker** | Docker | Latest |

## 📋 Architecture DDD

```
Domains/
├── Auth/              # Authentification & Utilisateurs
├── Tenants/           # Multi-tenancy
├── Products/          # Produits & Catégories
├── Stocks/            # Inventaire & CMP
├── Sales/             # Ventes & POS
├── Purchases/         # Achats & Fournisseurs
├── Transfers/         # Transferts Stock
├── Accounting/        # Comptabilité
└── Billing/           # Facturation
```

## ⚡ Démarrage Rapide

### Prérequis
- Docker & Docker Compose
- Git
- Ports: 8000, 5173, 5432, 6379, 5050

### Installation (5 min)

```bash
# 1. Cloner
git clone https://github.com/gandji1/SIGEC.git
cd SIGEC

# 2. Démarrer
docker-compose up -d

# 3. Accéder
# Frontend: http://localhost:5173
# API: http://localhost:8000
# pgAdmin: http://localhost:5050

# 4. Login
# Email: admin@sigec.local
# Password: password
```

## 📚 Documentation

| Guide | Description |
|-------|-------------|
| [INSTALLATION.md](./docs/INSTALLATION.md) | Installation complète |
| [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md) | Résolution problèmes |
| [deployment-vps.md](./docs/deployment-vps.md) | Production VPS |
| [security.md](./docs/security.md) | Sécurité |
| [monitoring-maintenance.md](./docs/monitoring-maintenance.md) | Monitoring |

## 🔐 Sécurité

- ✅ Authentification Sanctum + JWT
- ✅ Chiffrement AES-256
- ✅ RBAC Spatie Permission
- ✅ Multi-tenancy isolation
- ✅ HTTPS/SSL production
- ✅ RGPD compliance
- ✅ Audit logging

## 🧪 Tests

```bash
# Backend
docker-compose exec app php artisan test

# Frontend
docker-compose exec frontend npm test
```

## 💬 Support

- 📖 Documentation: [docs/](./docs/)
- 🐛 Issues: [GitHub](https://github.com/gandji1/SIGEC/issues)
- 📧 Email: support@sigec.local

## 📄 License

MIT License - voir [LICENSE](./LICENSE)

---

**Version**: 1.0.0-beta.1  
**Status**: 🟡 Beta  
**Dernière mise à jour**: Décembre 2024

⭐ Aimez le projet? Donnez-nous une star!
