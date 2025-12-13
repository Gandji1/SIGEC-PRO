# 🚀 Quick Start Guide SIGEC

## 30 secondes pour démarrer

```bash
# 1. Cloner
git clone https://github.com/gandji1/SIGEC.git && cd SIGEC

# 2. Lancer
docker-compose up -d

# 3. Ouvrir navigateur
# http://localhost:5173
```

**Login**: admin@sigec.local / password

---

## 📖 Guides Complets

### Pour les Développeurs
- 👨‍💻 **Local Dev Setup**: [INSTALLATION.md](./docs/INSTALLATION.md)
- 🐛 **Troubleshooting**: [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md)
- 🤝 **Contribution**: [CONTRIBUTING.md](./CONTRIBUTING.md)

### Pour DevOps/SRE
- 🚀 **Production Deploy**: [deployment-vps.md](./docs/deployment-vps.md)
- 🔒 **Security**: [security.md](./docs/security.md)
- 📊 **Monitoring**: [monitoring-maintenance.md](./docs/monitoring-maintenance.md)

### Documentation Technique
- 📋 **Specs**: [TdR.md](./docs/TdR.md)
- 📚 **API**: [swagger.yaml](./docs/swagger.yaml) (coming soon)
- 🏗️ **Architecture**: [architecture.md](./docs/architecture.md) (coming soon)

---

## 🎯 Cas d'Utilisation Communs

### Je veux... développer une nouvelle feature

```bash
# 1. Branch
git checkout -b feature/ma-feature

# 2. Développer
docker-compose up -d
# Éditer code...

# 3. Tester
docker-compose exec app php artisan test
docker-compose exec frontend npm test

# 4. Commit
git commit -m "feat: description"
git push origin feature/ma-feature

# 5. PR sur GitHub
```

### Je veux... déployer sur un VPS

```bash
# 1. Préparer serveur Ubuntu 22.04
# 2. Cloner repository
# 3. Exécuter script deploy
./scripts/deploy.sh

# 4. Voir docs complètes
cat docs/deployment-vps.md
```

### Je veux... faire backup/restore

```bash
# Backup
./scripts/backup_restore.sh backup

# Lister
./scripts/backup_restore.sh list

# Restaurer
./scripts/backup_restore.sh restore /path/to/backup.tar.gz
```

### Je veux... modifier schema BD

```bash
# Créer migration
docker-compose exec app php artisan make:migration create_table_name

# Éditer migration
# Migrer
docker-compose exec app php artisan migrate

# Rollback si erreur
docker-compose exec app php artisan migrate:rollback
```

### Je veux... créer nouvel utilisateur

```bash
docker-compose exec app php artisan tinker
>>> User::create([
>>>     'name' => 'John Doe',
>>>     'email' => 'john@example.com',
>>>     'password' => Hash::make('password'),
>>>     'tenant_id' => 1,
>>> ]);
```

---

## 🔧 Commandes Utiles

### Docker

```bash
docker-compose up -d          # Démarrer
docker-compose down           # Arrêter
docker-compose logs -f        # Logs
docker-compose ps             # Status
docker-compose restart        # Redémarrer
```

### Backend

```bash
docker-compose exec app php artisan list
docker-compose exec app php artisan migrate
docker-compose exec app php artisan tinker
docker-compose exec app php artisan test
```

### Frontend

```bash
docker-compose exec frontend npm install
docker-compose exec frontend npm run dev
docker-compose exec frontend npm run build
docker-compose exec frontend npm test
```

### Database

```bash
# PostgreSQL
docker-compose exec postgres psql -U sigec_user -d sigec_db

# Redis
docker-compose exec redis redis-cli
```

---

## 📋 Vérifier Installation

```bash
✅ Frontend: http://localhost:5173
✅ API: http://localhost:8000/api/health
✅ pgAdmin: http://localhost:5050
✅ Tests: docker-compose exec app php artisan test
```

---

## 🆘 Problèmes?

| Problème | Solution |
|----------|----------|
| Port déjà utilisé | `sudo lsof -i :8000` & tuer process |
| DB connection fail | `docker-compose logs postgres` |
| npm error | `rm -rf node_modules && npm install` |
| Tout cassé | `docker-compose down -v && docker-compose up -d` |

**Plus**: [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md)

---

## 📞 Support

- 💬 Discord: [Rejoindre](https://discord.gg/sigec)
- 📧 Email: support@sigec.local
- 🐛 Issues: [GitHub Issues](https://github.com/gandji1/SIGEC/issues)
- 📚 Docs: [docs/](./docs/)

---

**Prêt?** Commencez avec `docker-compose up -d` 🎉
