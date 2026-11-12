#!/bin/sh
# Prepara el volumen de imágenes y la configuración de nginx; luego baja a www-data.
set -e
PORT="${PORT:-8080}"
UP=/var/www/html/uploaded_img

mkdir -p "$UP" /tmp/nginx
if [ -z "$(ls -A "$UP" 2>/dev/null)" ]; then
    echo "[entrypoint] volumen de imágenes vacío: copiando imágenes de muestra"
    cp -r /opt/seed/uploaded_img/. "$UP"/
fi
# Un volumen de Railway se monta como root: se entrega a www-data.
chown -R www-data:www-data "$UP" /tmp/nginx

# Railway asigna el puerto por la variable PORT.
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf.template > /tmp/nginx.conf
chown www-data:www-data /tmp/nginx.conf

echo "[entrypoint] iniciando como www-data en el puerto $PORT"
exec su-exec www-data "$@"