#!/usr/bin/env bash
# Genera las evidencias de la Fase 2 en Linux/macOS. Guarda todo en evidencias/logs/.
# Uso (desde la raíz del repositorio):  bash evidencias/probar.sh
set -u
OUT=evidencias/logs
mkdir -p "$OUT"
[ -f .env ] || { cp .env.example .env; echo "Se creó .env a partir de .env.example"; }
PORT=$(grep -E '^WEB_PORT=' .env | cut -d= -f2); PORT=${PORT:-8080}
BASE="http://localhost:$PORT"

esperar_web() {
  for _ in $(seq 1 40); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' tienda_web 2>/dev/null)" = "healthy" ] && return
    sleep 5
  done
  echo "Aviso: el contenedor web no llegó a estado healthy"
}
usuarios() {
  docker compose exec -T db sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -uroot -e "SELECT id, name, email, user_type FROM $MYSQL_DATABASE.users ORDER BY id"' 2>&1 | tee "$OUT/$1"
}

echo "== 1. Construir y levantar con un solo comando"
docker compose up -d --build 2>&1 | tee "$OUT/01_up.txt"
esperar_web

echo "== 2. Estado de los contenedores, redes y volúmenes"
docker compose ps 2>&1 | tee "$OUT/02_ps.txt"
docker network ls --filter name=tienda 2>&1 | tee "$OUT/02_redes.txt"
docker volume ls --filter name=tienda 2>&1 | tee "$OUT/02_volumenes.txt"

echo "== 3. Respuesta HTTP desde el host"
for ruta in /healthz /login.php /css/style.css "/uploaded_img/ancheta%201.jpg"; do
  echo "$ruta -> $(curl -s -o /dev/null -w '%{http_code}' "$BASE$ruta")"
done | tee "$OUT/03_http.txt"

echo "== 4. Crear un usuario desde la aplicación y verlo en la base de datos"
CORREO="prueba$(date +%H%M%S)@ejemplo.com"
curl -s -o /dev/null -d "name=Usuario Prueba&email=$CORREO&password=Clave123&cpassword=Clave123&submit=1" "$BASE/registro.php"
usuarios 04_usuarios_antes.txt

echo "== 5. Aislamiento de redes y usuarios no root"
{
  echo "--- usuario con el que corre cada contenedor ---"
  echo "web: $(docker compose exec -T web id 2>&1)"
  echo "app: $(docker compose exec -T app id 2>&1)"
  echo "--- web intenta llegar a la base de datos (debe fallar: no comparten red) ---"
  docker compose exec -T web sh -c 'nc -z -w 3 db 3306 && echo ALCANZABLE || echo NO ALCANZABLE' 2>&1
  echo "--- app llega a la base de datos (debe funcionar) ---"
  docker compose exec -T app php -r 'echo @fsockopen("db", 3306) ? "app -> db: ALCANZABLE" : "app -> db: NO ALCANZABLE"; echo "\n";' 2>&1
} | tee "$OUT/05_redes_usuarios.txt"

echo "== 6. Logs de cada servicio"
docker compose logs --tail 40 app 2>&1 | tee "$OUT/06_logs_app.txt"
docker compose logs --tail 20 web 2>&1 | tee "$OUT/06_logs_web.txt"
docker compose logs --tail 20 db  2>&1 | tee "$OUT/06_logs_db.txt"

echo "== 7. Persistencia tras reiniciar los contenedores"
docker compose restart 2>&1 | tee "$OUT/07_restart.txt"
esperar_web
usuarios 07_usuarios_despues_restart.txt

echo "== 8. Persistencia tras eliminar y volver a crear los contenedores (sin borrar volúmenes)"
docker compose down 2>&1 | tee "$OUT/08_down.txt"
docker compose up -d 2>&1 | tee "$OUT/08_up.txt"
esperar_web
usuarios 08_usuarios_despues_down_up.txt
docker compose ps 2>&1 | tee "$OUT/08_ps_final.txt"

echo "Listo. Revisa la carpeta $OUT y toma las capturas indicadas en evidencias/PASOS.md"
