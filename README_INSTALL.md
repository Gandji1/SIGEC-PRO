# 🚀 SIGEC - Système Intégré de Gestion Efficace et de Comptabilité

**Version:** 0.2-stock-flows  
**Status:** MVP Core (40-55% complet)  
**Tech Stack:** Laravel 11 + React 18 + PostgreSQL

---

## 📋 Caractéristiques Actuelles (v0.2)

✅ **Multi-tenant SaaS** - Isolation complète par tenant_id  
✅ **Mode POS A/B** - Options Gros/Détail/POS configurables  
✅ **Achat avec CMP** - Coût Moyen Pondéré automatique  
✅ **Transfers Multi-Magasins** - Gros → Détail → POS avec validation  
✅ **Audit Trail** - StockMovement immutable pour chaque changement  
✅ **Seeder Demo** - Restaurant avec 8 produits pré-chargés  

---

## 🛠️ Installation & Lancement Local

### Prérequis

- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL (ou SQLite pour dev)
- Git

### Backend Setup

```bash
cd backend

# Installer dependencies
composer install

# Créer .env
cp .env.example .env
php artisan key:generate

# Database setup (SQLite pour dev)
touch database/database.sqlite

# Migrations & seeders
php artisan migrate --seed

# Démarrer serveur Laravel
php artisan serve  # http://localhost:8000
```

### Frontend Setup

```bash
cd frontend

# Installer dependencies
npm install

# Démarrer dev server
npm run dev  # http://localhost:5173
```

---

## 🧪 Tester l'API

### 1. Register Tenant (Mode B)

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Restaurant Test",
    "name": "Admin User",
    "email": "admin@test.com",
    "password": "password123",
    "password_confirmation": "password123",
    "mode_pos": "B"
  }'

# Réponse:
{
  "message": "Tenant créé avec succès (Mode B)",
  "token": "1|abc...",
  "tenant": { "id": 1, "name": "Restaurant Test", "mode_pos": "B" },
  "warehouses": [
    { "id": 1, "type": "gros", "name": "Gros" },
    { "id": 2, "type": "detail", "name": "Détail" },
    { "id": 3, "type": "pos", "name": "POS" }
  ]
}
```

### 2. Login

```bash
TOKEN=$(curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test.com",
    "password": "password123"
  }' | jq -r '.token')

echo $TOKEN
```

### 3. Create & Receive Purchase (CMP)

```bash
# Créer achat
curl -X POST http://localhost:8000/api/purchases \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_name": "Acme Distributor",
    "supplier_phone": "+229 12345678",
    "items": [
      { "product_id": 1, "quantity": 10, "unit_price": 1000 }
    ]
  }'

# Réponse: { "id": 1, "status": "pending", ... }

# Confirmer achat
curl -X POST http://localhost:8000/api/purchases/1/confirm \
  -H "Authorization: Bearer $TOKEN"

# Recevoir achat (déclenche CMP)
curl -X POST http://localhost:8000/api/purchases/1/receive \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      { "purchase_item_id": 1, "received_quantity": 10 }
    ]
  }'

# Vérifier stock avec CMP = 1000
curl -X GET "http://localhost:8000/api/stocks?warehouse_id=1" \
  -H "Authorization: Bearer $TOKEN"
```

### 4. Transfer Stock (Gros → Détail)

```bash
# Créer demande de transfert
curl -X POST http://localhost:8000/api/transfers \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "from_warehouse_id": 1,
    "to_warehouse_id": 2,
    "items": [
      { "product_id": 1, "quantity": 20 }
    ],
    "notes": "Ravitaillement hebdomadaire"
  }'

# Réponse: { "id": 1, "status": "pending", ... }

# Approuver & exécuter transfert
curl -X POST http://localhost:8000/api/transfers/1/approve \
  -H "Authorization: Bearer $TOKEN"

# Vérifier stock gros = 90, stock détail = 20
curl -X GET "http://localhost:8000/api/stocks" \
  -H "Authorization: Bearer $TOKEN" | jq '.data[] | {product_id, warehouse_id, quantity}'
