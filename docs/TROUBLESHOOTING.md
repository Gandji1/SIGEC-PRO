# Guide Troubleshooting SIGEC

## 1. Problèmes Démarrage

### Docker Compose ne démarre pas

```bash
# Vérifier status
docker-compose ps

# Vérifier logs
docker-compose logs app
docker-compose logs postgres
docker-compose logs redis

# Solutions communes
# 1. Port déjà utilisé
sudo lsof -i :8000  # Laravel
sudo lsof -i :5173  # Frontend
sudo lsof -i :5432  # PostgreSQL

# 2. Réinitialiser volumes
docker-compose down -v
docker-compose up -d

# 3. Rebuild images
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Migrations échouent

```bash
# Vérifier erreur
docker-compose logs app

# Solutions
# 1. Vérifier connexion BD
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();

# 2. Réinitialiser BD
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker-compose exec app php artisan migrate:fresh --seed

# 3. Vérifier fichier migration
ls -la backend/database/migrations/
```

### Application plantée au démarrage

```bash
# Vérifier logs
docker-compose logs app | tail -50

# Vérifier config
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear

# Test tinker
docker-compose exec app php artisan tinker
```

## 2. Problèmes Base de Données

### Connexion PostgreSQL refuse

```bash
# Vérifier PostgreSQL démarre
docker-compose ps postgres

# Vérifier logs
docker-compose logs postgres

# Vérifier credentials
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "SELECT 1"

# Réinitialiser conteneur
docker-compose down postgres
docker-compose up -d postgres
```

### Performances lentes requêtes

```bash
# 1. Identifier requête lente
docker-compose exec app php artisan tinker
>>> DB::enableQueryLog();
>>> App\Models\Product::where('category_id', 1)->get();
>>> dd(DB::getQueryLog());

# 2. Vérifier index existence
docker-compose exec postgres psql -U sigec_user -d sigec_db << SQL
SELECT indexname FROM pg_indexes WHERE tablename = 'products';
SQL

# 3. Ajouter index manquant
docker-compose exec app php artisan tinker
>>> Artisan::call('migrate');

# 4. Analyser table
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "ANALYZE products;"
```

### Espace disque plein

```bash
# Vérifier utilisation
docker system df

# Nettoyer
docker system prune -a  # Supprimer images/conteneurs non utilisés

# Nettoyer volumes
docker volume prune

# Vérifier logs volumineux
docker-compose exec app du -sh storage/logs/
docker-compose exec app rm -f storage/logs/laravel.log.*
```

## 3. Problèmes API

### 401 Unauthorized

```bash
# Solutions possibles:
# 1. Token absent
curl -H "Authorization: Bearer $TOKEN" https://api.sigec.example.com/api/products

# 2. Token expiré - renouveler
curl -X POST https://api.sigec.example.com/api/refresh

# 3. Token invalide - re-login
curl -X POST https://api.sigec.example.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### 403 Forbidden

```bash
# Vérifier permission utilisateur
docker-compose exec app php artisan tinker
>>> Auth::user()->getAllPermissions();
>>> Auth::user()->can('create-sales');

# Assigner permission
>>> Auth::user()->givePermissionTo('create-sales');
```

### 500 Server Error

```bash
# Vérifier logs
docker-compose logs app | tail -100
tail -f backend/storage/logs/laravel.log

# Erreurs courantes:
# - Exception MySQL
# - Model non trouvé
# - Variable non définie
```

### CORS Error (Frontend ne peut pas atteindre API)

```bash
# Vérifier config CORS
docker-compose exec app php artisan tinker
>>> config('cors');

# Éditer .env
CORS_ALLOWED_ORIGINS=["https://sigec.example.com","http://localhost:5173"]

# Redémarrer
docker-compose exec app php artisan config:clear
```

## 4. Problèmes Frontend

### npm install échoue

```bash
# Nettoyer cache npm
npm cache clean --force
rm -rf node_modules package-lock.json

# Réinstaller
npm install

# Vérifier version Node
node -v  # Doit être >= 18
npm -v   # Doit être >= 9
```

### Vite dev server ne démarre pas

```bash
# Vérifier port disponible
sudo lsof -i :5173

# Vérifier logs
docker-compose logs frontend

# Reconstruire
docker-compose exec frontend npm install
docker-compose exec frontend npm run dev
```

### Build production échoue

```bash
# Vérifier erreurs
npm run build

# Solutions
# 1. Nettoyer dist
rm -rf dist
npm run build

# 2. Vérifier types TypeScript
npm run type-check

# 3. Vérifier imports
npm run lint
```

### Styles Tailwind ne s'appliquent pas

