#!/bin/sh
# Copia las imágenes de muestra al volumen "uploads" solo si está vacío (primer arranque).
set -e
DEST=/var/www/html/uploaded_img
if [ -z "$(ls -A "$DEST" 2>/dev/null)" ]; then
    echo "[entrypoint] volumen de imágenes vacío: copiando imágenes de muestra"
    cp -r /opt/seed/uploaded_img/. "$DEST"/
fi
# Imágenes de muestra nuevas (añadidas en versiones posteriores) se copian aunque el volumen ya exista; no pisa nada.
for f in /opt/seed/uploaded_img/*; do [ -e "$DEST/$(basename "$f")" ] || cp "$f" "$DEST"/; done
# Aplica las migraciones pendientes de la base de datos (si la base aún no está lista, avisa y sigue).
php /usr/local/bin/migrar.php || echo "[entrypoint] aviso: las migraciones no se pudieron aplicar"

exec "$@"
