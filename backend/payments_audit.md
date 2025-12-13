# 🔒 AUDIT INTÉGRATIONS DE PAIEMENT - SIGEC

**Date:** 2024-12-11
**Status:** ⚠️ PARTIELLEMENT IMPLÉMENTÉ

---

## 1. RÉSUMÉ EXÉCUTIF

### Providers Identifiés
| Provider | SuperAdmin (Abonnements) | Tenant (Ventes) | Status |
|----------|-------------------------|-----------------|--------|
| **Fedapay** | ✅ Implémenté | ✅ Implémenté | Sandbox/Prod |
| **Kkiapay** | ✅ Implémenté | ✅ Implémenté | Sandbox/Prod |
| **MTN MoMo** | ✅ Implémenté | ✅ Implémenté | Sandbox/Prod |
| **Virement Bancaire** | ⚠️ Config seulement | ❌ Non implémenté | Manquant |

### Problèmes Critiques Identifiés
1. ❌ **Clés stockées en clair** - Pas de chiffrement AES-256
2. ❌ **Pas de vérification signature webhook** - Vulnérabilité sécurité
3. ❌ **Pas d'idempotence webhook** - Risque de double traitement
4. ❌ **Routes PSP tenant manquantes** - `getPspSettings`/`updatePspSettings` non implémentées
5. ❌ **Pas de refund** - Non implémenté
6. ❌ **Pas de réconciliation** - Non implémenté
7. ⚠️ **Isolation SuperAdmin/Tenant** - Partiellement respectée

---

## 2. DÉTAIL PAR PROVIDER

### 2.1 FEDAPAY

#### Endpoints Implémentés
| Endpoint | Type | Fichier | Status |
|----------|------|---------|--------|
| `POST /api/payments/initialize` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /api/payments/verify` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /payments/fedapay/callback` | Webhook Tenant | `PaymentController.php` | ⚠️ Sans signature |
| `POST /api/subscription-payment/initialize` | SuperAdmin | `SubscriptionPaymentController.php` | ✅ |
| `POST /webhooks/subscription/fedapay` | Webhook SuperAdmin | `SubscriptionPaymentController.php` | ⚠️ Sans signature |

#### Adapter
- **Fichier:** `app/Domains/Payments/Services/FedapayAdapter.php`
- **Sandbox URL:** `https://sandbox-api.fedapay.com/v1`
- **Production URL:** `https://api.fedapay.com/v1`
- **Switch:** Via `config('payments.environment')`

#### Stockage Clés
- **SuperAdmin:** `system_settings` table (clés: `fedapay_public_key`, `fedapay_secret_key`)
- **Tenant:** `tenant.settings` JSON field (clés: `psp_api_key`, `psp_secret_key`)
- **Chiffrement:** ❌ NON

#### Flows Supportés
- [x] Initialize payment
- [x] Verify payment
- [x] Webhook callback
- [ ] Refund
- [ ] Signature verification

---

### 2.2 KKIAPAY

#### Endpoints Implémentés
| Endpoint | Type | Fichier | Status |
|----------|------|---------|--------|
| `POST /api/payments/initialize` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /api/payments/verify` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /payments/kakiapay/callback` | Webhook Tenant | `PaymentController.php` | ⚠️ Sans signature |
| `POST /api/subscription-payment/initialize` | SuperAdmin | `SubscriptionPaymentController.php` | ✅ |
| `POST /webhooks/subscription/kkiapay` | Webhook SuperAdmin | `SubscriptionPaymentController.php` | ⚠️ Sans signature |

#### Adapter
- **Fichier:** `app/Domains/Payments/Services/KakiapayAdapter.php`
- **Sandbox URL:** `https://sandbox.kakiapay.com/api/v2`
- **Production URL:** `https://api.kakiapay.com/api/v2`

#### Stockage Clés
- **SuperAdmin:** `system_settings` (clés: `kkiapay_public_key`, `kkiapay_private_key`, `kkiapay_secret`)
- **Tenant:** Non implémenté séparément
- **Chiffrement:** ❌ NON

---

### 2.3 MTN MOMO

#### Endpoints Implémentés
| Endpoint | Type | Fichier | Status |
|----------|------|---------|--------|
| `POST /api/payments/initialize` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /api/payments/verify` | Tenant Sales | `PaymentController.php` | ✅ |
| `POST /api/subscription-payment/initialize` | SuperAdmin | `SubscriptionPaymentController.php` | ✅ |
| `POST /webhooks/subscription/momo` | Webhook SuperAdmin | `SubscriptionPaymentController.php` | ⚠️ |

#### Adapter
- **Fichier:** `app/Domains/Payments/Services/MomoAdapter.php`
- **Sandbox URL:** `https://sandbox.momoapi.mtn.com`
- **Production URL:** `https://proxy.momoapi.mtn.com`

#### Stockage Clés
- **SuperAdmin:** `system_settings` (clés: `momo_subscription_key`, `momo_api_user`, `momo_api_key`)
- **Tenant:** Via `tenant.settings`
- **Chiffrement:** ❌ NON

---

### 2.4 VIREMENT BANCAIRE

#### Status: ❌ NON IMPLÉMENTÉ

