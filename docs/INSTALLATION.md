# Guide Installation SIGEC - Développement Local

## 1. Prérequis

### Système Requis
- Windows 10/11, macOS 10.15+, ou Linux (Ubuntu 20.04+)
- RAM minimum: 8GB (16GB recommandé pour confort)
- Disque: 50GB SSD
- Port disponibles: 8000, 5173, 5432, 6379, 5050

### Installation Préalable

#### Docker & Docker Compose

**Windows/macOS:**
1. Télécharger [Docker Desktop](https://www.docker.com/products/docker-desktop)
2. Installer et redémarrer
3. Vérifier:
```bash
docker --version
docker-compose --version
```

**Linux (Ubuntu/Debian):**
```bash
# Installation Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
newgrp docker

# Installation Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Vérifier
docker-compose --version
```

#### Git

**Windows:**
Télécharger [Git for Windows](https://git-scm.com/download/win)

**macOS:**
```bash
brew install git
```

**Linux:**
```bash
sudo apt install -y git
```

#### Node.js (optionnel, si développement frontend sans Docker)

**Tous OS:**
Télécharger depuis [nodejs.org](https://nodejs.org) (LTS 20.x)

Ou via package manager:
```bash
# macOS
brew install node

# Linux
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Windows (WSL2)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install nodejs
```

## 2. Installation SIGEC

### Clone Repository

```bash
# SSH (si clé SSH configurée)
git clone git@github.com:gandji1/SIGEC.git
cd SIGEC

# Ou HTTPS
git clone https://github.com/gandji1/SIGEC.git
cd SIGEC
```

### Configuration Fichiers

```bash
# Copier fichiers d'environnement
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local

# Éditer .env backend (optionnel pour dev)
nano backend/.env

# Éditer .env frontend (optionnel)
nano frontend/.env.local
```

### Build & Démarrage

**Opción 1: Docker Compose (Recommandé)**

```bash
# Démarrer tous services
docker-compose up -d

# Vérifier status
docker-compose ps

# Afficher logs
docker-compose logs -f

# Attendre que PostgreSQL soit prêt (~10-15 sec)
# Les migrations s'exécutent automatiquement
```

**Option 2: Installation Locale (Sans Docker)**

#### Backend
```bash
cd backend

# PHP (vérifier version)
php -v  # Doit être >= 8.2

# Composer
composer install

# Configuration
cp .env.example .env
php artisan key:generate

# Base de données
# S'assurer PostgreSQL est installé localement
php artisan migrate --seed

# Démarrer serveur
php artisan serve --port=8000
```

#### Frontend
```bash
cd frontend

# Node.js
node -v  # Doit être >= 18

# Installation dépendances
npm install

# Développement
npm run dev

# Ou build production
npm run build
```

## 3. Vérifier Installation

### Accès Services

| Service | URL | Credentials |
|---------|-----|-------------|
| App Frontend | http://localhost:5173 | - |
| API Backend | http://localhost:8000 | - |
| pgAdmin | http://localhost:5050 | admin@sigec.local / admin |
| Redis | localhost:6379 | - |
| PostgreSQL | localhost:5432 | sigec_user / password |

### Health Checks

```bash
# Frontend
curl http://localhost:5173

# Backend
curl http://localhost:8000
curl http://localhost:8000/api/health

# Base de données
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "SELECT 1"

# Redis
docker-compose exec redis redis-cli ping
```

### Connexion Application

1. Ouvrir **http://localhost:5173**
2. Login avec:
   - **Email**: `admin@sigec.local`
   - **Mot de passe**: `password`
3. Parcourir l'application

## 4. Commandes Développement Courantes

### Docker

```bash
# Démarrer services
docker-compose up -d

# Arrêter services
docker-compose down

# Voir logs
docker-compose logs -f [service_name]

# Exécuter commande dans conteneur
docker-compose exec app php artisan [command]
docker-compose exec frontend npm [command]

# Rebuild images
docker-compose build --no-cache
```

### Backend (Laravel)

```bash
# Migrations
docker-compose exec app php artisan migrate
docker-compose exec app php artisan migrate:fresh --seed  # Reset

# Artisan commands
docker-compose exec app php artisan list
docker-compose exec app php artisan tinker  # REPL PHP

# Cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache

# Queue
docker-compose exec app php artisan queue:work
```

### Frontend (React)

```bash
# Développement
docker-compose exec frontend npm run dev

# Build
docker-compose exec frontend npm run build

# Lint
docker-compose exec frontend npm run lint

# Tests
docker-compose exec frontend npm test
```

### Base de Données

```bash
# Connexion PostgreSQL
docker-compose exec postgres psql -U sigec_user -d sigec_db

# Backup
docker-compose exec postgres pg_dump -U sigec_user sigec_db > backup.sql

# Restore
docker-compose exec postgres psql -U sigec_user sigec_db < backup.sql

# Vider DB (⚠️ Perte données)
docker-compose exec postgres psql -U sigec_user -d sigec_db -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
```

## 5. Résoudre Problèmes Courants

### Docker ne démarre pas

```bash
# Vérifier Docker daemon
docker ps

# Vérifier ports utilisés
# Windows
netstat -ano | findstr :8000

# macOS/Linux
lsof -i :8000

# Libérer port (ou utiliser port différent)
docker-compose down
```

### Erreur: "Cannot connect to PostgreSQL"

```bash
# Attendre que PostgreSQL démarre (health check)
docker-compose ps postgres

# Voir logs
docker-compose logs postgres

# Redémarrer PostgreSQL
docker-compose restart postgres
docker-compose exec app php artisan migrate
```

### Node/npm version incorrecte

```bash
# Vérifier version
node -v
npm -v

# Déinstaller et réinstaller
# macOS
brew uninstall node
brew install node@20

# Ou utiliser NVM (Node Version Manager)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install 20
nvm use 20
```

### Permisisions fichiers sur Linux

```bash
# Donner permissions
sudo chown -R $USER:$USER .
sudo chmod -R u+w backend/storage frontend/node_modules

# Ou utiliser Docker (recommandé)
docker-compose exec app chown -R www-data:www-data .
```

## 6. Configuration IDE/Editor

### VS Code

Extensions recommandées:
- Docker
- PHP Intelephense
- ES7+ React/Redux/React-Native snippets
- Tailwind CSS IntelliSense
- PostgreSQL
- REST Client

Fichier `.vscode/settings.json`:
```json
{
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  },
  "phpstan.enabled": false,
  "editor.formatOnSave": true
}
```

### PHPStorm/WebStorm

1. Configurer Docker as Runtime
   - Settings → Docker → Engine (Docker Desktop)
   
2. Configurer PHP Interpreter
   - Languages & Frameworks → PHP → CLI Interpreter → From Docker

3. Configurer Database
   - Database → New → PostgreSQL
   - Host: localhost, Port: 5432, User: sigec_user

## 7. Tests & Quality Assurance

### PHPUnit (Backend)

```bash
# Exécuter tests
docker-compose exec app php artisan test

# Avec rapport coverage
docker-compose exec app php artisan test --coverage

# Tests spécifiques
docker-compose exec app php artisan test --filter TestName
```

### Jest (Frontend)

```bash
# Exécuter tests
docker-compose exec frontend npm test

# Mode watch
docker-compose exec frontend npm test -- --watch

# Coverage
docker-compose exec frontend npm test -- --coverage
```

### Linting & Code Quality

```bash
# Backend (Pint - Laravel formatter)
docker-compose exec app php artisan pint

# Frontend (ESLint)
docker-compose exec frontend npm run lint

# Frontend (Prettier)
docker-compose exec frontend npm run format
```

## 8. Workflow Recommandé

### Créer Feature Branch

```bash
git checkout -b feature/nouvelle-fonctionnalite
```

### Développer

```bash
# Backend
docker-compose exec app php artisan make:controller NouveauController
docker-compose exec app php artisan make:migration create_nouvelles_tables

# Frontend
docker-compose exec frontend npm run dev
```

### Tester

```bash
# Backend
docker-compose exec app php artisan test

# Frontend
docker-compose exec frontend npm test

# Manual
# Ouvrir http://localhost:5173 et tester
```

### Commit & Push

```bash
git add .
git commit -m "feat: description changements"
git push origin feature/nouvelle-fonctionnalite
```

### Créer Pull Request sur GitHub

## 9. Build pour Production

### Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

### Frontend

```bash
cd frontend
npm install --production
npm run build
```

### Docker

```bash
# Build images production
docker-compose build --no-cache

# Lancer avec compose
docker-compose -f docker-compose.yml up -d
```

## 10. Documentation Utile

- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [React Docs](https://react.dev)
- [Docker Compose Docs](https://docs.docker.com/compose)
- [PostgreSQL Docs](https://www.postgresql.org/docs)
- [Tailwind CSS](https://tailwindcss.com/docs)

---

**Besoin d'aide?**
- 📖 Lire [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
- 💬 Contacter l'équipe DevOps
- 🐛 Ouvrir issue sur GitHub
