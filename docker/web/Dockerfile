# syntax=docker/dockerfile:1
# Frontend: nginx sin privilegios (corre como usuario "nginx", puerto 8080 interno).
FROM nginxinc/nginx-unprivileged:1.27-alpine

COPY docker/web/default.conf /etc/nginx/conf.d/default.conf

# Solo los archivos estáticos. El código PHP vive únicamente en el contenedor "app".
COPY app/css/    /var/www/html/css/
COPY app/js/     /var/www/html/js/
COPY app/images/ /var/www/html/images/

EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=3s --start-period=5s --retries=5 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
