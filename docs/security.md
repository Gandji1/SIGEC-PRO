# Guide Sécurité SIGEC

## 1. Sécurité Réseau

### Firewall Configuration

```bash
# Installer UFW (Uncomplicated Firewall)
sudo apt install -y ufw

# Activer UFW
sudo ufw enable

# Autoriser SSH
sudo ufw allow 22/tcp

# Autoriser HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Autoriser Monitoring (local seulement)
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 9090
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 3000

# Bloquer tous autres ports par défaut
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Vérifier règles
sudo ufw status verbose
```

### SSH Hardening

```bash
# Éditer /etc/ssh/sshd_config
sudo nano /etc/ssh/sshd_config

# Configuration sécurisée :
Port 2222                          # Changer port (optionnel)
PermitRootLogin no                 # Interdire SSH root
PasswordAuthentication no           # Clés SSH uniquement
PubkeyAuthentication yes
MaxAuthTries 3
MaxSessions 5
Protocol 2
X11Forwarding no
AllowTcpForwarding no
AllowAgentForwarding no

# Redémarrer SSH
sudo systemctl restart sshd

# Tester connexion avant logout!
ssh -i ~/.ssh/id_rsa -p 2222 user@sigec.example.com
```

### Fail2Ban (Protection brute-force)

```bash
# Installation
sudo apt install -y fail2ban

# Configuration
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Paramètres importants :
[DEFAULT]
maxretry = 3
findtime = 600
bantime = 3600

[sshd]
enabled = true

[nginx-http-auth]
enabled = true

[nginx-noscript]
enabled = true

# Activer et démarrer
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Vérifier bans
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

## 2. Sécurité Application Web

### Headers de Sécurité Nginx

Ajouter dans config Nginx (`/etc/nginx/sites-available/sigec`):

```nginx
# Strict-Transport-Security (HSTS)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# Content-Security-Policy
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https://api.stripe.com;" always;

# X-Frame-Options (prévenir clickjacking)
add_header X-Frame-Options "SAMEORIGIN" always;

# X-Content-Type-Options (prévenir MIME sniffing)
add_header X-Content-Type-Options "nosniff" always;

# X-XSS-Protection
add_header X-XSS-Protection "1; mode=block" always;

# Referrer-Policy
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Permissions-Policy
add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()" always;
```

### Disable Directory Listing

```nginx
location / {
    autoindex off;  # Désactiver liste répertoires
}
```

### Limit Rate (prévenir DDoS)

```nginx
# Limiter requêtes par IP
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;

server {
    # API rate limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
    }
    
    # Login rate limiting
    location /api/login {
        limit_req zone=login burst=3 nodelay;
    }
    
    # General rate limiting
    location / {
        limit_req zone=general burst=50 nodelay;
    }
}
```

## 3. Sécurité Base de Données

### PostgreSQL Hardening

```sql
-- Connexion PostgreSQL
sudo -u postgres psql

-- 1. Audit logging
ALTER SYSTEM SET log_statement = 'all';
ALTER SYSTEM SET log_min_duration_statement = 1000;  -- Log requêtes > 1s
ALTER SYSTEM SET log_connections = on;
ALTER SYSTEM SET log_disconnections = on;

-- 2. Utilisateur application (permissions minimales)
CREATE USER sigec_app WITH PASSWORD 'secure_password' NOCREATEDB NOCREATEROLE;
GRANT CONNECT ON DATABASE sigec_db TO sigec_app;
GRANT USAGE ON SCHEMA public TO sigec_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO sigec_app;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO sigec_app;

-- 3. Connexion SSL
ALTER SYSTEM SET ssl = on;
ALTER SYSTEM SET ssl_cert_file = '/etc/postgresql/server.crt';
ALTER SYSTEM SET ssl_key_file = '/etc/postgresql/server.key';

