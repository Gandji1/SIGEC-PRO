# 🚀 DÉMARRER TOUT MAINTENANT

## Option 1: Démo Automatique Complète (Recommandé - 3 min)

```bash
cd /workspaces/SIGEC
./start-demo.sh
```

Cela va automatiquement:
1. ✅ Préparer la base de données
2. ✅ Lancer le serveur Laravel
3. ✅ Exécuter tous les tests
4. ✅ Afficher les résultats

---

## Option 2: Démo Manuelle avec Contrôle

```bash
# Terminal 1: Backend
cd /workspaces/SIGEC/backend
php artisan migrate --seed
php artisan serve

# Terminal 2: Tester
cd /workspaces/SIGEC
./test-demo.sh
```

---

## Option 3: Tester Endpoint par Endpoint

```bash
# Terminal 1: Backend
cd /workspaces/SIGEC/backend
php artisan serve

# Terminal 2: API calls
TOKEN=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Test",
    "name": "Admin",
    "email": "admin@test.com",
    "password": "demo123456",
    "password_confirmation": "demo123456",
    "mode_pos": "B"
  }' | jq -r '.token')

# Lister transferts
curl -X GET http://localhost:8000/api/transfers \
  -H "Authorization: Bearer $TOKEN" | jq '.'
```

---

## 📋 Checklist Avancées

✅ **Itération 1 - Auth + Purchases (100%)**
- Register tenant avec mode A/B
- Login + token
- Purchase CRUD
- CMP calculation
- 7 tests passing

✅ **Itération 2 - Transfers (90%)**
- Transfer workflow (request → approve → execute)
- Multi-warehouse stock deduction
- StockMovement audit trail
- 8 tests passing
- 7 endpoints actifs

⏳ **Itération 3 - POS & Sales (À venir)**
- SalesService
- PaymentService  
- SaleController (4 endpoints)
- POS Frontend page
- 8+ tests

---

## 📊 Fichiers Importants

```
AVANCEES.md           ← Vous êtes ici! Détails complets
DEMO.md              ← Guide de test détaillé
README_INSTALL.md    ← Installation backend/frontend
PROGRESS.md          ← Statut itérations
test-demo.sh         ← Script de test (exécutable)
start-demo.sh        ← Setup + démo auto (exécutable)
```

---

## 🎯 Résumé des Avancées

| Feature | Status | Tests |
|---------|--------|-------|
| Auth (Register/Login) | ✅ Done | - |
| Purchases (Create/Receive) | ✅ Done | 7/7 ✓ |
| CMP Calculation | ✅ Done | In Purchase tests |
| Transfers (Request/Approve) | ✅ Done | 8/8 ✓ |
| Stock Audit Trail | ✅ Done | In Transfer tests |
| **Total Coverage** | **55%** | **15/15 ✓** |

---

## ⚡ Quick Commands

```bash
# Voir les avancées en détail
cat AVANCEES.md

# Lancer la démo complète
./start-demo.sh

# Voir les logs en direct
tail -f backend/storage/logs/laravel.log

# Tester une endpoint spécifique
curl -X GET http://localhost:8000/api/transfers \
  -H "Authorization: Bearer YOUR_TOKEN"

# Voir l'état de la base
sqlite3 backend/database/database.sqlite ".tables"

# Voir les tests
php artisan test

# Voir les migrations
php artisan migrate:status
```

---

## 🎬 Voir les Avancées Maintenant

**Option Préférée:** Lance ceci dans un terminal:

```bash
cd /workspaces/SIGEC && ./start-demo.sh
```

Ça va automatiquement:
1. Créer la base de données
2. Lancer le serveur
3. Exécuter la démo complète
4. Montrer les stocks avant/après transfers
5. Afficher les statistiques

**Durée:** ~3 minutes ⏱️

---

**Questions?** Voir `AVANCEES.md` ou `DEMO.md` pour plus de détails! 📚
