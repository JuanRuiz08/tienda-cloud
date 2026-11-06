#!/bin/sh
# Copia las imágenes de muestra al volumen "uploads" solo si está vacío (primer arranque).
set -e
DEST=/var/www/html/uploaded_img
if [ -z "$(ls -A "$DEST" 2>/dev/null)" ]; then
    echo "[entrypoint] volumen de imágenes vacío: copiando imágenes de muestra"
    cp -r /opt/seed/uploaded_img/. "$DEST"/
fi
exec "$@"
