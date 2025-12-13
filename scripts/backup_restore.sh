#!/bin/bash

##############################################################################
# SIGEC Backup & Restore Script
# Gère les sauvegardes complètes de la base de données et des fichiers
# Usage: ./backup_restore.sh [backup|restore|list|cleanup]
##############################################################################

set -e

# Configuration
APP_ROOT="/var/www/SIGEC"
BACKEND_ROOT="$APP_ROOT/backend"
BACKUP_DIR="/var/backups/sigec"
DB_NAME="sigec_db"
DB_USER="sigec_user"
DB_HOST="localhost"
RETENTION_DAYS=30
LOG_FILE="/var/log/sigec-backup.log"

# Couleurs pour output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

##############################################################################
# Fonctions utilitaires
##############################################################################

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

##############################################################################
# Créer répertoire de backup
##############################################################################

ensure_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
        chmod 700 "$BACKUP_DIR"
        log_success "Répertoire de backup créé: $BACKUP_DIR"
    fi
}

##############################################################################
# Backup complet
##############################################################################

backup() {
    log_info "=== Démarrage du backup SIGEC ==="
    
    ensure_backup_dir
    
    # Timestamp
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/sigec_backup_$TIMESTAMP.tar.gz"
    DB_DUMP="$BACKUP_DIR/sigec_db_$TIMESTAMP.sql"
    
    # 1. Dump de la base de données
    log_info "Dump de la base de données PostgreSQL..."
    if PGPASSWORD="${PGPASSWORD:-}" pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
        --no-password -Fc > "$DB_DUMP"; then
        log_success "Base de données dumpée: $DB_DUMP"
    else
        log_error "Erreur lors du dump PostgreSQL"
        exit 1
    fi
    
    # 2. Backup de fichiers essentiels
    log_info "Backup des fichiers essentiels..."
    
    cd "$APP_ROOT"
    
    # Fichiers à inclure
    FILES_TO_BACKUP=(
        "backend/.env"
        "backend/storage/app"
        "backend/storage/logs"
        "frontend/.env.production"
        "infra/docker-compose.yml"
    )
    
    # Fichiers à exclure
    EXCLUDE_PATTERNS=(
        "--exclude=node_modules"
        "--exclude=vendor"
        "--exclude=.git"
        "--exclude=dist"
        "--exclude=build"
        "--exclude=__pycache__"
        "--exclude=.env.local"
        "--exclude=.DS_Store"
    )
    
    # Créer archive tar.gz
    if tar -czf "$BACKUP_FILE" \
        "${EXCLUDE_PATTERNS[@]}" \
        -C "$APP_ROOT" \
        "${FILES_TO_BACKUP[@]}" \
        "$DB_DUMP" 2>/dev/null; then
        log_success "Archive créée: $BACKUP_FILE"
    else
        log_error "Erreur lors de la création de l'archive"
        rm -f "$DB_DUMP"
        exit 1
    fi
    
    # 3. Nettoyage DB dump temporaire
    rm -f "$DB_DUMP"
    
    # 4. Calculer taille
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log_success "Backup complété - Taille: $SIZE"
    
    # 5. Vérifier l'intégrité
    if tar -tzf "$BACKUP_FILE" > /dev/null 2>&1; then
        log_success "Vérification intégrité: OK"
    else
        log_error "Archive corrompue!"
        rm -f "$BACKUP_FILE"
        exit 1
    fi
    
    log_success "=== Backup SIGEC terminé ==="
    echo "$BACKUP_FILE"
}

##############################################################################
# Restauration depuis backup
##############################################################################

restore() {
    local BACKUP_FILE="$1"
    
    if [ -z "$BACKUP_FILE" ]; then
        log_error "Usage: $0 restore <chemin_backup_file>"
        exit 1
    fi
    
    if [ ! -f "$BACKUP_FILE" ]; then
        log_error "Fichier backup non trouvé: $BACKUP_FILE"
        exit 1
    fi
    
    log_warning "=== ATTENTION: RESTAURATION EN COURS ==="
    log_warning "Cela va remplacer les données existantes!"
    read -p "Confirmez avec 'oui': " -r
    if [[ ! $REPLY =~ ^oui$ ]]; then
        log_info "Restauration annulée"
        exit 0
    fi
    
    # 1. Arrêter services
    log_info "Arrêt des services..."
    sudo systemctl stop php8.2-fpm || true
    sudo systemctl stop nginx || true
    sudo supervisorctl stop all || true
    
    # 2. Extraire backup
    log_info "Extraction du backup..."
    cd "$APP_ROOT"
    if tar -xzf "$BACKUP_FILE"; then
        log_success "Backup extrait"
    else
        log_error "Erreur extraction"
        exit 1
    fi
    
    # 3. Trouver et restaurer DB dump
    DB_DUMP=$(tar -tzf "$BACKUP_FILE" | grep "sigec_db_.*\.sql" | head -1)
    if [ -z "$DB_DUMP" ]; then
        log_warning "Aucun dump DB trouvé dans l'archive"
    else
        log_info "Restauration base de données..."
        
        # Extraire le dump
        tar -xzf "$BACKUP_FILE" "$DB_DUMP"
        
        # Restaurer vers PostgreSQL
        if PGPASSWORD="${PGPASSWORD:-}" pg_restore -h "$DB_HOST" -U "$DB_USER" \
            -d "$DB_NAME" --clean --if-exists "$DB_DUMP"; then
            log_success "Base de données restaurée"
        else
            log_error "Erreur restauration DB"
            exit 1
        fi
        
        rm -f "$DB_DUMP"
    fi
    
    # 4. Corriger permissions
    log_info "Ajustement permissions..."
    sudo chown -R www-data:www-data "$APP_ROOT"
    sudo chmod -R 755 "$BACKEND_ROOT/storage" "$BACKEND_ROOT/bootstrap/cache"
    
    # 5. Redémarrer services
    log_info "Redémarrage services..."
    sudo systemctl start postgresql
    sudo systemctl start redis-server
    sudo systemctl start php8.2-fpm
    sudo systemctl start nginx
    sudo supervisorctl start all
    
    # 6. Exécuter migrations (si nécessaire)
    log_info "Exécution migrations..."
    cd "$BACKEND_ROOT"
    php artisan migrate --force || true
    
    log_success "=== Restauration complétée ==="
}

