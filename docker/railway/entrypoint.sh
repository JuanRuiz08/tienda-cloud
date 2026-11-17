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
# Imágenes de muestra nuevas se copian aunque el volumen ya exista; no pisa nada.
for f in /opt/seed/uploaded_img/*; do [ -e "$UP/$(basename "$f")" ] || cp "$f" "$UP"/; done
# Un volumen de Railway se monta como root: se entrega a www-data.
chown -R www-data:www-data "$UP" /tmp/nginx

# Railway no ejecuta database/init/: si la base está vacía, se cargan el esquema y los datos de muestra.
php /usr/local/bin/init-db.php || echo "[entrypoint] aviso: la inicialización de la base falló"
# Migraciones pendientes (cambios de esquema posteriores al volcado inicial).
php /usr/local/bin/migrar.php || echo "[entrypoint] aviso: las migraciones no se pudieron aplicar"

# Railway asigna el puerto por la variable PORT.
# Un reinicio reutiliza el disco del contenedor: el archivo de la ejecución anterior (de www-data) sigue en /tmp,
# y en un directorio con sticky bit root no puede sobrescribirlo (fs.protected_regular). Se borra antes de crearlo.
rm -f /tmp/nginx.conf
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf.template > /tmp/nginx.conf
chown www-data:www-data /tmp/nginx.conf

echo "[entrypoint] iniciando como www-data en el puerto $PORT"
exec su-exec www-data "$@"