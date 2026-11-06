# Tienda virtual (papelería) en contenedores

Proyecto final de Optativa IV Cloud Computing, Universidad Católica de Pereira (profesor Jorge Leonardo Bañol).
Integrantes: Juan David Ruiz, Yarlinson Mosquera, Diego Carmona.

Es una tienda virtual hecha en PHP, HTML, CSS, JavaScript y MySQL (catálogo, carrito, pedidos, pago con PayPal y panel de administración). En esta fase se adaptó a la nube (replatforming) y se empaquetó con Docker, sin reescribirla desde cero.

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

Cambia estas contraseñas si vas a usar el proyecto fuera de pruebas. phpMyAdmin está en http://127.0.0.1:8081 (usuario `root` y la clave `MYSQL_ROOT_PASSWORD` de tu `.env`).

## Variables de entorno

Se definen en `.env` (no se sube a Git; el modelo está en `.env.example`). Ninguna credencial está escrita en el código.

| Variable | Para qué sirve |
|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASS` | Base y usuario de la aplicación (los usa `app` y los crea `db`) |
| `MYSQL_ROOT_PASSWORD` | Clave del usuario root de MySQL |
| `PAYPAL_CLIENT_ID` | Client-id de PayPal Developer (opcional; sin él no se muestra el botón de pago) |
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

## Estructura del repositorio

```
app/                código PHP, CSS, JS e imágenes de la tienda
  bootstrap.php     logs a stderr, sesiones en MySQL, validación de imágenes
  conexion.php      conexión a MySQL leyendo variables de entorno
database/init/      esquema, procedimientos almacenados y datos de muestra
docker/app/         Dockerfile y configuración de PHP-FPM
docker/web/         Dockerfile y configuración de nginx
seed/uploaded_img/  imágenes de muestra de los productos
evidencias/         scripts de prueba y guía para las capturas del informe
docker-compose.yml  orquestación completa
```

## Pruebas y evidencias

`evidencias/probar.ps1` (Windows) o `evidencias/probar.sh` (Linux/macOS) ejecutan el protocolo completo y guardan los resultados en `evidencias/logs/`: arranque, estado de los contenedores, respuesta HTTP, aislamiento de redes, usuarios no root, logs y persistencia tras `restart` y tras `down` y `up`. Los pasos se explican en `evidencias/PASOS.md`.

## Solución de problemas

- **El puerto 8080 ya está en uso:** cambia `WEB_PORT` en `.env` y vuelve a ejecutar `docker compose up -d`.
- **`variable is not set` al levantar:** falta el archivo `.env` o alguna variable; copia `.env.example`.
- **La base no se inicializó con los datos:** el script solo corre con el volumen vacío. Usa `docker compose down -v` y vuelve a levantar.
- **`app` aparece como `unhealthy` justo al arrancar:** espera unos segundos a que MySQL termine de iniciar y revisa `docker compose logs app`.
- **Error `exec format error` o `no such file` en `entrypoint.sh` (Windows):** el archivo quedó con saltos de línea CRLF. El repositorio incluye `.gitattributes` para evitarlo; si pasó, ejecuta `git add --renormalize .` y reconstruye con `docker compose build --no-cache app`.

## Deuda técnica pendiente

Quedaron fuera de esta fase y se documentan en el informe: varias consultas del código original siguen armando SQL con variables sin parametrizar, no hay protección CSRF en los formularios y el pago de PayPal no se verifica en el servidor.
