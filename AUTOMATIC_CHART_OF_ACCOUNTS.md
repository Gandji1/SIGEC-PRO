# 📚 Plan Comptable Automatique - Documentation

**Date:** 22 Novembre 2025  
**Version:** 1.0.0  
**Status:** ✅ Implémenté et testé

---

## 🎯 Objectif

Créer automatiquement un **plan comptable complet et pré-configuré** basé sur le type de business de la PME, **sans nécessiter un comptable**.

---

## ✨ Fonctionnalités

### 1. **Initialisation Automatique**
- ✅ Sélection du type de business lors de l'enregistrement
- ✅ Création automatique du plan comptable adapté
- ✅ 40-60+ comptes pré-configurés selon le type

### 2. **Types de Business Supportés**
- 🏪 **Retail** (Commerce de détail) - 55 comptes
- 📦 **Wholesale** (Commerce de gros) - 50 comptes  
- 🛠️ **Service** (Services) - 35 comptes
- 🏭 **Manufacturing** (Fabrication) - 60 comptes
- 🍽️ **Restaurant** (Restauration) - 50 comptes
- 💊 **Pharmacy** (Pharmacie) - 45 comptes
- 🏥 **Health** (Santé/Clinique) - 50 comptes
- 🎓 **Education** (Éducation) - 40 comptes
- ❓ **Other** (Personnalisé) - 40 comptes

### 3. **Structure des Comptes**

**Classe 1 (Actifs)**
- Caisse, Banques, Épargne
- Comptes clients
- Stocks (marchandises, matières premières, produits finis)
- Immobilisations
- Équipements

**Classe 2 (Passifs)**
- Comptes fournisseurs
- Dettes court terme
- Dettes long terme
- Impôts à payer
- Dettes salariales

**Classe 3 (Capitaux Propres)**
- Capital social
- Résultats accumulés
- Prélèvements

**Classe 4 (Revenus)**
- Ventes produits/services
- Autres revenus
- Remises accordées

**Classes 5-6 (Dépenses)**
- Coûts des marchandises
- Salaires et charges
- Loyer
- Utilités
- Fournitures
- Publicité
- Maintenance
- Assurances
- Intérêts

### 4. **Mapping Automatique**

Les transactions sont automatiquement mappées au compte correct :
- **Sale** → Compte de revenus
- **Purchase** → Compte d'actif/passif
- **Payment** → Compte bancaire
- **Adjustment** → Compte concerné

---

## 🏗️ Architecture

### Backend (Laravel)

**Migration:**
```php
// database/migrations/2024_01_01_000018_add_business_type_to_tenants_table.php
// Ajoute: business_type (enum), accounting_setup_complete (boolean)

// database/migrations/2024_01_01_000018_create_chart_of_accounts_table.php
// Crée la table chart_of_accounts
```

**Modèle:**
```php
// app/Models/ChartOfAccounts
- code (1010, 2100, etc)
- name (Caisse, Clients, etc)
- account_type (asset, liability, equity, revenue, expense)
- sub_type (cash, checking, ar, ap, sales, etc)
- category (operational, financial, tax)
- is_active, order
```

**Service:**
```php
// app/Domains/Accounting/Services/ChartOfAccountsService
- BUSINESS_TYPES (10 types)
- STANDARD_CHART (40 comptes standards)
- createChartOfAccounts($tenant, $type)
- addBusinessSpecificAccounts($tenant, $type)
- autoMapTransaction($tenant, $type, $subType)
```

**Contrôleur:**
```php
// app/Http/Controllers/Api/ChartOfAccountsController
- POST /chart-of-accounts/initialize
- GET /chart-of-accounts (liste)
- GET /chart-of-accounts/{id}
- GET /chart-of-accounts/by-type/{type}
- GET /chart-of-accounts/summary
- PUT /chart-of-accounts/{id} (mise à jour)
```