-- 4. Redémarrer PostgreSQL
SELECT pg_reload_conf();
sudo systemctl restart postgresql
```

### Backup Chiffré

```bash
# Créer backup avec chiffrement GPG
pg_dump -h localhost -U sigec_user sigec_db | \
  gpg --symmetric --cipher-algo AES256 > sigec_backup.sql.gpg

# Restaurer backup chiffré
gpg --decrypt sigec_backup.sql.gpg | \
  psql -h localhost -U sigec_user sigec_db
```

## 4. Sécurité Authentification & Autorisation

### Laravel .env Secrets

```env
# .env (PRODUCTION)
APP_KEY=base64:xxx...  # Générer avec: php artisan key:generate
APP_DEBUG=false
APP_ENV=production

# Sanctum
SANCTUM_STATEFUL_DOMAINS=sigec.example.com
SESSION_DOMAIN=.sigec.example.com
SESSION_SECURE_COOKIES=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# CORS
CORS_ALLOWED_ORIGINS=["https://sigec.example.com"]

# JWT (si utilisé)
JWT_SECRET=your_jwt_secret_key
JWT_ALGORITHM=HS256
JWT_EXPIRATION=3600

# 2FA Secret
TWO_FACTOR_SECRET_LENGTH=32
```

### RBAC (Role-Based Access Control)

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Model {
    use HasRoles;
}

// Vérification permission dans middleware
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/sales', [SaleController::class, 'store'])
        ->middleware('permission:create-sales');
    
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])
        ->middleware('permission:delete-products');
});
```

### Audit Trail (Logger toutes modifications)

```php
// app/Traits/LogsActivity.php
trait LogsActivity {
    protected static function boot() {
        parent::boot();
        
        static::created(function ($model) {
            $model->logActivity('created');
        });
        
        static::updated(function ($model) {
            $model->logActivity('updated', $model->getDirty());
        });
        
        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }
    
    private function logActivity($action, $data = []) {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => tenant('id'),
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'action' => $action,
            'data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

## 5. Sécurité API

### API Key Protection

```php
// app/Http/Middleware/ApiKeyMiddleware.php
public function handle(Request $request, Closure $next) {
    $apiKey = $request->header('X-API-Key');
    
    if (!$apiKey) {
        return response()->json(['error' => 'API key missing'], 401);
    }
    
    $client = ApiClient::where('api_key', hash('sha256', $apiKey))
                      ->where('active', true)
                      ->first();
    
    if (!$client) {
        return response()->json(['error' => 'Invalid API key'], 401);
    }
    
    request()->merge(['api_client' => $client]);
    return $next($request);
}
```

### CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_origins' => [
        'https://sigec.example.com',
        'https://app.sigec.example.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],
    'max_age' => 3600,
    'supports_credentials' => true,
];
```

### Input Validation & Sanitization

```php
// Validation stricte
$validated = $request->validate([
    'email' => 'required|email|max:255',
    'amount' => 'required|numeric|min:0|max:999999.99',
    'description' => 'nullable|string|max:500',
    'phone' => 'nullable|phone:DZ',
]);

// Sanitization
$validated['email'] = strtolower(trim($validated['email']));
$validated['description'] = htmlspecialchars($validated['description']);

// Utiliser filtered data seulement
Product::create($validated);
```

## 6. Data Protection (RGPD)

### Chiffrement des données sensibles

```php
// app/Models/Customer.php
use Illuminate\Database\Eloquent\Casts\Encrypted;

class Customer extends Model {
    protected $casts = [
        'phone' => Encrypted::class,      // Téléphone chiffré
        'email' => Encrypted::class,      // Email chiffré
        'tax_id' => Encrypted::class,     // NIF chiffré
        'address' => Encrypted::class,    // Adresse chiffrée
    ];
}
```

### Export & Suppression Données

