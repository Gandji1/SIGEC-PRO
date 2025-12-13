# Guide Déploiement SIGEC sur VPS Linux

## 1. Préparation du Serveur

### Prérequis
- Ubuntu 22.04 LTS ou Debian 12
- Accès SSH root ou sudoer
- 2GB RAM minimum, 20GB SSD

### Installation des packages système

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git nginx supervisor postgresql postgresql-contrib redis-server

# Docker (optionnel, si déploiement containerisé)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

### Installation PHP 8.2

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-pgsql php8.2-redis php8.2-bcmath php8.2-mbstring php8.2-xml php8.2-zip

# Vérifier
php -v
```

### Installation Node.js

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Configuration PostgreSQL

```bash
# Se connecter
sudo -u postgres psql

# Créer utilisateur et BD
CREATE USER sigec_user WITH PASSWORD 'secure_password_here';
CREATE DATABASE sigec_db OWNER sigec_user;
ALTER ROLE sigec_user SET client_encoding TO 'utf8';
ALTER ROLE sigec_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE sigec_user SET default_transaction_deferrable TO on;
ALTER ROLE sigec_user SET default_transaction_isolation TO 'read committed';

# Quitter
\q
```

## 3. Déploiement Application

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/gandji1/SIGEC.git
cd SIGEC

# Permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 backend/storage bootstrap/cache

# Backend setup
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env

# Générer APP_KEY
php artisan key:generate

# Configurer .env
# Éditer vi .env pour :
# DB_HOST, DB_USERNAME, DB_PASSWORD, MAIL_FROM_ADDRESS, etc.

# Migrations
php artisan migrate --force
php artisan db:seed --force

# Frontend build
cd ../frontend
npm install
npm run build

# Copier dist dans public
cp -r dist/* ../backend/public/
```

## 4. Configuration Nginx

Créer `/etc/nginx/sites-available/sigec` :

```nginx
upstream php_backend {
    server unix:/run/php/php8.2-fpm.sock;
}

server {
    listen 80;
    listen [::]:80;
    server_name sigec.example.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name sigec.example.com;
    
    # SSL Certificates (Certbot)
    ssl_certificate /etc/letsencrypt/live/sigec.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sigec.example.com/privkey.pem;
    
    root /var/www/SIGEC/backend/public;
    index index.php;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Logs
    access_log /var/log/nginx/sigec-access.log;
    error_log /var/log/nginx/sigec-error.log;
    
    # Routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/sigec /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 5. SSL avec Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d sigec.example.com
```

## 6. Configuration Supervisor (Queue Worker)

Créer `/etc/supervisor/conf.d/sigec-worker.conf` :

```ini
[program:sigec-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/SIGEC/backend/artisan queue:work redis --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sigec-worker.log
user=www-data

[program:sigec-schedule]
process_name=%(program_name)s
command=php /var/www/SIGEC/backend/artisan schedule:work
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sigec-schedule.log
user=www-data
```

Activer:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sigec-worker:*
sudo supervisorctl start sigec-schedule
```

## 7. Configuration Cron (Backups Automatiques)

```bash
sudo crontab -e

# Ajouter :
0 2 * * * /usr/bin/php /var/www/SIGEC/backend/artisan backup:run
0 */4 * * * /usr/bin/php /var/www/SIGEC/backend/artisan schedule:run
```

## 8. Monitoring & Logging

### Gestion logs

```bash
# Rotation logs
sudo tee /etc/logrotate.d/sigec > /dev/null <<EOF
/var/log/supervisor/sigec*.log
/var/www/SIGEC/backend/storage/logs/*.log
{
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
EOF
```

### Health check

```bash
curl https://sigec.example.com/api/health
# Réponse : { "status": "ok" }
```

## 9. Variables d'Environnement Production

Éditer `/var/www/SIGEC/backend/.env` :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sigec.example.com

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=sigec_db
DB_USERNAME=sigec_user
DB_PASSWORD=your_secure_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=localhost
REDIS_PORT=6379

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_BUCKET=sigec-backups

MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com

STRIPE_PUBLIC_KEY=pk_live_xxx
STRIPE_SECRET_KEY=sk_live_xxx
```

## 10. Troubleshooting

### Vérifier logs

```bash
# Nginx
sudo tail -f /var/log/nginx/sigec-error.log

# PHP-FPM
sudo tail -f /var/log/php8.2-fpm.log

# Laravel
tail -f /var/www/SIGEC/backend/storage/logs/laravel.log

# Supervisor
sudo tail -f /var/log/supervisor/sigec-worker.log
```

### Permissions

```bash
sudo chown -R www-data:www-data /var/www/SIGEC
sudo chmod -R 755 /var/www/SIGEC/backend/storage bootstrap/cache
```

### Redémarrer Services

```bash
sudo systemctl restart nginx php8.2-fpm redis-server postgresql
sudo supervisorctl restart all
```

---

**Documentation** : [Production Deployment Guide](./deployment-prod.md)
