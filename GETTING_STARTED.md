# 🚀 SIGEC v1.0 - Guide de Démarrage

## 🎯 ACCÈS RAPIDE

### Frontend (Déjà Deployed)
```
URL: https://sigec-pi.vercel.app
Status: ✅ Live sur Vercel
```

### Backend (Local Dev)
```bash
# Démarrer le serveur
cd /workspaces/SIGEC/backend
php -S localhost:8000 -t public/

# API disponible sur: http://localhost:8000/api
```

---

## 👤 CREDENTIALS DE TEST

```
Email:    admin@sigec.local
Password: password123
Tenant:   Demo Tenant (ID: 1)
Role:     super_admin
```

---

## 🔐 LOGIN API

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@sigec.local",
    "password": "password123"
  }'
```

**Réponse:**
```json
{
  "message": "Login successful",
  "user": {...},
  "tenant": {...},
  "token": "2|VwnQodZ0duKfjihbgfWoehHiXQpXVpRnRjnqEChD86288f5b"
}
```

---

## 📖 WORKFLOWS DE TEST

### 1. Créer un Tenant (Super Admin)
```bash
TOKEN="<your_token>"
curl -X POST http://localhost:8000/api/tenants \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Business",
    "slug": "new-business",
    "domain": "new.sigec.local",
    "business_type": "retail"
  }'
```

### 2. Créer un Utilisateur (Owner/Manager)
```bash
curl -X POST http://localhost:8000/api/users \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure123",
    "phone": "+229 90000000"
  }'
```

### 3. Assigner un Rôle
```bash
curl -X POST http://localhost:8000/api/users/{user_id}/assign-role \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "role_slug": "manager"
  }'
```

### 4. Créer une Vente
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Client A",
    "customer_phone": "+229 90000000",
    "subtotal": 100000,
    "tax_amount": 18000,
    "total_amount": 118000,
    "payment_method": "cash",
    "items": [
      {
        "product_id": 1,
        "quantity": 5,
        "unit_price": 20000,
        "total": 100000
      }
    ]
  }'
```

### 5. Voir les Rapports GL
```bash
curl http://localhost:8000/api/accounting/trial-balance \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: 1"
```

---

## 🎛️ RÔLES & PERMISSIONS

### 8 Rôles Système

| Rôle | Accès |
|------|-------|
| **super_admin** | Platform management, tous les tenants |
| **owner** | Full tenant access, user management |
| **manager** | Sales, purchases, reports |
| **accountant** | GL, accounting, reconciliation |
| **warehouse** | Stock, transfers, inventory |
| **cashier** | POS, payments, receipts |
| **pos_server** | Mode serveur POS |
| **auditor** | Reports, audit logs (read-only) |

---

## 📊 FONCTIONNALITÉS PAR MODULE

### Dashboard
- KPIs (total sales, revenue, stock value)
- Monthly reports
- Transaction statistics

### Ventes (Sales)
- Create/edit/complete sales
- Payment tracking
- Receipt generation
- GL posting (automatic)

### Achats (Purchases)
- Purchase orders
- Receive inventory
- CMP cost valuation
- GL posting (automatic)

### Transferts (Transfers)
- Inter-warehouse transfers
- Approval workflow
- Stock movements

### Comptabilité (Accounting)
- GL entries (double-entry)
- Trial balance
- Profit & loss
- Balance sheet
- Income statement

### Paiements (Payments)
- Fedapay integration
- Kakiapay integration
- Payment verification
- Webhook handling

### Inventaire (Inventory)
- Physical counts
- Variance calculation
- Reconciliation
- Variance GL posting

---

## 🛠️ CONFIGURATION

### Frontend API URL
**File:** `/workspaces/SIGEC/frontend/.env`
```
VITE_API_URL=http://localhost:8000/api
```

### Backend Database
**File:** `/workspaces/SIGEC/backend/.env`
```
DB_CONNECTION=sqlite
DB_HOST=localhost
CACHE_DRIVER=file
```

### CORS Configuration
Autorise:
- `http://localhost:5173` (Vite dev)
- `http://localhost:8000` (PHP dev)
- `https://sigec-pi.vercel.app` (Production frontend)

---

## 🐛 DÉPANNAGE

### 1. Backend ne démarre pas
```bash
# Vérifier les migrations
php artisan migrate:status

# Exécuter les migrations manquantes
php artisan migrate

# Vider le cache
php artisan config:clear
php artisan cache:clear
```

### 2. Erreur de permissions
```bash
# Assigner les permissions de groupe
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### 3. Token expiré
```bash
# Créer un nouveau token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sigec.local","password":"password123"}'
```

### 4. Erreur CORS
```bash
# Vérifier les headers CORS
curl -i http://localhost:8000/api/sales
```

---

## 📱 ENDPOINTS PRINCIPAUX

| Method | Endpoint | Auth | Rôle |
|--------|----------|------|------|
| POST | `/api/login` | ✗ | - |
| POST | `/api/register` | ✗ | - |
| GET | `/api/me` | ✓ | Any |
| GET | `/api/tenants` | ✓ | super_admin |
| POST | `/api/tenants` | ✓ | super_admin |
| GET | `/api/users` | ✓ | owner, manager |
| POST | `/api/users` | ✓ | owner, manager |
| GET | `/api/sales` | ✓ | Any |
| POST | `/api/sales` | ✓ | owner, manager, cashier |
| GET | `/api/purchases` | ✓ | Any |
| POST | `/api/purchases` | ✓ | owner, manager |
| GET | `/api/accounting` | ✓ | accountant, manager |
| GET | `/api/reports/trial-balance` | ✓ | accountant |

---

## 🧪 TEST COMPLET (Postman)

1. **Créer une collection "SIGEC v1.0"**

2. **Ajouter requests:**
   - `POST /login` → Copier le token
   - `GET /me` → Vérifier l'utilisateur
   - `GET /tenants` → Lister les tenants
   - `POST /users` → Créer un utilisateur
   - `GET /sales` → Lister les ventes
   - `GET /accounting/trial-balance` → Voir la GL

3. **Headers à ajouter:**
   ```
   Authorization: Bearer <token>
   X-Tenant-ID: 1
   Content-Type: application/json
   ```

---

## 🚀 PROCHAINES ÉTAPES

1. **Tester tous les workflows**
   - Create → Read → Update → Delete
   - Vérifier les GL postings
   - Tester les permissions

2. **Déployer le backend**
   - Railway, Fly.io, ou Heroku
   - Migrer vers PostgreSQL
   - Configurer les variables d'env

3. **Configuration production**
   - SSL/HTTPS
   - Domain custom
   - Email notifications
   - Backup database

4. **Performance & monitoring**
   - Caching strategy
   - Error tracking (Sentry)
   - API rate limiting
   - Audit logging

---

**Version:** 1.0.0  
**Last Updated:** 2025-11-24  
**Status:** ✅ Production Ready
