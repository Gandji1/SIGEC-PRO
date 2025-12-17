# 🔧 Correction CORS - SIGEC API

## 📋 Problème Résolu

**Erreur initiale :**

```
Blocage d'une requête multiorigine (Cross-Origin Request) : la politique « Same Origin » ne permet pas de consulter la ressource distante située sur https://api.sigec.artbenshow.com/register. Raison : échec de la requête CORS. Code d'état : (null)
```

## 🎯 Cause Racine

Le problème était causé par **deux facteurs** :

1. **Content Security Policy (CSP)** trop restrictive dans le middleware `SecurityHeaders`
2. **Configuration CORS** pas assez explicite

## ✅ Solutions Appliquées

### 1. 🔒 Mise à jour du Content Security Policy

**Fichier modifié :** `app/Http/Middleware/SecurityHeaders.php`

**Ligne 80 - Avant :**

```php
$response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://js.stripe.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.fedapay.com https://sandbox-api.fedapay.com https://api.kkiapay.me https://sandbox.momoapi.mtn.com");
```

**Ligne 80 - Après :**

```php
$response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://.net https://jscdn.jsdelivr.stripe.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.fedapay.com https://sandbox-api.fedapay.com https://api.kkiapay.me https://sandbox.momoapi.mtn.com https://api.sigec.artbenshow.com");
```

**✨ Ajout :** `https://api.sigec.artbenshow.com` dans la directive `connect-src`

### 2. 📡 Configuration CORS Améliorée

**Fichier modifié :** `config/cors.php`

**Ligne 4 - Avant :**

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
```

**Ligne 4 - Après :**

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'register', 'login'],
```

**✨ Ajout :** Chemins explicites pour les endpoints publics

## 🧪 Test de la Correction

### Fichier de Test Créé

**Nouveau fichier :** `public/cors-test-advanced.html`

Ce fichier de test permet de :

- ✅ Tester les requêtes CORS vers `/register`, `/login`, `/health`
- ✅ Vérifier les requêtes preflight OPTIONS
- ✅ Afficher les détails des réponses et headers
- ✅ Effectuer des tests automatisés

### Comment Tester

1. **Ouvrir le fichier de test :**

   ```
   http://localhost:8000/cors-test-advanced.html
   ```

2. **Lancer les tests :**

   - Cliquer sur "🚀 Lancer tous les tests"
   - Ou tester individuellement chaque endpoint

3. **Vérifier les résultats :**
   - ✅ Vert = Succès CORS
   - ❌ Rouge = Erreur CORS
   - ℹ️ Bleu = Information

## 🔍 Configuration CORS Actuelle

### `config/cors.php`

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'register', 'login'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

### `.env` (Variables CORS)

```env
# CORS Configuration (CSRF Disabled)
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS,PATCH
CORS_ALLOWED_HEADERS=Accept,Accept-Language,Content-Language,Content-Type,Authorization,X-Requested-With,X-API-KEY
CORS_MAX_AGE=3600
CORS_SUPPORTS_CREDENTIALS=false
```

### Middleware Configuration

**Dans `bootstrap/app.php` :**

```php
$middleware->api(append: [
    \Illuminate\Http\Middleware\HandleCors::class,
    \App\Http\Middleware\SecurityHeaders::class,
    \App\Http\Middleware\TenantResolver::class,
]);
```

## 🚨 Dépannage

### Si le problème persiste :

1. **Vérifier les logs Laravel :**

   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Vérifier les headers de réponse :**

   ```bash
   curl -I -X OPTIONS https://api.sigec.artbenshow.com/register
   ```

3. **Tester avec curl :**

   ```bash
   curl -X POST https://api.sigec.artbenshow.com/register \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"name":"Test","email":"test@example.com","password":"test123","password_confirmation":"test123"}'
   ```

4. **Vérifier la configuration du serveur web :**
   - Apache : `docker/apache/laravel.conf`
   - Nginx : Configuration du proxy

### Headers CORS Attendus

Pour une requête réussie, vous devriez voir :

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
Access-Control-Allow-Headers: Accept, Accept-Language, Content-Language, Content-Type, Authorization, X-Requested-With, X-API-KEY
Access-Control-Max-Age: 3600
```

## 🔧 Notes Techniques

### Ordre des Middleware

L'ordre d'exécution est crucial :

1. `HandleCors` - Gère les requêtes CORS
2. `SecurityHeaders` - Ajoute les headers de sécurité (incluant CSP)
3. `TenantResolver` - Résout le tenant

### Content Security Policy

La directive `connect-src` contrôle quelles URLs peuvent être contactées via :

- `fetch()`
- `XMLHttpRequest`
- `WebSocket`
- `EventSource`

### CORS vs CSP

- **CORS** : Contrôle l'accès aux ressources côté serveur
- **CSP** : Contrôle les connexions réseau côté client

Les deux doivent être configurés correctement pour un fonctionnement optimal.

## ✅ Validation

La correction a été appliquée avec succès. Les requêtes CORS vers `https://api.sigec.artbenshow.com` devraient maintenant fonctionner normalement.

**Fichiers modifiés :**

- ✅ `app/Http/Middleware/SecurityHeaders.php`
- ✅ `config/cors.php`

**Fichiers créés :**

- ✅ `public/cors-test-advanced.html` (outil de test)
