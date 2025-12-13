# Changelog SIGEC

Tous les changements notables à ce projet seront documentés ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0.0-beta.1] - 2024-12-01

### ✨ Ajouts

#### Backend
- Architecture DDD complète (8 Domains)
- Authentication: Sanctum + JWT
- Multi-tenancy support avec isolation data
- RBAC: 8 rôles prédéfinis (Spatie Permission)
- Services métier:
  - StockService: CMP calculation, transfers
  - SaleService: Mode manuel/facturette
  - PurchaseService: Commandes fournisseurs
  - TransferService: Transferts stock
  - AccountingService: Écritures automatiques
- Export formats: Excel, PDF, Word
- Backup/Restore automation
- Queue workers: Jobs asynchrones
- Health check endpoints

#### Frontend
- React 18 + Vite build
- Responsive UI avec Tailwind CSS
- Zustand state management
- Offline POS via IndexedDB
- Forms: React Hook Form + Zod validation
- Real-time sync: WebSocket-ready
- Recharts dashboards
- Barcode scanning support
- Dark/Light theme

#### Infrastructure
- Docker Compose: 5 services (app, frontend, postgres, redis, pgadmin)
- GitHub Actions CI/CD
- Deployment scripts: Linux/Windows
- Environment configuration
- Health checks avec auto-retry

#### Documentation
- Installation guide
- Troubleshooting guide
- VPS deployment guide
- Security hardening
- Monitoring & maintenance
- Contribution guidelines

### 🐛 Corrections

### 🚀 Améliorations

### ⚠️ Breaking Changes

---

## [1.0.0-beta.0] - 2024-11-15

### ✨ Ajouts

- Initial beta release
- Basic project structure
- Initial dependencies

---

## Upcoming

### Planifié pour v1.1.0

#### Features
- [ ] Offline POS sync & reconciliation
- [ ] Advanced reporting engine
- [ ] SMS/Email notifications
- [ ] Stripe integration
- [ ] API rate limiting
- [ ] Two-factor authentication
- [ ] Custom reports builder
- [ ] Multi-currency support

#### Performance
- [ ] Database query optimization
- [ ] Redis caching layer
- [ ] Frontend code splitting
- [ ] Image optimization

#### Infrastructure
- [ ] Kubernetes manifests
- [ ] CloudFormation templates
- [ ] Auto-scaling policies
- [ ] CDN integration

### Planifié pour v2.0.0

- Mobile app (React Native)
- Real-time collaboration
- Advanced AI recommendations
- Multi-warehouse analytics
- Integration marketplace
- Customer portal
- Supplier portal

---

## Migration Guide

### De v0.x vers v1.0.0

#### Backend

```php
// ✅ NEW: Utiliser DDD services
$service = app(StockService::class);
$service->transfer($product, $from, $to, $qty);

// ❌ OLD: Appels directs modèles
Stock::where(...)->decrement('quantity');
```

#### Frontend

```jsx
// ✅ NEW: Utiliser Zustand stores
const user = useTenantStore(state => state.user);

// ❌ OLD: localStorage
const user = JSON.parse(localStorage.getItem('user'));
```

---

## Notes de Sécurité

### v1.0.0-beta.1
- ✅ Chiffrement AES-256 données sensibles
- ✅ Audit logging toutes modifications
- ✅ RGPD compliance data export/delete
- ⚠️ Rate limiting recommandé en production
- ⚠️ Vérifier certificats SSL avant prod

---

## Version Support

| Version | Status | Support |
|---------|--------|---------|
| 1.0.0-beta.1 | Active | - |
| 1.0.0-beta.0 | EOL | 2024-12-15 |

---

## Contribution

Voir [CONTRIBUTING.md](./CONTRIBUTING.md) pour contribution guidelines.

---

## License

MIT License - voir [LICENSE](./LICENSE)
