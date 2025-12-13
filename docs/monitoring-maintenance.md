# Guide Monitoring & Maintenance SIGEC

## 1. Monitoring en Temps Réel

### Dashboard Système

```bash
# Installation (Ubuntu)
sudo apt install -y htop iotop nethogs

# Monitoring CPU/RAM/Disque
htop

# Monitoring I/O Disque
sudo iotop

# Monitoring réseau
sudo nethogs
```

### Vérifier Services

```bash
# Status services
systemctl status nginx
systemctl status php8.2-fpm
systemctl status postgresql
systemctl status redis-server
sudo supervisorctl status

# Logs temps réel
sudo journalctl -u nginx -f
sudo journalctl -u php8.2-fpm -f
tail -f /var/www/SIGEC/backend/storage/logs/laravel.log
```

## 2. Monitoring Performance Base de Données

### Requêtes lentes

```sql
-- Connexion PostgreSQL
sudo -u postgres psql -d sigec_db

-- Activer query logging
ALTER SYSTEM SET log_statement = 'all';
ALTER SYSTEM SET log_min_duration_statement = 1000; -- ms
SELECT pg_reload_conf();

-- Voir requêtes en cours
SELECT pid, usename, state, query 
FROM pg_stat_activity 
WHERE state != 'idle';

-- Voir taille tables
SELECT schemaname, tablename, 
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Voir index unused
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(relid) DESC;
```

### Optimisation

```sql
-- Vacuum (nettoyer espace)
VACUUM ANALYZE;

-- Reindex (reconstruire index)
REINDEX TABLE products;
REINDEX TABLE stocks;
REINDEX TABLE sales;

-- Check intégrité
ANALYZE;
```

## 3. Monitoring Redis

```bash
# Connexion redis-cli
redis-cli

# Stats
INFO
INFO memory
INFO stats

# Voir toutes clés (dangereux en prod!)
KEYS *

# Taille base redis
DBSIZE

# Monitor requêtes
MONITOR

# Vider cache (si nécessaire)
FLUSHDB
FLUSHALL  # Danger: vide TOUT
```

## 4. Monitoring Disque

```bash
# Espace utilisé
df -h

# Détail par dossier
sudo du -sh /var/www/SIGEC/*
sudo du -sh /var/www/SIGEC/backend/storage/*

# Fichiers volumineux
find /var/www/SIGEC -type f -size +100M

# Logs volumineux
find /var/log -type f -size +100M
```

## 5. Monitoring Application Laravel

### Health Check

```bash
# Endpoint santé
curl https://sigec.example.com/api/health

# Response attendue
{
  "status": "ok",
  "version": "1.0.0",
  "database": "ok",
  "redis": "ok",
  "environment": "production"
}
```

### Commandes Artisan

```bash
# Cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue
php artisan queue:work --queue=default,high --tries=3
php artisan queue:failed
php artisan queue:retry all

# Storage
php artisan storage:link

# Database
php artisan migrate:status
php artisan model:prune  # Supprimer soft-deleted
```

## 6. Monitoring Frontend

### Vérifier assets

```bash
# Taille bundle
cd /var/www/SIGEC/frontend
npm run build -- --report

# Performance
curl -s -I https://sigec.example.com/index.html | grep -i cache

# Analyser JS
npm run analyze  # ou webpack-bundle-analyzer
```

## 7. Alertes & Notifications

### Configuration Alertes Disk

```bash
# Script alerte disque
#!/bin/bash
THRESHOLD=80
USAGE=$(df /var/www | tail -1 | awk '{print $5}' | sed 's/%//')

if [ $USAGE -gt $THRESHOLD ]; then
    echo "Espace disque critique: ${USAGE}%" | \
    mail -s "ALERTE SIGEC: Disque à ${USAGE}%" admin@sigec.local
fi

# Cron job (vérifier toutes les heures)
0 * * * * /usr/local/bin/check_disk_space.sh
```

### Alertes Service Down

```bash
# Script
#!/bin/bash
for service in nginx php8.2-fpm postgresql redis-server; do
    if ! systemctl is-active --quiet $service; then
        echo "$service est DOWN!" | \
        mail -s "ALERTE SIGEC: $service DOWN" admin@sigec.local
    fi
done

# Cron job (toutes les 5 min)
*/5 * * * * /usr/local/bin/check_services.sh
```

## 8. Maintenance Régulière

