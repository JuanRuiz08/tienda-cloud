# syntax=docker/dockerfile:1
# Imagen para Railway: nginx + PHP-FPM en UN solo contenedor (Railway no comparte volúmenes entre
# servicios ni expone un puerto FastCGI interno, así que el frontend y el backend viajan juntos).
# El proceso de la tienda corre como www-data (no root): el entrypoint solo ajusta permisos del volumen.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx su-exec \
    && docker-php-ext-install -j"$(nproc)" mysqli \
    && rm -rf /tmp/* /var/cache/apk/*

# PHP-FPM: misma configuración que el backend local, pero escuchando solo en loopback y con el log en /tmp
# (start.sh lo reenvía a la salida estándar): un usuario no root no puede reabrir /proc/self/fd/2 si el contenedor arranca como root.
RUN rm -rf /usr/local/etc/php-fpm.d
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-tienda.ini
COPY docker/app/php-fpm.conf /usr/local/etc/php-fpm.conf
RUN sed -i 's/^listen = 9000/listen = 127.0.0.1:9000/; s#^error_log = .*#error_log = /tmp/php-fpm.log#' /usr/local/etc/php-fpm.conf

COPY docker/railway/nginx.conf.template /etc/nginx/nginx.conf.template
COPY --chmod=755 docker/railway/start.sh /usr/local/bin/start.sh
COPY --chmod=755 docker/railway/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh /usr/local/bin/start.sh /etc/nginx/nginx.conf.template

COPY app/ /var/www/html/
COPY seed/uploaded_img/ /opt/seed/uploaded_img/
RUN mkdir -p /var/www/html/uploaded_img && chown www-data:www-data /var/www/html/uploaded_img

WORKDIR /var/www/html
ENV PORT=8080
EXPOSE 8080

# Arranca como root solo para preparar el volumen; entrypoint.sh baja a www-data con su-exec.
ENTRYPOINT ["entrypoint.sh"]
CMD ["start.sh"]