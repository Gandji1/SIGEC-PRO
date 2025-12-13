# ❓ FAQ - Questions Fréquemment Posées

## Installation & Configuration

### Q: Quels ports SIGEC utilise-t-il?
**A**: Par défaut:
- Frontend: 5173
- Backend API: 8000
- PostgreSQL: 5432
- Redis: 6379
- pgAdmin: 5050

Vous pouvez les changer dans `docker-compose.yml`.

### Q: Je n'ai pas Docker, comment installer?
**A**: Voir [INSTALLATION.md](./docs/INSTALLATION.md) pour installation locale (PHP, Node.js, PostgreSQL nécessaires).

### Q: Comment changer le mot de passe admin par défaut?
**A**: 
```bash
docker-compose exec app php artisan tinker
>>> $user = User::find(1);
>>> $user->password = Hash::make('nouveau_password');
>>> $user->save();
```

### Q: Puis-je utiliser MySQL au lieu de PostgreSQL?
**A**: Non recommandé. PostgreSQL est requis pour les contraintes multi-tenancy et JSON operators. Vous pouvez modifier `docker-compose.yml` mais ce n'est pas supporté.

---

## Développement

### Q: Comment déboguer l'application?
**A**: 
```bash
# Backend: Utiliser Tinker
docker-compose exec app php artisan tinker

# Frontend: Dev tools Chrome/Firefox

# Logs:
docker-compose logs -f app
```

### Q: Comment créer migration?
**A**:
```bash
docker-compose exec app php artisan make:migration create_table_name
# Éditer migration en backend/database/migrations/
docker-compose exec app php artisan migrate
```

### Q: Comment ajouter dependency PHP?
**A**:
```bash
docker-compose exec app composer require package/name
# Ou éditer backend/composer.json et faire docker-compose build
```

### Q: Comment ajouter package npm?
**A**:
```bash
docker-compose exec frontend npm install package-name
```

### Q: Les changements React ne se reflètent pas?
**A**: 
- Vérifier que dev server est running: `docker-compose logs frontend`
- Hard refresh: `Ctrl+Shift+R` (ou `Cmd+Shift+R`)
- Redémarrer: `docker-compose restart frontend`

---

## Déploiement

### Q: Comment déployer en production?
**A**: Voir [deployment-vps.md](./docs/deployment-vps.md). Résumé:
1. Préparer VPS Ubuntu 22.04
2. Exécuter `./scripts/deploy.sh`
3. Configurer DNS & SSL
4. Configurer variables d'env

### Q: Comment faire backup?
**A**:
```bash
./scripts/backup_restore.sh backup
./scripts/backup_restore.sh list    # Voir backups
./scripts/backup_restore.sh restore /path/to/backup.tar.gz
```

### Q: Docker en production, c'est ok?
**A**: Oui, Docker Compose fonctionne pour petites installations. Pour production à grande échelle:
- Utiliser Kubernetes
- AWS ECS/EKS
- Google Cloud Run
- DigitalOcean App Platform

### Q: Comment configurer SSL/HTTPS?
**A**: 
```bash
# Installation Certbot
sudo apt install certbot python3-certbot-nginx

# Générer certificat
sudo certbot certonly --nginx -d sigec.example.com

# Config nginx recharge automatiquement
```

---

## Performance & Scaling

### Q: Comment optimiser les performances?
**A**: 
1. Redis caching activé par défaut
2. Database indexing sur tenant_id, product_id
3. Lazy loading assets frontend
4. Query optimization: éviter N+1
5. CDN pour assets statiques

### Q: Comment gérer beaucoup d'utilisateurs?
**A**: 
- Augmenter workers PHP-FPM
- Ajouter replicas PostgreSQL
- Utiliser Redis cluster
- Ajouter Elasticsearch pour recherche
- Load balancer Nginx

---

## Sécurité

### Q: Est-ce que SIGEC est sûr pour production?
**A**: 
- ✅ Multi-tenancy isolée
- ✅ Authentification Sanctum + JWT
- ✅ Chiffrement AES-256
- ✅ Audit logging
- ✅ RGPD compliant
- ⚠️ Faire pentest avant production
- ⚠️ Voir [security.md](./docs/security.md)

### Q: Comment sécuriser les données clients?
**A**: 
- Chiffrement AES-256 en base
- HTTPS/SSL obligatoire
- Backup chiffré GPG
- Audit trail toutes modifications
- RBAC permissions granulaires

### Q: Que faire en cas de fuite de données?
**A**: 
1. Isoler immédiatement le serveur
2. Collecter logs pour investigation
3. Notifier administrateurs
4. Changer tous mots de passe
5. Publier security advisory

