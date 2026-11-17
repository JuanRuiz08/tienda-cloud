# Tienda virtual (papelería) en contenedores

Proyecto final de Optativa IV Cloud Computing, Universidad Católica de Pereira (profesor Jorge Leonardo Bañol).
Integrantes: Juan David Ruiz, Yarlinson Mosquera, Diego Carmona.

Es una tienda virtual hecha en PHP, HTML, CSS, JavaScript y MySQL (catálogo, carrito, pedidos, pago en línea con Wompi y panel de administración). En esta fase se adaptó a la nube (replatforming) y se empaquetó con Docker, sin reescribirla desde cero.

## Arquitectura

```
Usuario ──▶ :8080 ──▶ web (nginx)  ──FastCGI :9000──▶  app (PHP-FPM)  ──SQL :3306──▶  db (MySQL 8)
                       frontend_net ───────────────────┤ backend_net (internal) ──────┤
                       volumen uploads (solo lectura)  │ volumen uploads (lectura/escritura)   volumen db_data
                                                                      phpMyAdmin ──▶ db   (solo en 127.0.0.1:8081)
```

| Servicio | Imagen | Función | Puerto en tu equipo |
|---|---|---|---|
| `web` | nginx-unprivileged (Alpine), build propio | Sirve CSS, JS e imágenes y reenvía los `.php` al backend | 8080 |
| `app` | php:8.3-fpm-alpine, build propio | Lógica de la tienda | ninguno |
| `db` | mysql:8.0 | Base de datos `shop_db` | ninguno |
| `phpmyadmin` | phpmyadmin:5-apache | Administración de la BD (solo desarrollo) | 127.0.0.1:8081 |

Redes: `frontend_net` conecta `web` con `app`; `backend_net` (interna, sin salida a Internet) conecta `app` con `db`. `web` no puede llegar a la base de datos.
Volúmenes: `db_data` guarda los datos de MySQL y `uploads` guarda las imágenes de los productos.

## Cómo ejecutarlo

Requisitos: Docker Desktop (o Docker Engine) con Docker Compose v2.

1. Clonar el repositorio y entrar a la carpeta.

   ```bash
   git clone <URL-del-repositorio> tienda-cloud
   cd tienda-cloud
   ```

2. Crear el archivo de variables de entorno y cambiar las claves.

   ```bash
   cp .env.example .env        # en Windows PowerShell: Copy-Item .env.example .env
   ```

3. Construir y levantar todo con un solo comando.

   ```bash
   docker compose up -d --build
   ```

   La primera vez tarda un poco: MySQL crea la base y ejecuta `database/init/01_shop_db.sql`.

4. Abrir la tienda en http://localhost:8080 y entrar con un usuario de demostración.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@tienda.local | Admin2026! |
| Cliente | cliente@tienda.local | Cliente2026! |

Son cuentas **solo para el entorno local**; en la nube se cambian desde *Mi cuenta* (`cuenta.php`). Las contraseñas nuevas deben tener 8 caracteres o más, con letras y números. phpMyAdmin está en http://127.0.0.1:8081 (usuario `root` y la clave `MYSQL_ROOT_PASSWORD` de tu `.env`).

## Variables de entorno

Se definen en `.env` (no se sube a Git; el modelo está en `.env.example`). Ninguna credencial está escrita en el código.

| Variable | Para qué sirve |
|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASS` | Base y usuario de la aplicación (los usa `app` y los crea `db`) |
| `MYSQL_ROOT_PASSWORD` | Clave del usuario root de MySQL |
| `WOMPI_PUBLIC_KEY`, `WOMPI_INTEGRITY_SECRET` | Llaves de Wompi (opcionales; sin ellas el pago en línea queda desactivado y el pedido queda pendiente) |
| `WOMPI_EVENTS_SECRET`, `APP_URL` | Secreto de eventos (valida el webhook) y dirección pública de la tienda (opcionales) |
| `CSP_MODO` | Política de seguridad de contenido: `enforce` (por defecto), `report` (solo avisa en la consola del navegador) u `off` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` | Correo de confirmación de pedidos (opcional; sin `SMTP_HOST` no se envía nada) |
| `WEB_PORT`, `PMA_PORT` | Puertos publicados en tu equipo (por defecto 8080 y 8081) |

## Comandos útiles

```bash
docker compose ps                  # estado y salud de los servicios
docker compose logs -f app         # logs del backend (también web y db)
docker compose restart             # reiniciar; los datos se conservan
docker compose down                # borrar contenedores; los volúmenes se conservan
docker compose down -v             # borrar también los volúmenes (empieza de cero)
```

## Persistencia y estado

- La base de datos vive en el volumen `db_data`. El script de `database/init/` solo se ejecuta cuando ese volumen está vacío.
- Las imágenes subidas por el administrador viven en el volumen `uploads`; en el primer arranque se copian las imágenes de muestra de `seed/uploaded_img/`.
- Las sesiones de usuario se guardan en la tabla `sessions` de MySQL, por eso reiniciar o recrear el contenedor `app` no cierra la sesión de nadie (la aplicación es stateless).
- Los logs salen por la salida estándar de cada contenedor y se consultan con `docker compose logs`.

## Base de datos: migraciones y copias de seguridad

