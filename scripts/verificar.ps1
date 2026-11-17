# Verificación rápida antes de desplegar: construye las dos imágenes, levanta el entorno local y prueba lo básico.
# Uso:  .\scripts\verificar.ps1
$ErrorActionPreference = 'Continue'   # docker escribe avisos en stderr; se comprueba $LASTEXITCODE
Set-Location (Split-Path $PSScriptRoot -Parent)
function Paso($t) { Write-Host "`n== $t" -ForegroundColor Cyan }
function Falla($t) { Write-Host "FALLO: $t" -ForegroundColor Red; exit 1 }

Paso 'Construyendo imagen de Railway (misma que se despliega)'
docker build -q -f docker/railway/Dockerfile -t tienda-railway-check . | Out-Null
if ($LASTEXITCODE) { Falla 'no construye la imagen de Railway' }

Paso 'Levantando entorno local'
docker compose up -d --build | Out-Null
if ($LASTEXITCODE) { Falla 'docker compose up' }
$ok = $false
1..30 | ForEach-Object { if (-not $ok) { Start-Sleep 2; if ((curl.exe -s -o NUL -w '%{http_code}' http://localhost:8080/healthz) -eq '200') { $ok = $true } } }
if (-not $ok) { Falla '/healthz no responde 200' }
Write-Host 'healthz OK'

Paso 'Sintaxis PHP'
$malos = docker compose exec -T app sh -c 'find /var/www/html -name "*.php" -exec php -l {} \; | grep -v "^No syntax" || true' 2>$null | Out-String
if ($malos.Trim()) { Write-Host $malos; Falla 'errores de sintaxis' }

Paso 'Páginas y seguridad'
foreach ($p in 'inicio.php','login.php','tienda.php') {
   $c = curl.exe -s -o NUL -w '%{http_code}' "http://localhost:8080/$p"
   Write-Host "$p -> $c"
   if ($c -ne '200') { Falla "$p no devuelve 200" }
}
$h = (curl.exe -s -D - -o NUL http://localhost:8080/login.php) | Out-String
if ($h -notmatch 'Content-Security-Policy') { Falla 'falta la cabecera CSP' }
$c = curl.exe -s -o NUL -w '%{http_code}' -X POST http://localhost:8080/login.php
if ($c -ne '403') { Falla "POST sin token debería dar 403 y dio $c" }
Write-Host 'CSP y CSRF OK'
docker rmi tienda-railway-check 2>$null | Out-Null
Write-Host "`nTodo en orden." -ForegroundColor Green