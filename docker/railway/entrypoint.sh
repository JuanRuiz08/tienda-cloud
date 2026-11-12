#!/bin/sh
# Prepara el volumen de imágenes y la configuración de nginx; luego baja a www-data.
set -e
PORT="${PORT:-8080}"
UP=/var/www/html/uploaded_img

mkdir -p "$UP" /tmp/nginx
# Un volumen recién creado trae "lost+found", por eso se usa un archivo marcador y no "carpeta vacía".
if [ ! -f "$UP/.seeded" ]; then
    echo "[entrypoint] primer arranque del volumen: copiando imágenes de muestra"
    cp -r /opt/seed/uploaded_img/. "$UP"/
    touch "$UP/.seeded"
fi
# Un volumen de Railway se monta como root: se entrega a www-data.
chown -R www-data:www-data "$UP" /tmp/nginx

# Railway no ejecuta database/init/: si la base está vacía, se cargan el esquema y los datos de muestra.
php /usr/local/bin/init-db.php || echo "[entrypoint] aviso: la inicialización de la base falló"

# Railway asigna el puerto por la variable PORT.
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf.template > /tmp/nginx.conf
chown www-data:www-data /tmp/nginx.conf

echo "[entrypoint] iniciando como www-data en el puerto $PORT"
exec su-exec www-data "$@"