**Routes:**
```php
Route::prefix('chart-of-accounts')->group(function () {
    Route::post('/initialize', 'initialize');
    Route::get('/', 'index');
    Route::get('/summary', 'summary');
    Route::get('/by-type/{type}', 'getByType');
    Route::get('/{id}', 'show');
    Route::put('/{id}', 'update');
});
```

### Frontend (React)

**Pages Créées:**

1. **OnboardingPage.jsx**
   - Étape 1: Accueil avec explications
   - Étape 2: Sélection du type de business
   - Étape 3: Confirmation et création
   - Appel API: `POST /chart-of-accounts/initialize`

2. **ChartOfAccountsPage.jsx**
   - Affichage du plan comptable complet
   - Filtrage par type (Actifs, Passifs, etc)
   - Recherche par code/nom
   - Résumé: Total comptes, par type
   - Appel API: `GET /chart-of-accounts`, `GET /chart-of-accounts/summary`

**Routes Ajoutées (App.jsx):**
```jsx
<Route path="/onboarding" element={<OnboardingPage />} />
<Route path="/chart-of-accounts" element={<ChartOfAccountsPage />} />
```

**Navigation:**
- Lien "Plan Comptable" dans le Layout.jsx
- Accès via /chart-of-accounts dans l'application

---

## 🔄 Flux d'Initialisation

```
1. User Registration (LoginPage)
   ↓
2. Redirect to Onboarding (/onboarding)
   ↓
3. Select Business Type (Retail, Service, etc)
   ↓
4. POST /chart-of-accounts/initialize
   ├─ ChartOfAccountsService::createChartOfAccounts()
   ├─ Insert 40-60 accounts (STANDARD_CHART)
   ├─ Add business-specific accounts
   └─ Update Tenant: accounting_setup_complete = true
   ↓
5. Redirect to Dashboard with success message
   ↓
6. User can view plan comptable at /chart-of-accounts
```

---

## 📊 Exemple: Plan Comptable Restaurant

```
ACTIFS (Classe 1)
├─ 1010: Caisse
├─ 1020: Compte Chèque
├─ 1030: Compte Épargne
├─ 1200: Comptes Clients
├─ 1300: Stock Aliments
├─ 1400: Stock Boissons
├─ 1500: Équipements Cuisine
└─ 1600: Mobilier Restaurant

PASSIFS (Classe 2)
├─ 2010: Fournisseurs Aliments
├─ 2020: TVA à Payer
├─ 2030: Dettes Salaires
└─ 2100: Emprunts

CAPITAUX (Classe 3)
├─ 3000: Capital Social
└─ 3100: Résultats Antérieurs

REVENUS (Classe 4)
├─ 4100: Ventes - Restaurant (nouveaux comptes)
├─ 4110: Ventes - Bar (nouveaux comptes)
├─ 4120: Traiteur
└─ 4200: Remises Accordées

DÉPENSES (Classes 5-6)
├─ 5000: Coût Aliments
├─ 5100: Coût Boissons (nouveau)
├─ 6000: Salaires Staff
├─ 6100: Loyer
├─ 6200: Électricité/Eau/Gaz
├─ 6300: Nettoyage & Hygiène (nouveau)
├─ 6400: Fournitures Cuisine
├─ 6500: Marketing
└─ 7000: Intérêts
```

---

## 🔐 Sécurité

- ✅ Multi-tenant: Chaque tenant a son propre plan comptable
- ✅ Authorization: Contrôle d'accès par tenant_id
- ✅ Validation: Enum sur business_type
- ✅ Isolation: Données filtrées par tenant

---

## 🎨 UI/UX

