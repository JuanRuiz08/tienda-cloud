#!/bin/sh
# Mantiene nginx y PHP-FPM dentro del mismo contenedor. Ambos escriben sus logs en la salida estándar.
# Si uno de los dos se cae, el contenedor termina y Railway lo reinicia (restartPolicy).
# FIFO para los access logs de nginx: este proceso conserva la salida estándar y la reenvía.
mkdir -p /tmp/nginx && rm -f /tmp/nginx/access.fifo && mkfifo /tmp/nginx/access.fifo
cat /tmp/nginx/access.fifo &
: > /tmp/php-fpm.log
tail -F /tmp/php-fpm.log &

php-fpm -F -y /usr/local/etc/php-fpm.conf &
PHP_PID=$!
nginx -e stderr -c /tmp/nginx.conf -g 'daemon off;' &
NGINX_PID=$!

trap 'kill $PHP_PID $NGINX_PID 2>/dev/null; wait; exit 0' TERM INT

while kill -0 "$PHP_PID" 2>/dev/null && kill -0 "$NGINX_PID" 2>/dev/null; do
    sleep 2
done
echo "[start] un proceso terminó (php-fpm=$PHP_PID, nginx=$NGINX_PID); cerrando el contenedor para que se reinicie"
kill $PHP_PID $NGINX_PID 2>/dev/null
exit 1