# syntax=docker/dockerfile:1
# Backend: PHP-FPM sobre Alpine (imagen base liviana).
FROM php:8.3-fpm-alpine

# 1) Capa de dependencias: cambia poco, así se reutiliza la caché de Docker.
RUN docker-php-ext-install -j"$(nproc)" mysqli \
    && rm -rf /tmp/* /var/cache/apk/*

# 2) Configuración propia de PHP y de FPM (logs a stderr, auto_prepend_file, sin pool por defecto).
RUN rm -rf /usr/local/etc/php-fpm.d
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-tienda.ini
COPY docker/app/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY --chmod=755 docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
# Por si el repo se clonó en Windows con saltos de línea CRLF, que rompen los scripts de shell.
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh

# 3) Código (cambia seguido, por eso va al final) e imágenes de muestra.
#    El código queda de solo lectura para el usuario de ejecución.
COPY app/ /var/www/html/
COPY seed/uploaded_img/ /opt/seed/uploaded_img/

# Única carpeta con escritura: la de imágenes subidas (se monta como volumen).
RUN mkdir -p /var/www/html/uploaded_img \
    && chown www-data:www-data /var/www/html/uploaded_img

WORKDIR /var/www/html
# Seguridad: el proceso NO corre como root.
USER www-data
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=5 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm", "-F"]