#### Config Existante (SuperAdmin seulement)
- `bank_name` - Nom de la banque
- `bank_iban` - IBAN
- `bank_bic` - BIC/SWIFT

#### Manquant
- [ ] Flow de paiement offline
- [ ] Création paiement pending avec référence
- [ ] Validation manuelle par admin
- [ ] Réconciliation avec relevé bancaire
- [ ] UI tenant pour virement

---

## 3. SÉCURITÉ & STOCKAGE

### 3.1 Stockage Actuel des Clés

#### SuperAdmin (system_settings)
```
fedapay_public_key    -> VARCHAR (clair)
fedapay_secret_key    -> VARCHAR (clair) ❌
kkiapay_public_key    -> VARCHAR (clair)
kkiapay_private_key   -> VARCHAR (clair) ❌
kkiapay_secret        -> VARCHAR (clair) ❌
momo_subscription_key -> VARCHAR (clair) ❌
momo_api_user         -> VARCHAR (clair)
momo_api_key          -> VARCHAR (clair) ❌
```

#### Tenant (tenant.settings JSON)
```
psp_api_key    -> JSON field (clair) ❌
psp_secret_key -> JSON field (clair) ❌
```

### 3.2 Problèmes de Sécurité
1. **Clés secrètes en clair** - Doivent être chiffrées AES-256
2. **Pas de masquage UI** - Les clés sont visibles en entier
3. **Pas de séparation stricte** - SuperAdmin peut potentiellement voir les settings tenant

---

## 4. WEBHOOKS

### 4.1 Routes Webhook Actuelles
```php
// Tenant Sales
POST /payments/fedapay/callback
POST /payments/kakiapay/callback

// SuperAdmin Subscriptions
POST /webhooks/subscription/fedapay
POST /webhooks/subscription/kkiapay
POST /webhooks/subscription/momo
```

### 4.2 Problèmes Webhooks
| Problème | Status | Impact |
|----------|--------|--------|
| Signature verification | ❌ Absent | Critique - Faux webhooks possibles |
| Idempotence | ❌ Absent | Double traitement possible |
| Tenant mapping | ⚠️ Partiel | Via metadata seulement |
| Retry handling | ❌ Absent | Perte de webhooks |
| Logging | ❌ Absent | Pas de traçabilité |

---

## 5. UI EXISTANTE

### 5.1 SuperAdmin UI
- **Route:** `/api/superadmin/system-settings`
- **Controller:** `SystemSettingsController.php`
- **Fonctionnalités:**
  - [x] Liste des paramètres par groupe
  - [x] Mise à jour des paramètres
  - [x] Init paramètres par défaut
  - [ ] Test de connexion PSP
  - [ ] Logs webhook
  - [ ] Masquage clés secrètes

### 5.2 Tenant UI
- **Routes:** `/api/tenant/psp-settings` (NON IMPLÉMENTÉES)
- **Fonctionnalités manquantes:**
  - [ ] Configuration clés PSP tenant
  - [ ] Toggle sandbox/production
  - [ ] Test de paiement sandbox
  - [ ] Logs webhook tenant
  - [ ] Masquage clés

---

## 6. TESTS EXISTANTS

### 6.1 Tests Automatisés
- ❌ Aucun test d'intégration paiement trouvé
- ❌ Aucun test webhook
- ❌ Aucun test idempotence

### 6.2 Tests Manuels Requis
- [ ] Fedapay sandbox charge
- [ ] Kkiapay sandbox charge
- [ ] MoMo sandbox charge
- [ ] Webhook replay
- [ ] Refund (non implémenté)

---

## 7. ACTIONS REQUISES (PRIORITÉ)

### 🔴 CRITIQUE (Sécurité)
1. Implémenter chiffrement AES-256 pour clés secrètes
2. Ajouter vérification signature webhooks
3. Implémenter idempotence webhooks (table dedup)

### 🟠 HAUTE (Fonctionnalité)
4. Implémenter routes tenant PSP settings
5. Implémenter flow virement bancaire
6. Ajouter refund API
7. Séparer strictement clés SuperAdmin/Tenant

### 🟡 MOYENNE (UX)
8. UI SuperAdmin: masquer clés, bouton test, logs
9. UI Tenant: config PSP, test sandbox
10. Logging webhooks

### 🟢 BASSE (Maintenance)
11. Tests d'intégration
12. Job réconciliation
13. Monitoring/alertes

---

## 8. FICHIERS CLÉS

```
app/Domains/Payments/Services/
├── PaymentGatewayAdapter.php    # Classe abstraite
├── FedapayAdapter.php           # Fedapay
├── KakiapayAdapter.php          # Kkiapay (note: typo Kakiapay)
└── MomoAdapter.php              # MTN MoMo

app/Http/Controllers/Api/
├── PaymentController.php        # Paiements ventes tenant
├── SubscriptionPaymentController.php  # Paiements abonnements
└── SuperAdmin/SystemSettingsController.php  # Config SuperAdmin

app/Models/System/
├── SystemSetting.php            # Paramètres système (clés SuperAdmin)
└── Payment.php                  # Paiements abonnements

routes/api.php                   # Routes webhooks lignes 622-629
```

---

**Audit réalisé le:** 2024-12-11
**Prochaine étape:** Implémenter corrections sécurité et fonctionnalités manquantes