```php
// app/Http/Controllers/UserController.php

// Export données utilisateur (RGPD Art. 15)
public function exportData(User $user) {
    return $user->only([
        'name', 'email', 'phone', 'address',
        'orders', 'invoices', 'payments'
    ]);
}

// Suppression utilisateur (Droit à l'oubli - Art. 17)
public function deleteAccount(User $user) {
    // Supprimer données utilisateur
    $user->anonymize();
    $user->orders()->delete();
    $user->delete();
    
    return response()->json(['message' => 'Account deleted']);
}
```

## 7. Secrets Management

### Utiliser `.env` et ne PAS commiter

```bash
# .gitignore
.env
.env.local
.env.*.local
config/cache.php
config/database.php
storage/
```

### Utiliser Laravel Vault (optionnel)

```bash
# Installation
composer require laravel/vault

# Créer encrypted .env
php artisan vault:encrypt

# Éditer
php artisan vault:edit

# Décrypt automatique au démarrage
```

### AWS Secrets Manager (alternative)

```php
// Récupérer secrets d'AWS
$secretsManager = new SecretsManagerClient([
    'region' => 'us-east-1'
]);

$secret = $secretsManager->getSecretValue([
    'SecretId' => 'sigec/database-password'
]);

$dbPassword = json_decode($secret['SecretString'], true)['password'];
```

## 8. Sécurité Frontend

### Prévention XSS

```jsx
// ❌ DANGEREUX
<div dangerouslySetInnerHTML={{__html: userInput}} />

// ✅ SÛRE - React échappe automatiquement
<div>{userInput}</div>

// ✅ SÛRE - Utiliser DOMPurify si nécessaire
import DOMPurify from 'dompurify';
<div>{DOMPurify.sanitize(userInput)}</div>
```

### Protection CSRF

```jsx
// Frontend incluant token CSRF
<form onSubmit={handleSubmit}>
    <input type="hidden" name="_token" value={csrfToken} />
    {/* form fields */}
</form>
```

### Content Security Policy

```jsx
// Meta tag dans HTML
<meta http-equiv="Content-Security-Policy" 
      content="default-src 'self'; script-src 'self' 'unsafe-inline';" />
```

## 9. Incident Response

### Checklist en Cas de Fuite

```bash
# 1. Isoler l'incident
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm

# 2. Collecter logs
sudo journalctl -u nginx > /backups/nginx_logs.txt
sudo journalctl -u php8.2-fpm > /backups/php_logs.txt
tail -n 10000 /var/www/SIGEC/backend/storage/logs/laravel.log > /backups/laravel_logs.txt

# 3. Forensics
ps aux > /backups/processes.txt
netstat -tuln > /backups/network.txt

# 4. Notifier administrateurs
echo "Incident de sécurité détecté!" | mail -s "SECURITY ALERT" admin@sigec.local

# 5. Changer tous mots de passe
# - DB: ALTER USER sigec_user WITH PASSWORD 'new_password';
# - SSH keys
# - API keys
```

### Disclosure Responsable

1. Documenter vulnérabilité
2. Créer fix patch
3. Notifier utilisateurs affectés
4. Publier fix dans mise à jour
5. Annoncer incident après patch

## 10. Checklist Sécurité Pré-Production

- [ ] HTTPS/SSL configuré (certificat valide)
- [ ] SSH clés seulement (pas de password)
- [ ] Firewall actif (UFW avec règles restrictives)
- [ ] Fail2Ban activé
- [ ] PostgreSQL: utilisateur app avec permissions minimales
- [ ] Redis: authentification activée
- [ ] APP_DEBUG=false en production
- [ ] Tous secrets en variables d'environnement
- [ ] Audit logging activé (base données + application)
- [ ] Backup chiffré en place
- [ ] Monitoring & alertes configurés
- [ ] Plan incident response établi
- [ ] Tests de pénétration effectués
- [ ] Conformité RGPD vérifiée
- [ ] Assurance cyber en place

---

**Contacts Sécurité**:
- Security Officer: security@sigec.local
- Emergency: emergency@sigec.local (24/7)
- Rapporter vulnérabilité: security@sigec.local
