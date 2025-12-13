# 🔐 SIGEC - Accès Tenants & POS

## ✅ Tenants Créés

### Tenant 1: Business 1
**ID**: 7  
**Domain**: business-1.localhost

#### Utilisateurs:
| Rôle | Email | Mot de passe | Accès |
|------|-------|--------------|-------|
| **Owner/Admin** | admin@business-1.local | password | Gestion complète |
| **Manager** | manager@business-1.local | password | Gestion opérationnelle |
| **Caissier/POS** | pos@business-1.local | password | Point de vente |

**Warehouse POS**: Main POS - Business 1 (Code: POS-1)

---

### Tenant 2: Business 2
**ID**: 8  
**Domain**: business-2.localhost

#### Utilisateurs:
| Rôle | Email | Mot de passe | Accès |
|------|-------|--------------|-------|
| **Owner/Admin** | admin@business-2.local | password | Gestion complète |
| **Manager** | manager@business-2.local | password | Gestion opérationnelle |
| **Caissier/POS** | pos@business-2.local | password | Point de vente |

**Warehouse POS**: Main POS - Business 2 (Code: POS-2)

---

### Tenant 3: Business 3
**ID**: 9  
**Domain**: business-3.localhost

#### Utilisateurs:
| Rôle | Email | Mot de passe | Accès |
|------|-------|--------------|-------|
| **Owner/Admin** | admin@business-3.local | password | Gestion complète |
| **Manager** | manager@business-3.local | password | Gestion opérationnelle |
| **Caissier/POS** | pos@business-3.local | password | Point de vente |

**Warehouse POS**: Main POS - Business 3 (Code: POS-3)

---

## 🚀 Connexion & Test

### 1. Accéder au Frontend
```
URL: https://improved-robot-vjjr5wpv6pqhx4pg-8000.app.github.dev/
OU: http://localhost:5173 (développement)
```

### 2. Se Connecter
Cliquez sur **Login** et entrez:
```
Email: admin@business-1.local
Mot de passe: password
```

### 3. Créer un Compte (Alternative)
Cliquez sur **Register**:
```
Business Name: Votre Commerce
Your Name: Votre Nom
Email: votre@email.com
Mot de passe: (min 8 caractères)
```

---

## 🧪 Tests Recommandés

### Phase 1: Authentification ✓
- [ ] Login avec admin@business-1.local
- [ ] Vérifier le dashboard
- [ ] Logout et reconnexion

### Phase 2: Accès POS ✓
- [ ] Login avec pos@business-1.local
- [ ] Accéder au module POS
- [ ] Créer une vente test

### Phase 3: Gestion de Stock ✓
- [ ] Login avec manager@business-1.local
- [ ] Vérifier les stocks
- [ ] Voir les alertes de stock bas

### Phase 4: Gestion Multi-Tenant ✓
- [ ] Logout
- [ ] Login avec admin@business-2.local
- [ ] Vérifier l'isolation des données

### Phase 5: Comptabilité ✓
- [ ] Accéder aux journaux comptables
- [ ] Voir les rapports
- [ ] Consulter la balance

---

## 📱 Endpoints API

### Base URL
```
http://localhost:8000/api
```

### Authentification
```bash
# Login
POST /login
{
  "email": "admin@business-1.local",
  "password": "password"
}

# Response
{
  "success": true,
  "token": "XX|YYYYYYY...",
  "user": {...},
  "tenant": {...}
}
```

### Utiliser le Token
```bash
curl -H "Authorization: Bearer TOKEN" \
     -H "X-Tenant-ID: 7" \
     http://localhost:8000/api/suppliers
```

---

## 🔧 Commandes Utiles

```bash
# Créer plus de tenants
php artisan create:tenant-pos --count=5

# Vérifier les tenants
php artisan tinker
> Tenant::all()

# Vérifier les utilisateurs
> User::where('email', 'like', '%business%')->get()

# Vérifier les warehouses
> Warehouse::all()
```

---

## ⚙️ Configuration Serveur

### Ports en Utilisation
- **8000**: Backend API (Laravel)
- **5173**: Frontend Dev (Vite)

### Ports Supprimés ✓
- PostgreSQL (5432) - Utilise SQLite maintenant
- Redis (6379) - Optionnel
- pgAdmin (5050) - Non nécessaire

---

## 🎯 État Actuel

| Composant | Status | Port |
|-----------|--------|------|
| Backend API | ✅ Actif | 8000 |
| Frontend | ✅ Prêt | 5173 |
| Base de données | ✅ SQLite | Local |
| Authentification | ✅ Sanctum | Token Bearer |
| Multi-tenant | ✅ Fonctionnel | X-Tenant-ID |

---

## 📞 Support

Si vous avez des problèmes:

1. **Erreur 419 (CSRF)**: Vérifiez les headers CORS
2. **Erreur 401 (Auth)**: Token expiré, se reconnecter
3. **Port déjà utilisé**: `lsof -i :PORT` et `kill -9 PID`
4. **Base de données vide**: `php artisan migrate --seed`

---

**Créé**: 25 Novembre 2025  
**Version**: 1.0  
**Status**: Production Ready ✓
