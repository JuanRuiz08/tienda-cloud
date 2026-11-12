# Despliegue en Railway

Railway construye y ejecuta nuestro propio contenedor (`docker/railway/Dockerfile`, indicado en `railway.json`); no se despliega código plano.

## Arquitectura en Railway

```
Usuario ──HTTPS──▶ dominio público de Railway ──▶ servicio "tienda" (nginx + PHP-FPM, un contenedor, usuario www-data)
                                                       │  volumen en /var/www/html/uploaded_img
                                                       └── red privada ──▶ servicio "MySQL" (base administrada con volumen propio)
```

Por qué nginx y PHP-FPM van juntos aquí: Railway asigna el puerto por la variable `PORT`, no permite compartir un volumen entre dos servicios (`uploads` lo necesitan `web` y `app` en local) y la comunicación FastCGI entre servicios sería tráfico interno extra. En local se conserva la versión de tres contenedores del `docker-compose.yml`.

## Pasos

1. **Subir el código** con la carpeta `docker/railway/` y `railway.json` a GitHub.
2. En Railway: **New Project → Deploy from GitHub repo** y elegir el repositorio. Toma `railway.json` y construye con el Dockerfile.
3. **+ New → Database → Add MySQL** en el mismo proyecto.
4. En el servicio de la tienda → **Variables** (se referencian las del servicio MySQL, así ninguna credencial queda en el código):

   | Variable | Valor |
   |---|---|
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_USER` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASS` | `${{MySQL.MYSQLPASSWORD}}` |
   | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
   | `PAYPAL_CLIENT_ID` | client-id de PayPal Developer (opcional) |

5. **Volumen para las imágenes:** servicio de la tienda → **Volumes → New Volume**, ruta de montaje `/var/www/html/uploaded_img`. En el primer arranque el contenedor copia las imágenes de muestra.
6. **Dominio público:** servicio de la tienda → **Settings → Networking → Generate Domain**.
7. **Cargar el esquema y los datos de muestra** (Railway no ejecuta `database/init/`). Desde el servicio MySQL → **Connect → Public Network** se obtienen host, puerto y clave; luego, desde la raíz del repositorio:

   ```powershell
   docker run --rm -v "${PWD}/database/init:/sql" mysql:8.0 sh -c "mysql -h HOST_PUBLICO -P PUERTO_PUBLICO -uroot -pCLAVE railway < /sql/01_shop_db.sql"
   ```

   Reemplaza `HOST_PUBLICO`, `PUERTO_PUBLICO` y `CLAVE` por los valores del panel. Se ejecuta una sola vez.
8. Abrir la URL generada y entrar con un usuario de demostración. Si la app es pública, cambia las contraseñas de demostración.

## Prueba de resiliencia para la demo

1. En la URL pública, registrar un usuario y hacer un pedido.
2. En el panel del servicio: **⋯ → Restart** (o **Redeploy**).
3. Cuando vuelva a estar activo, iniciar sesión con el mismo usuario: el pedido y el usuario siguen ahí, porque los datos están en MySQL y las imágenes en el volumen.

## Para mostrar en la sustentación

- **Logs en tiempo real:** pestaña *Deployments → View Logs* (nginx y PHP salen por la salida estándar).
- **Recursos:** pestaña *Metrics* del servicio.
- **Variables seguras:** pestaña *Variables* (no hay credenciales en el repositorio).
