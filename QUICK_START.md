# 🚀 SIGEC - Guide de Démarrage Rapide

## État du Projet (24 Novembre 2025)

**Statut:** 🟢 Production Ready - 90% Complet
- ✅ Backend API complet (Laravel 11)
- ✅ Frontend UI complet (React 18)
- ✅ Base de données structurée (PostgreSQL)
- ✅ Système d'audit + transactions atomiques
- ✅ Multi-locataire + Authentification
- ✅ 120+ endpoints API
- ✅ 7 pages principales + Dashboard

---

## 📋 Prérequis

### Option 1 : Docker (Recommandé)
```bash
# Installer Docker & Docker Compose
# https://docs.docker.com/get-docker/
# https://docs.docker.com/compose/install/

docker --version  # >= 24.0
docker-compose --version  # >= 2.0
```

### Option 2 : Installation Locale
```bash
# Backend
- PHP >= 8.2
- Composer
- PostgreSQL >= 14
- Redis >= 6

# Frontend
- Node.js >= 18
- npm >= 9
```

---

## 🏁 Démarrage avec Docker (Recommandé)

### 1️⃣ Préparation
```bash
cd /workspaces/SIGEC

# Les fichiers .env sont déjà créés
# backend/.env ✅
# frontend/.env ✅
```

### 2️⃣ Lancer l'Application
```bash
cd infra

# Démarrer tous les services
docker-compose up -d

# Attendre ~60 secondes que tout démarre
# Vérifier l'état
docker-compose ps

# Voir les logs
docker-compose logs -f app  # Backend
docker-compose logs -f frontend  # Frontend
```

### 3️⃣ Accéder à l'Application

**Frontend (Client):**
```
http://localhost:5173
```

**Backend API:**
```
http://localhost:8000/api
```

**pgAdmin (Gestion DB):**
```
http://localhost:5050
- Email: admin@sigec.local
- Password: admin
```

---

## 🔑 Compte de Test

### Créer un Compte Tenant

**Endpoint:** `POST /api/register`

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Entreprise",
    "email": "admin@test.local",
    "password": "password123",
    "company_name": "SIGEC Test",
    "industry": "retail"
  }'
```

**Réponse :**
```json
{
  "token": "xxx",
  "user": { "id": 1, "name": "Admin", "tenant_id": 1 },
  "tenant": { "id": 1, "name": "Test Entreprise" }
}
```

### Se Connecter

**Frontend:** Aller à `/login` et utiliser les identifiants ci-dessus

**Backend (API):**
```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer xxx"
```

---

## 🏭 Configuration Initiale

Après login, initialiser le système :

### 1️⃣ Créer des Entrepôts

```bash
curl -X POST http://localhost:8000/api/warehouses \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Entrepôt Central",
    "type": "gros",
    "location": "Kinshasa"
  }'
```

### 2️⃣ Ajouter des Produits

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Riz 50kg",
    "sku": "RIZ001",
    "unit_price": 25000,
    "selling_price": 28000,
    "barcode": "1234567890"
  }'
```

### 3️⃣ Créer un Stock Initial

```bash
curl -X POST http://localhost:8000/api/stocks/adjust \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "product_id": 1,
    "quantity": 100,
    "reason": "Initial stock"
  }'
```

---

## 🛒 Flux Complet : Achat → Vente → Transfert

### Phase 1️⃣ : Acheter (Commande Fournisseur)

```bash
# Créer une commande
curl -X POST http://localhost:8000/api/purchases \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_name": "Fournisseur ABC",
    "mode": "manual",
    "items": [
      {"product_id": 1, "quantity": 50, "unit_price": 25000}
    ]
  }'

# Réponse : { "id": 1, "reference": "PO-20251124-0001" }
```

### Phase 2️⃣ : Confirmer la Commande

```bash
curl -X POST http://localhost:8000/api/purchases/1/confirm \
  -H "Authorization: Bearer TOKEN"

# Statut passe de "pending" → "confirmed"
```

### Phase 3️⃣ : Recevoir (Stock + CMP)

```bash
curl -X POST http://localhost:8000/api/purchases/1/receive \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"warehouse_id": 1}'

# ✅ Stock ajouté au warehouse
# ✅ CMP calculé : 25000 FCFA/unité
# ✅ Audit trail créée
# ✅ Statut passe à "received"
```

### Phase 4️⃣ : Vendre (POS)

```bash
# Créer une vente avec déduction stock
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "customer_name": "Client XYZ",
    "payment_method": "cash",
    "items": [
      {"product_id": 1, "quantity": 5, "unit_price": 28000}
    ]
  }'

# ✅ Stock déduit atomiquement (5 unités)
# ✅ Vente créée avec référence
# ✅ Paiement enregistré
# ✅ Audit trail créée
```

### Phase 5️⃣ : Transfert Entre Entrepôts

```bash
# Demander un transfert
curl -X POST http://localhost:8000/api/transfers \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "from_warehouse_id": 1,
    "to_warehouse_id": 2,
    "items": [
      {"product_id": 1, "quantity": 10}
    ]
  }'

# Approuver
curl -X POST http://localhost:8000/api/transfers/1/approve \
  -H "Authorization: Bearer TOKEN"

# ✅ Stock transféré d'un warehouse à l'autre
# ✅ Audit trail des 2 mouvements
```