##############################################################################
# Lister les backups disponibles
##############################################################################

list_backups() {
    ensure_backup_dir
    
    log_info "Backups disponibles dans $BACKUP_DIR:"
    echo ""
    
    if ls -1 "$BACKUP_DIR"/sigec_backup_*.tar.gz 1> /dev/null 2>&1; then
        ls -lh "$BACKUP_DIR"/sigec_backup_*.tar.gz | awk '{
            printf "%-40s %8s %s\n", $9, $5, $6" "$7" "$8
        }'
    else
        log_warning "Aucun backup trouvé"
    fi
    
    echo ""
}

##############################################################################
# Nettoyage des anciens backups
##############################################################################

cleanup() {
    ensure_backup_dir
    
    log_info "Nettoyage des backups > $RETENTION_DAYS jours..."
    
    DELETED=0
    while IFS= read -r file; do
        rm -f "$file"
        log_info "Supprimé: $(basename "$file")"
        ((DELETED++))
    done < <(find "$BACKUP_DIR" -name "sigec_backup_*.tar.gz" -mtime +$RETENTION_DAYS)
    
    if [ $DELETED -eq 0 ]; then
        log_info "Aucun backup à nettoyer"
    else
        log_success "$DELETED ancien(s) backup(s) supprimé(s)"
    fi
}

##############################################################################
# Backup incrémental (fichiers modifiés depuis dernier backup)
##############################################################################

incremental_backup() {
    local TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    local INCR_FILE="$BACKUP_DIR/sigec_incremental_$TIMESTAMP.tar.gz"
    local LAST_BACKUP="$BACKUP_DIR/.last_backup_time"
    
    log_info "Création backup incrémental..."
    
    ensure_backup_dir
    
    # Déterminer point de départ
    if [ -f "$LAST_BACKUP" ]; then
        SINCE=$(cat "$LAST_BACKUP")
        log_info "Backup incrémental depuis: $SINCE"
        tar -czf "$INCR_FILE" \
            --newer-mtime-than="$SINCE" \
            -C "$APP_ROOT" \
            backend/storage/app \
            backend/storage/logs \
            2>/dev/null || true
    else
        log_warning "Aucun backup antérieur trouvé, effectuant backup complet"
        backup
        return
    fi
    
    # Mettre à jour timestamp
    date > "$LAST_BACKUP"
    
    SIZE=$(du -h "$INCR_FILE" | cut -f1)
    log_success "Backup incrémental créé - Taille: $SIZE"
}

##############################################################################
# Vérification intégrité backup
##############################################################################

verify_backup() {
    local BACKUP_FILE="$1"
    
    if [ -z "$BACKUP_FILE" ]; then
        log_error "Usage: $0 verify <backup_file>"
        exit 1
    fi
    
    if [ ! -f "$BACKUP_FILE" ]; then
        log_error "Fichier non trouvé: $BACKUP_FILE"
        exit 1
    fi
    
    log_info "Vérification intégrité: $BACKUP_FILE"
    
    if tar -tzf "$BACKUP_FILE" > /dev/null 2>&1; then
        log_success "Archive valide"
    else
        log_error "Archive corrompue"
        exit 1
    fi
    
    # Afficher contenu
    log_info "Contenu archive:"
    tar -tzf "$BACKUP_FILE" | head -20
}

##############################################################################
# Menu principal
##############################################################################

case "${1:-help}" in
    backup)
        backup
        ;;
    restore)
        restore "$2"
        ;;
    list)
        list_backups
        ;;
    cleanup)
        cleanup
        ;;
    incremental)
        incremental_backup
        ;;
    verify)
        verify_backup "$2"
        ;;
    *)
        echo "Usage: $0 {backup|restore|list|cleanup|incremental|verify}"
        echo ""
        echo "Commandes:"
        echo "  backup              - Effectuer un backup complet"
        echo "  restore <file>      - Restaurer depuis un backup"
        echo "  list                - Lister tous les backups"
        echo "  cleanup             - Supprimer anciens backups (> $RETENTION_DAYS jours)"
        echo "  incremental         - Backup incrémental (fichiers modifiés)"
        echo "  verify <file>       - Vérifier intégrité d'un backup"
        echo ""
        exit 1
        ;;
esac