```

---

## 📚 API Reference (Endpoints Testables)

### Auth
- `POST   /api/register` - Créer tenant + warehouses
- `POST   /api/login` - Get token
- `GET    /api/me` - Profil utilisateur
- `POST   /api/logout` - Logout

### Purchases
- `POST   /api/purchases` - Créer bon d'achat
- `GET    /api/purchases` - Lister
- `GET    /api/purchases/{id}` - Détail
- `POST   /api/purchases/{id}/confirm` - Confirmer
- `POST   /api/purchases/{id}/receive` - Recevoir (CMP)
- `POST   /api/purchases/{id}/cancel` - Annuler

### Transfers
- `GET    /api/transfers` - Lister transferts
- `POST   /api/transfers` - Créer demande
- `GET    /api/transfers/{id}` - Détail
- `POST   /api/transfers/{id}/approve` - Approuver + exécuter
- `POST   /api/transfers/{id}/cancel` - Annuler
- `GET    /api/transfers/pending` - Transfers en attente
- `GET    /api/transfers/statistics` - Stats

### Warehouses
- `GET    /api/warehouses` - Lister
- `GET    /api/warehouses/{id}` - Détail
- `GET    /api/warehouses/{id}/stock-value` - Total valeur stock

### Stocks
- `GET    /api/stocks` - Lister stocks

---

## 🧪 Tests

### Unitaires (PHPUnit)

```bash
cd backend

# Tous les tests
php artisan test

# Tests spécifiques
php artisan test tests/Feature/PurchaseReceiveTest.php
php artisan test tests/Feature/TransferTest.php
```

### Résultats actuels
- ✅ PurchaseReceiveTest: 7/7 passing
- ✅ TransferTest: 8/8 passing

---

## 📁 Structure des Dossiers

```
.
├── backend/
│   ├── app/
│   │   ├── Domains/
│   │   │   ├── Purchases/Services/PurchaseService.php
│   │   │   ├── Transfers/Services/TransferService.php
│   │   │   ├── Stocks/Services/StockService.php
│   │   │   └── ...
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── PurchaseController.php
│   │   │   ├── TransferController.php
│   │   │   ├── WarehouseController.php
│   │   │   └── ...
│   │   ├── Models/ (23 modèles)
│   │   └── ...
│   ├── database/
│   │   ├── migrations/ (29+ migrations)
│   │   ├── seeders/ (DemoDataSeeder)
│   │   └── factories/
│   ├── tests/Feature/
│   │   ├── PurchaseReceiveTest.php
│   │   ├── TransferTest.php
│   │   └── ...
│   └── routes/
│       └── api.php (40+ endpoints)
├── frontend/
│   ├── src/
│   │   ├── pages/ (OnboardingPage, LoginPage, DashboardPage, etc.)
│   │   ├── components/
│   │   ├── services/ (apiClient, offlineSync)
│   │   ├── stores/ (tenantStore)
│   │   └── ...
│   ├── package.json
│   ├── vite.config.js
│   └── tailwind.config.js
└── docs/ (specifications + guides)
```

---

## 🗺️ Roadmap (Itérations Prochaines)

**Itération 3: POS & Sales** (Prochaine)
- Sale endpoints avec deduction de stock
- Mode A/B logic (gros vs pos warehouse)
- Payments (cash/momo/bank simulation)
- POS UI page (React)

**Itération 4: Backoffice & Billing**
- Host admin dashboard (multitenancy)
- Subscription management
- Billing cron jobs
- Snapshots/backups

**Itération 5: Accounting & Exports**
- Auto-generate GL entries (sales/purchases/transfers)
- Export endpoints (Excel/PDF/Word)
- Financial reports (P&L, Balance Sheet)
- Async export jobs

---

## 🔐 Sécurité

- ✅ Sanctum authentication tokens
- ✅ Tenant isolation via middleware (tenant_id)
- ✅ Role-based access (admin, manager, cashier, etc.)
- ⏳ Rate limiting
- ⏳ CSRF tokens

---

## 🤝 Contributing

Créer une branche pour chaque feature:
```bash
git checkout -b feature/your-feature
git commit -m "feat: description"
git push origin feature/your-feature
# Créer PR sur GitHub
```

---

## 📧 Support

Questions? Ouvrir une issue sur GitHub.

---

## 📄 License

License Type: TBD (voir LICENSE file)

---

**Last Updated:** 23 November 2025  
**Committed:** v0.2-stock-flows  
**Next Release:** v0.3-pos-sales (ETA: 1-2 days)