### Daily (Quotidien)

```bash
# Vérifier logs erreurs
grep ERROR /var/www/SIGEC/backend/storage/logs/laravel.log

# Vérifier queue
php artisan queue:failed
php artisan queue:retry all

# Backup (via cron)
0 2 * * * /usr/local/bin/backup_restore.sh backup
```

### Weekly (Hebdomadaire)

```bash
# Nettoyer logs
find /var/log -name "*.log" -mtime +7 -delete

# Vacuum PostgreSQL
sudo -u postgres vacuumdb sigec_db

# Reindex importante tables
sudo -u postgres psql -d sigec_db -c "REINDEX TABLE products;"
sudo -u postgres psql -d sigec_db -c "REINDEX TABLE stocks;"

# Vérifier intégrité données
php artisan schedule:work --once
```

### Monthly (Mensuel)

```bash
# Archiver anciens logs
tar -czf /backups/logs_$(date +%Y%m).tar.gz /var/www/SIGEC/backend/storage/logs/

# Nettoyer cache assets
rm -rf /var/www/SIGEC/backend/bootstrap/cache/*

# Rebuild indexes
sudo -u postgres psql -d sigec_db -c "REINDEX DATABASE sigec_db;"

# Backup complet (vérifier intégrité)
./backup_restore.sh backup && ./backup_restore.sh verify <latest_backup>

# Test restauration backup (sur serveur test!)
```

## 9. Problèmes Courants & Solutions

### Problème: Requête PostgreSQL lente

```bash
# 1. Vérifier plan exécution
EXPLAIN ANALYZE SELECT * FROM products WHERE category_id = 1;

# 2. Ajouter index si nécessaire
CREATE INDEX idx_products_category ON products(category_id);

# 3. Analyser table
ANALYZE products;

# 4. Vérifier statistiques
SELECT * FROM pg_stat_user_tables;
```

### Problème: Mémoire RAM saturée

```bash
# 1. Vérifier processus RAM
ps aux --sort=-%mem | head -10

# 2. Vérifier cache Redis
redis-cli INFO memory

# 3. Réduire pool PHP-FPM (si nécessaire)
# /etc/php/8.2/fpm/pool.d/www.conf
# pm.max_children = 20  # réduire si RAM saturée

# 4. Nettoyer cache Laravel
php artisan cache:clear
```

### Problème: Disque plein

```bash
# 1. Identifier gros fichiers
find / -type f -size +500M

# 2. Nettoyer logs
find /var/log -name "*.log" -delete

# 3. Nettoyer storage Laravel
rm -rf /var/www/SIGEC/backend/storage/logs/*
rm -rf /var/www/SIGEC/backend/bootstrap/cache/*

# 4. Compresser anciens logs
gzip /var/www/SIGEC/backend/storage/logs/*.log
```

### Problème: Queue Laravel en retard

```bash
# 1. Vérifier queue
php artisan queue:failed
sudo supervisorctl status sigec-worker

# 2. Retenter jobs échoués
php artisan queue:retry all

# 3. Augmenter workers (si besoin)
# /etc/supervisor/conf.d/sigec-worker.conf
# numprocs=8  # augmenter

# 4. Redémarrer
sudo supervisorctl restart sigec-worker:*
```

### Problème: Certificat SSL expire bientôt

```bash
# 1. Vérifier expiration
echo | openssl s_client -servername sigec.example.com -connect sigec.example.com:443 2>/dev/null | openssl x509 -noout -dates

# 2. Renouveler
sudo certbot renew --force-renewal

# 3. Automatique (Cron)
0 0 * * * /usr/bin/certbot renew --quiet
```

## 10. Monitoring Avancé (Prometheus + Grafana)

### Installation

```bash
# Prometheus
docker run -d --name prometheus -p 9090:9090 \
  -v /etc/prometheus:/etc/prometheus \
  prom/prometheus --config.file=/etc/prometheus/prometheus.yml

# Grafana
docker run -d --name grafana -p 3000:3000 \
  -e GF_SECURITY_ADMIN_PASSWORD=admin \
  grafana/grafana
```

### Configuration `/etc/prometheus/prometheus.yml`

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'sigec'
    static_configs:
      - targets: ['localhost:9090']
```

---

**Contacts d'urgence**:
- DevOps: devops@sigec.local
- DB Admin: dbadmin@sigec.local
- Support: support@sigec.local