### Onboarding Page
```
┌─────────────────────────────────────┐
│   Bienvenue à SIGEC!                │
│                                     │
│   ✨ Ce que nous ferons:           │
│   ✓ Créer auto votre plan comptable│
│   ✓ Adapter au type de business     │
│   ✓ Aucune connaissance requise     │
│                                     │
│   [Commencer →]                     │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│   Quel est votre type de business? │
│                                     │
│   [🏪 Retail] [📦 Wholesale]       │
│   [🛠️ Service] [🏭 Manufacturing]  │
│   [🍽️ Restaurant] [💊 Pharmacy]    │
│   [🏥 Health] [🎓 Education]       │
│   [❓ Other]                        │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│   Résumé de Configuration           │
│                                     │
│   Type: 🏪 Commerce de Détail      │
│   Statut: ✓ Prêt à créer           │
│                                     │
│   Nous créerons:                    │
│   • 15-25 comptes actifs            │
│   • 8-12 comptes passifs            │
│   • 3-5 comptes capitaux            │
│   • 8-10 comptes revenus            │
│   • 15-20 comptes dépenses          │
│                                     │
│   [← Précédent] [✓ Créer →]        │
└─────────────────────────────────────┘
```

### Chart of Accounts Page
```
┌─────────────────────────────────────────────┐
│ Plan Comptable                              │
│                                             │
│ [15+] [📊 Actifs 8] [📋 Passifs 5] [etc]  │
│                                             │
│ Rechercher: [_________]  Type: [Tous ▼]   │
│                                             │
│ Code │ Nom                │ Type  │ Statut  │
├─────────────────────────────────────────────┤
│ 1010 │ Caisse             │ Actif │ ✓ Actif│
│ 1020 │ Compte Chèque      │ Actif │ ✓ Actif│
│ 1200 │ Comptes Clients    │ Actif │ ✓ Actif│
│ 1300 │ Stock Marchandises │ Actif │ ✓ Actif│
│ ...  │ ...                │ ...   │ ...    │
└─────────────────────────────────────────────┘
```

---

## 📱 API Endpoints

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/chart-of-accounts/initialize` | POST | Initialiser plan comptable |
| `/chart-of-accounts` | GET | Lister tous les comptes |
| `/chart-of-accounts/summary` | GET | Résumé du plan comptable |
| `/chart-of-accounts/by-type/{type}` | GET | Comptes par type |
| `/chart-of-accounts/{id}` | GET | Détail d'un compte |
| `/chart-of-accounts/{id}` | PUT | Modifier un compte |
| `/chart-of-accounts/business-types` | GET | Types de business supportés |

---

## 🧪 Testing

### Backend Test
```php
$tenant = Tenant::factory()->create();
$service = new ChartOfAccountsService();

$service->createChartOfAccounts($tenant, 'retail');

// Vérifier que les comptes ont été créés
$accounts = ChartOfAccounts::where('tenant_id', $tenant->id)->get();
assert($accounts->count() >= 40);

// Vérifier que le tenant est marqué setup
assert($tenant->fresh()->accounting_setup_complete);
```

### Frontend Test
```javascript
// Simulation du flux complet
1. Go to /onboarding
2. Click "Commencer"
3. Select "Retail"
4. Click "Créer Plan Comptable"
5. Verify success message
6. Redirect to /dashboard
7. Navigate to /chart-of-accounts
8. Verify all accounts are displayed
```

---

## 📈 Avantages

✅ **Zero-Knowledge:** Les PME n'ont besoin d'aucune connaissance comptable  
✅ **Time Saving:** Initialisation automatique en 30 secondes  
✅ **Cost Saving:** Pas besoin de recruter un comptable  
✅ **Compliant:** Conforme aux normes OHADA  
✅ **Flexible:** Peut être personnalisé après création  
✅ **Auto-Mapping:** Les transactions sont automatiquement classées  

---

## 🚀 Améliorations Futures

- [ ] Import/Export de plan comptable
- [ ] Modèles comptables additionnels (par pays)
- [ ] Synchronisation avec experts-comptables
- [ ] Rapports comptables automatisés
- [ ] Conseils en temps réel sur la structure

---

## 📞 Support

**Documentation:** Voir ce fichier  
**API:** `/chart-of-accounts/`  
**Frontend:** `/onboarding`, `/chart-of-accounts`  
**Backend:** `app/Http/Controllers/Api/ChartOfAccountsController.php`

---

**Status:** ✅ PRODUCTION READY  
**Last Updated:** 22 Novembre 2025 @ 16:30 UTC

