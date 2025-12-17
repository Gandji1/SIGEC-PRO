# Script PowerShell pour remplacer les migrations par les versions consolidées
# Exécuter depuis le dossier backend: .\replace_migrations.ps1

$migrationsPath = ".\database\migrations"
$consolidatedPath = ".\database\migrations\consolidated"
$backupPath = ".\database\migrations_backup"

Write-Host "=== Remplacement des migrations SIGEC ===" -ForegroundColor Cyan

# Étape 1: Créer le dossier de backup
Write-Host "`n[1/4] Création du dossier de backup..." -ForegroundColor Yellow
if (!(Test-Path $backupPath)) {
    New-Item -ItemType Directory -Force -Path $backupPath | Out-Null
}

# Étape 2: Déplacer les anciennes migrations vers le backup
Write-Host "[2/4] Sauvegarde des anciennes migrations..." -ForegroundColor Yellow
$oldMigrations = Get-ChildItem -Path $migrationsPath -Filter "*.php" -File
$count = 0
foreach ($file in $oldMigrations) {
    Move-Item -Path $file.FullName -Destination $backupPath -Force
    $count++
}
Write-Host "   $count fichiers déplacés vers migrations_backup" -ForegroundColor Green

# Étape 3: Renommer et déplacer les fichiers consolidés
Write-Host "[3/4] Installation des migrations consolidées..." -ForegroundColor Yellow

$renames = @{
    "0001_create_base_tables.php" = "2025_01_01_000001_create_base_tables.php"
    "0002_create_tenants_users.php" = "2025_01_01_000002_create_tenants_users.php"
    "0003_create_warehouses_products_stocks.php" = "2025_01_01_000003_create_warehouses_products_stocks.php"
    "0004_create_customers_suppliers.php" = "2025_01_01_000004_create_customers_suppliers.php"
    "0005_create_sales_purchases.php" = "2025_01_01_000005_create_sales_purchases.php"
    "0006_create_transfers_inventory.php" = "2025_01_01_000006_create_transfers_inventory.php"
    "0007_create_accounting.php" = "2025_01_01_000007_create_accounting.php"
    "0008_create_pos_tables.php" = "2025_01_01_000008_create_pos_tables.php"
    "0009_create_cash_register.php" = "2025_01_01_000009_create_cash_register.php"
    "0010_create_server_stocks.php" = "2025_01_01_000010_create_server_stocks.php"
    "0011_create_misc_tables.php" = "2025_01_01_000011_create_misc_tables.php"
    "0012_add_performance_indexes.php" = "2025_01_01_000012_add_performance_indexes.php"
}

foreach ($old in $renames.Keys) {
    $oldPath = Join-Path $consolidatedPath $old
    $newName = $renames[$old]
    $newPath = Join-Path $migrationsPath $newName
    
    if (Test-Path $oldPath) {
        Copy-Item -Path $oldPath -Destination $newPath -Force
        Write-Host "   + $newName" -ForegroundColor Green
    } else {
        Write-Host "   ! $old non trouvé" -ForegroundColor Red
    }
}

# Étape 4: Supprimer le dossier consolidated
Write-Host "[4/4] Nettoyage..." -ForegroundColor Yellow
Remove-Item -Path $consolidatedPath -Recurse -Force
Write-Host "   Dossier consolidated supprimé" -ForegroundColor Green

Write-Host "`n=== Migration terminée ===" -ForegroundColor Cyan
Write-Host "`nProchaines étapes:" -ForegroundColor White
Write-Host "1. Vérifier que la base de données est vide ou peut être réinitialisée"
Write-Host "2. Exécuter: php artisan migrate:fresh"
Write-Host "3. Optionnel: php artisan migrate:fresh --seed"
Write-Host "`nPour restaurer les anciennes migrations:" -ForegroundColor Yellow
Write-Host "   Move-Item -Path '$backupPath\*' -Destination '$migrationsPath'"