Voir [security.md - Incident Response](./docs/security.md#9-incident-response)

---

## Comptabilité & Conformité

### Q: Comment générer rapports comptables?
**A**: 
```
Dashboard → Comptabilité → Rapports
Options: Balance, Journal, Grand Livre
Export: Excel, PDF, XML
```

### Q: Export SIGEC compatible avec expert-comptable?
**A**: 
- Format XML standard
- Export par période (mois/an)
- Inclut journaux, balance, grand livre
- Formaté pour logiciels comptables courants

### Q: Comment configurer plans comptables?
**A**: 
```bash
# Backend: Configuration dans Accounting domain
# Frontend: Admin → Paramètres → Comptabilité
# Éditer comptes, journaux, devises
```

---

## Données & Sauvegarde

### Q: Où les données sont-elles stockées?
**A**: 
- PostgreSQL (données transactionnelles)
- Redis (cache temporaire)
- IndexedDB browser (POS offline)
- S3 (backups optionnel)

### Q: Combien d'espace disque est nécessaire?
**A**: 
- Base: 2GB PostgreSQL
- Logs: ~100MB/an
- Uploads: Variable (fichiers PDFs, excels)
- Backups: ~500MB/backup
- Recommandation: 50GB+ SSD

### Q: Backup automatique possible?
**A**: Oui, via cron:
```bash
0 2 * * * /usr/local/bin/backup_restore.sh backup
```

---

## Utilisateurs & Accès

### Q: Comment ajouter nouvel utilisateur?
**A**:
```bash
# Admin panel: Utilisateurs → Ajouter
# Ou via CLI:
docker-compose exec app php artisan user:create
```

### Q: Comment réinitialiser mot de passe?
**A**:
- User: Oubli password → Reset email (si SMTP config)
- Admin: Admin panel → Réinitialiser

### Q: Comment gérer les permissions?
**A**:
Admin panel → Rôles & Permissions
8 rôles: Admin, Manager, Vendeur, Caissier, Magasinier, Comptable, Auditeur, Client

### Q: Peut-on personnaliser rôles/permissions?
**A**: Oui, backend: `app/Domains/Auth/Models/Role.php`

---

## Fonctionnalités

### Q: Comment activer mode hors-ligne POS?
**A**: 
- Automatique dans app mobile
- Données sync quand connexion revient
- Voir `stores/tenantStore.js` pour logique

### Q: Puis-je utiliser codes-barres?
**A**: 
- Oui, scanner USB/Bluetooth supportés
- Codes-barres EAN-13, Code128 supportés
- Admin → Produits → Importer avec codes

### Q: Comment gérer multi-devises?
**A**: 
- Actuellement: une devise par tenant
- Futures versions: multi-devises
- Conversion manuelle possible

### Q: Puis-je intégrer Stripe?
**A**: 
- Prochainement (v1.1)
- Actuellement: paiements manuels seulement
- Roadmap: Stripe, PayPal, CIB

---

## Support & Contribution

### Q: Comment rapporter un bug?
**A**: 
1. Vérifier [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md)
2. Chercher issues existantes
3. Créer issue sur GitHub avec template
4. Fournir logs, steps, environment

### Q: Puis-je contribuer?
**A**: Bien sûr! Voir [CONTRIBUTING.md](./CONTRIBUTING.md)
- Fork repository
- Feature branch
- Tests & linting
- Pull request

### Q: Comment obtenir support professionnel?
**A**: 
- 💬 Community: Discord/GitHub
- 📧 Enterprise: enterprise@sigec.local
- 🎓 Formation disponible

---

## Troubleshooting

### Q: Docker ne démarre pas
**A**: 
```bash
docker ps              # Vérifier daemon
docker-compose logs    # Voir erreurs
docker system prune    # Nettoyer
```

### Q: Application lente
**A**:
- Vérifier CPU/RAM: `docker stats`
- Vérifier DB requêtes lentes
- Optimiser indexes
- Augmenter resources docker

### Q: Erreur "Connection refused"
**A**:
```bash
docker-compose ps                 # Vérifier services
docker-compose logs [service]     # Voir erreurs
docker-compose restart [service]  # Redémarrer
```

Voir [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md) complet

---

## Encore une question?

- 📖 Lire documentation: [docs/](./docs/)
- 💬 Ouvrir issue: [GitHub Issues](https://github.com/gandji1/SIGEC/issues)
- 📧 Email: support@sigec.local
- 🔗 Wiki: https://github.com/gandji1/SIGEC/wiki

---

**Dernière mise à jour**: Décembre 2024