```bash
# Vérifier fichier CSS importé
grep "@tailwind" src/index.css

# Vérifier config Tailwind
cat tailwind.config.js | grep content

# Rebuilder CSS
npm run build

# En dev
npm run dev  # Tailwind watch mode activé
```

## 5. Problèmes Performance

### Application lente

```bash
# 1. Vérifier charge serveur
docker stats

# 2. Vérifier requêtes lentes
grep "took.*ms" backend/storage/logs/laravel.log | sort -t= -k2 -nr | head

# 3. Vérifier mémoire PHP
docker-compose exec app php -r "echo ini_get('memory_limit');"

# 4. Profiler requête
docker-compose exec app php artisan tinker
>>> Debugbar::enable();  # Si Barryvdh Debugbar installé
```

### Bundle JavaScript volumineux

```bash
# Analyser taille
npm run build -- --report

# Identifier modules lourds
npm ls | sort -k2 -rn | head -20

# Solutions:
# - Code splitting
# - Lazy loading routes
# - Tree shaking non-utilisé
```

### Redis cache ne fonctionne pas

```bash
# Vérifier Redis démarre
docker-compose ps redis

# Test connexion
docker-compose exec redis redis-cli ping

# Nettoyer cache
docker-compose exec app php artisan cache:clear
docker-compose exec redis redis-cli FLUSHDB

# Vérifier config
docker-compose exec app php artisan tinker
>>> Cache::get('test');
```

## 6. Problèmes Déploiement

### Docker build échoue

```bash
# Vérifier Dockerfile
docker build -t sigec:latest backend/

# Voir erreur détaillée
docker build --progress=plain -t sigec:latest backend/

# Solutions courantes:
# - Dépendances PHP manquantes
# - Version Node incompatible
# - Fichier .dockerignore incorrect
```

### Application crash après déploiement

```bash
# Vérifier health check
curl https://sigec.example.com/api/health

# Vérifier logs
ssh user@server "tail -f /var/www/SIGEC/backend/storage/logs/laravel.log"

# Vérifier variables d'env
ssh user@server "grep APP_DEBUG=/var/www/SIGEC/backend/.env"

# Rollback si nécessaire
ssh user@server "cd /var/www/SIGEC && git revert HEAD"
```

### SSL/HTTPS ne fonctionne pas

```bash
# Vérifier certificat
curl -vI https://sigec.example.com

# Vérifier expiration
echo | openssl s_client -servername sigec.example.com -connect sigec.example.com:443 | openssl x509 -noout -dates

# Renouveler si expiré
sudo certbot renew --force-renewal

# Test local HTTP
curl http://localhost
```

## 7. Problèmes de Données

### Données corrompues

```bash
# Backup avant correction
./backup_restore.sh backup

# Vérifier intégrité
docker-compose exec postgres psql -U sigec_user -d sigec_db << SQL
SELECT * FROM products WHERE price < 0;  -- Exemple
SQL

# Corriger données
docker-compose exec app php artisan tinker
>>> Product::where('price', '<', 0)->update(['price' => 0]);
```

### Synchronisation multi-tenant échouée

```bash
# Vérifier tenant_id présent
docker-compose exec app php artisan tinker
>>> Product::first()->tenant_id;

# Vérifier scope tenant
>>> Product::limit(10)->get();  # Filtre tenant_id automatiquement?

# Réinitialiser si nécessaire
docker-compose exec app php artisan migrate:fresh --seed
```

## 8. Scripts de Récupération

### Full Reset (⚠️ Perte de données)

```bash
# ⚠️ ATTENTION: Supprime TOUTES les données!
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

### Backup & Restore

```bash
# Backup complet
./scripts/backup_restore.sh backup

# Lister backups
./scripts/backup_restore.sh list

# Restaurer
./scripts/backup_restore.sh restore /path/to/backup.tar.gz

# Vérifier intégrité
./scripts/backup_restore.sh verify /path/to/backup.tar.gz
```

## 9. Contacts & Escalade

| Problème | Contact | Temps |
|----------|---------|-------|
| Crash app | Dev Team | 15 min |
| Lenteur DB | DBA | 1 heure |
| Disque plein | DevOps | 30 min |
| Sécurité | Security | 1 heure |
| Email/SMTP | Infrastructure | 2 heures |

## 10. Commandes Utiles

```bash
# Logs en temps réel
docker-compose logs -f app
docker-compose logs -f postgres
docker-compose logs -f redis

# Exécuter commande dans conteneur
docker-compose exec app php artisan command

# Tinker/REPL
docker-compose exec app php artisan tinker

# Vérifier santé service
curl http://localhost:8000/api/health

# Redémarrer tout
docker-compose restart

# Stats ressources
docker stats
docker-compose exec app ps aux
```

---

**Support 24/7**: support@sigec.local
