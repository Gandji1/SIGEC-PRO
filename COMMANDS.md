#!/bin/bash

# SIGEC - Commandes Utiles

# ========================================
# 🚀 DÉMARRAGE & ARRÊT
# ========================================

# Démarrer tous les services
docker-compose up -d

# Arrêter services
docker-compose down

# Redémarrer tout
docker-compose restart

# Voir status services
docker-compose ps

# ========================================
# 📋 LOGS
# ========================================

# Logs backend en temps réel
docker-compose logs -f app

# Logs frontend
docker-compose logs -f frontend

# Logs PostgreSQL
docker-compose logs -f postgres

# Logs Redis
docker-compose logs -f redis

# Tous logs
docker-compose logs -f

# ========================================
# 💻 BACKEND (Laravel)
# ========================================

# Exécuter artisan command
docker-compose exec app php artisan <command>

# Exemples:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan migrate:fresh --seed
docker-compose exec app php artisan test
docker-compose exec app php artisan tinker
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache

# Composer
docker-compose exec app composer install
docker-compose exec app composer require package/name

# ========================================
# 🎨 FRONTEND (React)
# ========================================

# Voir dev server
docker-compose logs -f frontend

# Installer dépendances
docker-compose exec frontend npm install

# Développement (dev server)
docker-compose exec frontend npm run dev

# Build production
docker-compose exec frontend npm run build

# Linting
docker-compose exec frontend npm run lint

# Tests
docker-compose exec frontend npm test

# ========================================
# 🗄️ DATABASE (PostgreSQL)
# ========================================

# Connexion PostgreSQL CLI
docker-compose exec postgres psql -U sigec_user -d sigec_db

# Dump base de données
docker-compose exec postgres pg_dump -U sigec_user sigec_db > backup.sql

# Restore base de données
docker-compose exec postgres psql -U sigec_user sigec_db < backup.sql

# Utile:
# \l                          # List databases
# \c sigec_db                 # Connect database
# \dt                         # List tables
# \d table_name               # Describe table
# SELECT * FROM users;        # Query
# \q                          # Quit

# ========================================
# 📦 REDIS
# ========================================

# Connexion Redis CLI
docker-compose exec redis redis-cli

# Utile:
# PING                        # Test connection
# INFO                        # Server info
# DBSIZE                      # Database size
# FLUSHDB                     # Clear current DB (DANGER!)
# KEYS *                      # List all keys
# GET key                     # Get value
# DEL key                     # Delete key

# ========================================
# 🧪 TESTS
# ========================================

# Tests Backend (PHPUnit)
docker-compose exec app php artisan test

# Tests Frontend (Jest)
docker-compose exec frontend npm test

# Avec coverage
docker-compose exec app php artisan test --coverage
docker-compose exec frontend npm test -- --coverage

# ========================================
# 🚀 DEPLOYMENT
# ========================================

# Linux/macOS
./scripts/deploy.sh

# Windows PowerShell
./scripts/deploy.ps1

# ========================================
# 💾 BACKUPS
# ========================================

# Créer backup
./scripts/backup_restore.sh backup

# Lister backups
./scripts/backup_restore.sh list

# Restaurer backup
./scripts/backup_restore.sh restore /path/to/backup.tar.gz

# Vérifier intégrité
./scripts/backup_restore.sh verify /path/to/backup.tar.gz

# ========================================
# 🐛 DEBUGGING
# ========================================

# Exec bash dans conteneur app
docker-compose exec app bash

# Exec bash frontend
docker-compose exec frontend bash

# Voir les processus
docker-compose exec app ps aux

# Vérifier utilisation ressources
docker stats

# ========================================
# 🏥 HEALTH CHECK
# ========================================

# Frontend
curl http://localhost:5173

# API
curl http://localhost:8000/api/health

# PostgreSQL
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "SELECT 1"

# Redis
docker-compose exec redis redis-cli ping

# ========================================
# 🧹 MAINTENANCE
# ========================================

# Nettoyer images/conteneurs inutilisés
docker system prune -a

# Nettoyer volumes
docker volume prune

# Clear cache Laravel
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear

# Vacuum PostgreSQL
docker-compose exec postgres vacuumdb -U sigec_user -d sigec_db

# ========================================
# 🔧 BUILD & REBUILD
# ========================================

# Rebuild images
docker-compose build

# Rebuild sans cache
docker-compose build --no-cache

# Up avec rebuild
docker-compose up -d --build

# ========================================
# 📊 MIGRATION & SEEDING
# ========================================

# Créer migration
docker-compose exec app php artisan make:migration create_table_name

# Exécuter migrations
docker-compose exec app php artisan migrate

# Rollback dernière migration
docker-compose exec app php artisan migrate:rollback

# Rollback tout
docker-compose exec app php artisan migrate:reset

# Fresh install (drop + migrate + seed)
docker-compose exec app php artisan migrate:fresh --seed

# ========================================
# 👤 USER MANAGEMENT
# ========================================

# Créer utilisateur via Tinker
docker-compose exec app php artisan tinker
# >>> User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => Hash::make('password'), 'tenant_id' => 1])

# Réinitialiser mot de passe
# >>> $user = User::find(1);
# >>> $user->password = Hash::make('nouveau_password');
# >>> $user->save();

# ========================================
# 🔍 TROUBLESHOOTING
# ========================================

# Port déjà utilisé
sudo lsof -i :8000          # Voir process
kill -9 <PID>               # Tuer process

# Impossible de se connecter BD
docker-compose logs postgres
docker-compose restart postgres

# Conteneur crash
docker-compose logs app
docker-compose restart app

# Volumes corrompus
docker-compose down -v      # Supprimer volumes
docker-compose up -d        # Recréer

# ========================================
# 📁 FICHIERS IMPORTANTS
# ========================================

# Configuration:
# backend/.env                    # Config backend
# frontend/.env.local             # Config frontend
# docker-compose.yml              # Docker orchestration

# Code:
# backend/app/Domains/            # Backend domains
# frontend/src/                    # Frontend code

# Docs:
# docs/INSTALLATION.md            # Installation
# docs/TROUBLESHOOTING.md         # Problèmes
# docs/security.md                # Sécurité
# docs/deployment-vps.md          # Production

# ========================================
# 💡 TIPS
# ========================================

# Ajouter alias bash (ajouter à ~/.bashrc):
# alias sigec-logs='docker-compose -f ~/SIGEC/docker-compose.yml logs -f'
# alias sigec-artisan='docker-compose -f ~/SIGEC/docker-compose.yml exec app php artisan'

# Puis:
# sigec-logs app                # Voir logs app
# sigec-artisan migrate         # Exécuter migration

# ========================================

echo "SIGEC Commands Ready! 🚀"
echo ""
echo "Exemples:"
echo "  docker-compose up -d"
echo "  docker-compose logs -f app"
echo "  docker-compose exec app php artisan test"