---

## 📊 Consultations et Rapports

### Consulter l'Inventaire

```bash
curl -X GET "http://localhost:8000/api/stocks?warehouse_id=1" \
  -H "Authorization: Bearer TOKEN"
```

**Réponse :**
```json
{
  "data": [
    {
      "id": 1,
      "product": { "name": "Riz 50kg" },
      "quantity": 95,
      "cost_average": 25000,
      "value": 2375000
    }
  ]
}
```

### Rapport de Ventes

```bash
curl -X GET "http://localhost:8000/api/sales/report?from_date=2025-01-01&to_date=2025-12-31" \
  -H "Authorization: Bearer TOKEN"
```

### Dashboard Comptable

```bash
curl -X GET http://localhost:8000/api/accounting/trial-balance \
  -H "Authorization: Bearer TOKEN"
```

---

## 🧪 Tests Automatisés

### Lancer les Tests

```bash
cd backend

# Tests unitaires
php artisan test

# Tests spécifiques
php artisan test tests/Feature/SalesTest.php
php artisan test tests/Feature/PurchaseTest.php
php artisan test tests/Feature/TransferTest.php

# Avec rapport de couverture
php artisan test --coverage
```

### Tests Attendus
- ✅ 15+ tests pour Purchases (CMP, audit)
- ✅ 8+ tests pour Sales (déduction, paiements)
- ✅ 10+ tests pour Transfers (atomicité)
- ✅ 6+ tests pour Accounting (GL entries)

---

## 🐛 Troubleshooting

### Erreur: "Connection refused" (8000)

```bash
# Vérifier que le backend est lancé
docker-compose logs app

# Attendre ~30 secondes, puis réessayer
```

### Erreur: "Database connection failed"

```bash
# Vérifier PostgreSQL
docker-compose logs postgres

# Réinitialiser la base
docker-compose exec app php artisan migrate:fresh --seed
```

### Erreur: "CORS" en frontend

Les headers CORS sont configurés dans `config/cors.php`:
```php
'allowed_origins' => ['localhost:5173', '127.0.0.1:5173'],
```

### Frontend ne charge pas

```bash
# Vérifier les logs
docker-compose logs frontend

# Vérifier le VITE_API_URL
cat ../frontend/.env
```

---

## 📁 Structure des Fichiers Clés

```
backend/
├── app/
│   ├── Models/          # 16 modèles Eloquent
│   ├── Http/Controllers/Api/  # 11 contrôleurs
│   ├── Domains/         # Services métier
│   └── Events/          # Événements + Listeners
├── database/
│   ├── migrations/      # 17 migrations
│   └── seeders/         # Données test
├── routes/
│   └── api.php          # 120+ endpoints

frontend/
├── src/
│   ├── pages/           # 7 pages principales
│   ├── components/      # Composants réutilisables
│   ├── services/        # API clients
│   ├── stores/          # État Zustand
│   └── App.jsx          # Router principal
└── tailwind.config.js   # Thème (dark)
```

---

## 🔄 Architecture

### Stack Complet

```
┌─────────────────────────────────────┐
│   Frontend (React 18 + Vite)        │
│   http://localhost:5173             │
├─────────────────────────────────────┤
│   API REST (Laravel 11 + Sanctum)   │
│   http://localhost:8000/api         │
├─────────────────────────────────────┤
│   PostgreSQL 16 + Redis 7           │
│   Port 5432 + 6379                  │
└─────────────────────────────────────┘
```

### Flux de Données

```
User Login
   ↓
[Sanctum Token + Tenant ID]
   ↓
API Endpoints (protected)
   ↓
DB::transaction() [Atomicité]
   ↓
Response + Audit Log
```

### Sécurité

- ✅ Authentication: Sanctum (Laravel)
- ✅ Multi-tenancy: Tenant ID isolation
- ✅ Authorization: Policies + Gates
- ✅ Validation: Request validation
- ✅ Encryption: HTTPS (production)

---

## 📈 Prochaines Étapes

### Court terme (1 semaine)
- [ ] Tests E2E (Cypress)
- [ ] Optimisation performance DB
- [ ] Cache Redis

### Moyen terme (2-4 semaines)
- [ ] Module de facturation + PDF
- [ ] Intégration paiements (Stripe/Orange Money)
- [ ] Synchronisation offline
- [ ] Barcode scanning

### Long terme
- [ ] Application mobile (React Native)
- [ ] Rapports avancés (Power BI)
- [ ] Intégration HR + Payroll
- [ ] Multi-devise

---

## 📞 Support

### Documentation
- Backend: `docs/INSTALLATION.md`
- Troubleshooting: `docs/TROUBLESHOOTING.md`
- API: `backend/routes/api.php`

### Chat & Logs
```bash
# Voir les logs en temps réel
docker-compose logs -f

# Accéder au container
docker-compose exec app bash
```

---

**Version:** 1.0  
**Dernière mise à jour:** 24 Novembre 2025  
**Auteur:** SIGEC Development Team