- **Migraciones:** `database/migrations/NNN_nombre.php` cambian el esquema de una base que ya existe. `docker/migrar.php` las aplica en orden al arrancar `app` (local y Railway) y registra cuáles corrieron en `schema_migrations`; una base nueva ejecuta el seed y luego las migraciones. Para un cambio nuevo, agrega el siguiente número; nunca edites una migración ya aplicada.
- **Copias:** `.\scripts\backup.ps1` genera `backups/tienda-FECHA.sql` desde el contenedor local; el administrador también puede descargar una copia desde *Mi cuenta → Copia de seguridad* (funciona igual en Railway). Para restaurar: `Get-Content backups\ARCHIVO.sql -Raw | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'`.
- **Verificación antes de desplegar:** `.\scripts\verificar.ps1` construye las dos imágenes (local y Railway, desde el mismo código), levanta el entorno y comprueba salud, sintaxis PHP, páginas, CSP y CSRF.

## Pedidos y ventas en tienda

- Un pedido web nace **pendiente** y reserva el stock. Si el cliente nunca paga, el administrador lo **cancela** desde *Pedidos* (queda en el historial como «Cancelado» y el stock vuelve a la tienda; no se descuenta dos veces aunque se pulse dos veces). Un pedido cancelado no se reabre.
- **Vencimiento automático:** un pedido de pago en línea que no se paga en **30 minutos** (`PEDIDO_EXPIRA_MIN`, mínimo 5) se cancela solo y devuelve el stock. Los pedidos en efectivo no vencen. Como la aplicación no guarda estado ni usa tareas programadas, el barrido corre durante las peticiones, como máximo una vez por minuto. Si el cliente paga justo después de vencer, Wompi aprueba el pago y la tienda reserva de nuevo el stock y deja el pedido pagado; si ya no hay stock queda cancelado, con un error en los logs para devolver el dinero.
- Las **ventas presenciales** se registran en *Venta en tienda*: se eligen productos y cantidades, la forma de pago (efectivo, datáfono, transferencia/Nequi) y, opcionalmente, el nombre del cliente. La venta queda **completada** al instante, descuenta el stock y cuenta en las ventas del panel; en *Pedidos* aparece con la etiqueta «En tienda».
- Todo queda en *Actividad* (auditoría).

## Seguridad

Formularios con token CSRF (todo POST sin token da 403) y borrados siempre por POST con confirmación · bloqueo temporal tras 6 intentos fallidos de login por correo (30 por IP) en 15 minutos · contraseñas de 8+ caracteres con letras y números · cookie de sesión `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS · cabeceras CSP, HSTS (solo HTTPS) y Permissions-Policy · registro de actividad del administrador en *Actividad* (tabla `auditoria`) · listas paginadas.

## Estructura del repositorio

```
app/                código PHP, CSS, JS e imágenes de la tienda
  bootstrap.php     logs a stderr, sesiones en MySQL, validación de imágenes
  conexion.php      conexión a MySQL leyendo variables de entorno
  includes/         código compartido (no se sirve por URL): app.php (carga todo), sesion.php (acceso),
                    vistas.php (head, mensajes, cierre), datos.php (consultas con parámetros),
                    tienda.php (tarjeta de producto y carrito), autenticacion.php (login)
  header.php, footer.php, admin_header.php   partes comunes de las páginas
database/init/      esquema, procedimientos almacenados y datos de muestra (base nueva)
database/migrations/ cambios de esquema incrementales (se aplican solos al arrancar)
scripts/            backup.ps1 (copia local) y verificar.ps1 (chequeo previo al despliegue)
docker/app/         Dockerfile y configuración de PHP-FPM
docker/web/         Dockerfile y configuración de nginx
seed/uploaded_img/  imágenes de muestra de los productos
evidencias/         scripts de prueba y guía para las capturas del informe
docker-compose.yml  orquestación completa
```

## Despliegue en la nube (Railway)

La tienda también se despliega en Railway con una imagen propia (`docker/railway/Dockerfile`: nginx + PHP-FPM en un contenedor, usuario no root), una base MySQL administrada y un volumen para las imágenes. Los pasos y la prueba de resiliencia están en [RAILWAY.md](RAILWAY.md).

## Pruebas y evidencias

`evidencias/probar.ps1` (Windows) o `evidencias/probar.sh` (Linux/macOS) ejecutan el protocolo completo y guardan los resultados en `evidencias/logs/`: arranque, estado de los contenedores, respuesta HTTP, aislamiento de redes, usuarios no root, logs y persistencia tras `restart` y tras `down` y `up`. Los pasos se explican en `evidencias/PASOS.md`.

## Solución de problemas

- **El puerto 8080 ya está en uso:** cambia `WEB_PORT` en `.env` y vuelve a ejecutar `docker compose up -d`.
- **`variable is not set` al levantar:** falta el archivo `.env` o alguna variable; copia `.env.example`.
- **La base no se inicializó con los datos:** el script solo corre con el volumen vacío. Usa `docker compose down -v` y vuelve a levantar.
- **`app` aparece como `unhealthy` justo al arrancar:** espera unos segundos a que MySQL termine de iniciar y revisa `docker compose logs app`.
- **Error `exec format error` o `no such file` en `entrypoint.sh` (Windows):** el archivo quedó con saltos de línea CRLF. El repositorio incluye `.gitattributes` para evitarlo; si pasó, ejecuta `git add --renormalize .` y reconstruye con `docker compose build --no-cache app`.

## Deuda técnica pendiente

Lo que queda abierto de forma consciente: no hay recuperación de contraseña por correo ni verificación del correo al registrarse; los correos de pedido se envían por SMTP simple (sin cola de reintentos); el listado del panel se actualiza por sondeo cada 8 s y no por WebSockets; no hay pruebas automatizadas de PHP (solo `scripts/verificar.ps1` y `evidencias/`). Ya resueltos: consultas con parámetros, pago con Wompi confirmado en el servidor, CSRF, límite de intentos de login, migraciones, copias, auditoría, paginación y pedidos con tabla de ítems.