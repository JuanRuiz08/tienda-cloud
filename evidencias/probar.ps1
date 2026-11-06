# Genera las evidencias de la Fase 2 en Windows (PowerShell). Guarda todo en evidencias\logs\.
# Uso (desde la raíz del repositorio):  powershell -ExecutionPolicy Bypass -File evidencias\probar.ps1
$ErrorActionPreference = 'Continue'
$out = 'evidencias\logs'
New-Item -ItemType Directory -Force $out | Out-Null
if (-not (Test-Path .env)) { Copy-Item .env.example .env; Write-Host 'Se creó .env a partir de .env.example' }
$port = (Select-String -Path .env -Pattern '^WEB_PORT=(\d+)').Matches.Groups[1].Value
if (-not $port) { $port = '8080' }
$base = "http://localhost:$port"

function Esperar-Web {
    for ($i = 0; $i -lt 40; $i++) {
        $estado = docker inspect -f '{{.State.Health.Status}}' tienda_web 2>$null
        if ($estado -eq 'healthy') { return }
        Start-Sleep -Seconds 5
    }
    Write-Host 'Aviso: el contenedor web no llegó a estado healthy' -ForegroundColor Yellow
}
function Usuarios($archivo) {
    # Sin comillas dobles dentro del argumento: Windows PowerShell 5.1 las pierde al llamar a docker.
    docker compose exec -T db sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -uroot -e ''SELECT id, name, email, user_type FROM shop_db.users ORDER BY id''' 2>&1 | Tee-Object -FilePath "$out\$archivo"
}

Write-Host '== 1. Construir y levantar con un solo comando'
docker compose up -d --build 2>&1 | Tee-Object -FilePath "$out\01_up.txt"
Esperar-Web

Write-Host '== 2. Estado de los contenedores, redes y volúmenes'
docker compose ps 2>&1 | Tee-Object -FilePath "$out\02_ps.txt"
docker network ls --filter name=tienda 2>&1 | Tee-Object -FilePath "$out\02_redes.txt"
docker volume ls --filter name=tienda 2>&1 | Tee-Object -FilePath "$out\02_volumenes.txt"

Write-Host '== 3. Respuesta HTTP desde el host'
'/healthz', '/login.php', '/css/style.css', '/uploaded_img/ancheta%201.jpg' | ForEach-Object {
    try { $r = Invoke-WebRequest -UseBasicParsing "$base$_"; "$_ -> $($r.StatusCode)" }
    catch { "$_ -> ERROR $($_.Exception.Message)" }
} | Tee-Object -FilePath "$out\03_http.txt"

Write-Host '== 4. Crear un usuario desde la aplicación y verlo en la base de datos'
$correo = "prueba$(Get-Date -Format 'HHmmss')@ejemplo.com"
try {
    Invoke-WebRequest -UseBasicParsing -Method Post -Uri "$base/registro.php" -MaximumRedirection 0 -ErrorAction SilentlyContinue `
        -Body @{ name = 'Usuario Prueba'; email = $correo; password = 'Clave123'; cpassword = 'Clave123'; submit = '1' } | Out-Null
} catch { }
Usuarios '04_usuarios_antes.txt'

Write-Host '== 5. Aislamiento de redes y usuarios no root'
@(
    '--- usuario con el que corre cada contenedor ---',
    'web: ' + (docker compose exec -T web id 2>&1),
    'app: ' + (docker compose exec -T app id 2>&1),
    '--- web intenta llegar a la base de datos (debe fallar: no comparten red) ---',
    (docker compose exec -T web sh -c 'nc -z -w 3 db 3306 && echo ALCANZABLE || echo NO ALCANZABLE' 2>&1),
    '--- app llega a la base de datos (debe funcionar) ---',
    (docker compose exec -T app php -r "echo @fsockopen('db', 3306) ? 'app -> db: ALCANZABLE' : 'app -> db: NO ALCANZABLE';" 2>&1)
) | Tee-Object -FilePath "$out\05_redes_usuarios.txt"

Write-Host '== 6. Logs de cada servicio'
docker compose logs --tail 40 app 2>&1 | Tee-Object -FilePath "$out\06_logs_app.txt"
docker compose logs --tail 20 web 2>&1 | Tee-Object -FilePath "$out\06_logs_web.txt"
docker compose logs --tail 20 db  2>&1 | Tee-Object -FilePath "$out\06_logs_db.txt"

Write-Host '== 7. Persistencia tras reiniciar los contenedores'
docker compose restart 2>&1 | Tee-Object -FilePath "$out\07_restart.txt"
Esperar-Web
Usuarios '07_usuarios_despues_restart.txt'

Write-Host '== 8. Persistencia tras eliminar y volver a crear los contenedores (sin borrar volúmenes)'
docker compose down 2>&1 | Tee-Object -FilePath "$out\08_down.txt"
docker compose up -d 2>&1 | Tee-Object -FilePath "$out\08_up.txt"
Esperar-Web
Usuarios '08_usuarios_despues_down_up.txt'
docker compose ps 2>&1 | Tee-Object -FilePath "$out\08_ps_final.txt"

Write-Host "Listo. Revisa la carpeta $out y toma las capturas de pantalla indicadas en evidencias\PASOS.md"
