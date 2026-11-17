# Copia de seguridad de la base de datos local (docker compose). Uso:  .\scripts\backup.ps1
# Genera backups\tienda-AAAAMMDD-HHMMSS.sql. Para restaurar:
#   Get-Content backups\ARCHIVO.sql -Raw | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
# (En Railway usa el botón "Descargar copia" de la página Mi cuenta del administrador.)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
New-Item -ItemType Directory -Force backups | Out-Null
$archivo = "backups\tienda-$(Get-Date -Format 'yyyyMMdd-HHmmss').sql"
docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE" 2>/dev/null' | Out-File -Encoding utf8 $archivo
if ((Get-Item $archivo).Length -lt 1000) { throw "La copia parece vacía: $archivo" }
Write-Host "Copia guardada en $archivo ($([int]((Get-Item $archivo).Length/1KB)) KB)"