# Despliegue en Railway

Railway construye y ejecuta nuestro propio contenedor (`docker/railway/Dockerfile`, indicado con la variable `RAILWAY_DOCKERFILE_PATH`); no se despliega código plano. URL pública: https://papeleriaucp.up.railway.app

## Arquitectura en Railway

```
Usuario ──HTTPS──▶ dominio de Railway ──▶ servicio "tienda" (nginx + PHP-FPM, un contenedor, usuario www-data)
                                              │  volumen en /var/www/html/uploaded_img
                                              └── red privada ──▶ servicio "MySQL" (base administrada con volumen propio)
```

nginx y PHP-FPM van en el mismo contenedor porque Railway asigna el puerto con la variable `PORT`, no comparte volúmenes entre servicios y la red privada es de pago por tráfico. En local se conserva la versión de tres contenedores del `docker-compose.yml`.

- **La base se carga sola:** `docker/railway/init-db.php` importa `database/init/01_shop_db.sql` en el primer arranque, solo si la base está vacía.
- **Imágenes subidas:** viven en el volumen; el primer arranque copia las de muestra (archivo marcador `.seeded`).
- **Logs:** nginx y PHP-FPM escriben en la salida estándar, visibles en el panel y con `railway logs`.

## Desplegar (CLI)

Requisitos: `npm install -g @railway/cli` y `railway login`.

```bash
cd tienda-cloud
railway status
railway up --service tienda --ci
```

Sube la carpeta local (lo que haya, esté o no en Git), construye la imagen y reemplaza el contenedor. La base de datos no se toca.

## Primera vez: crear el proyecto

```bash
railway init --name tienda-cloud
railway add --database mysql
railway add --service tienda \
  --variables 'DB_HOST=${{MySQL.MYSQLHOST}}' --variables 'DB_USER=${{MySQL.MYSQLUSER}}' \
  --variables 'DB_PASS=${{MySQL.MYSQLPASSWORD}}' --variables 'DB_NAME=${{MySQL.MYSQLDATABASE}}'
railway service link tienda
railway variable set RAILWAY_DOCKERFILE_PATH=docker/railway/Dockerfile --service tienda --skip-deploys
railway volume add --mount-path /var/www/html/uploaded_img
railway domain --service tienda
railway up --service tienda --ci
```

Cambiar el subdominio (`<nombre>.up.railway.app`; quitar `.up.railway.app` solo es posible con un dominio propio):

```bash
railway domain list --service tienda
railway domain update <ID_DEL_DOMINIO> --domain papeleriaucp --service tienda
```

## Variables de entorno (panel del servicio → Variables)

| Variable | Valor |
|---|---|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Referencias al servicio MySQL: `${{MySQL.MYSQLHOST}}`, `${{MySQL.MYSQLUSER}}`, `${{MySQL.MYSQLPASSWORD}}`, `${{MySQL.MYSQLDATABASE}}` |
| `RAILWAY_DOCKERFILE_PATH` | `docker/railway/Dockerfile` |
| `PAYPAL_CLIENT_ID` | Client ID de la app de PayPal Developer |
| `PAYPAL_CLIENT_SECRET` | Secret de la misma app (no se sube a Git ni se muestra en pantalla) |
| `PAYPAL_ENV` | `sandbox` (pruebas) o `live` (cobros reales) |
| `PAYPAL_COP_PER_USD` | Tasa de respaldo (por defecto `4000`) si la consulta automática falla |
| `PAYPAL_TASA_AUTO` | `1` (defecto) consulta la tasa real COP/USD cada 6 h; `0` usa siempre la fija |
| `CSP_MODO` | `enforce` (defecto), `report` u `off`: si el botón de PayPal no cargara por la política de seguridad, pon `report` y avisa |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` | Opcionales: correo de confirmación de pedidos (Gmail: `smtp.gmail.com`, 587, clave de aplicación) |

Sin las variables de PayPal la tienda funciona: el pago con tarjeta muestra "no disponible" y el pedido queda pendiente.

## Pago con PayPal

1. En https://developer.paypal.com → *Apps & Credentials* → modo **Sandbox** → *Create App*. Copia *Client ID* y *Secret*.
2. Guárdalos en Railway:

   ```bash
   railway variable set PAYPAL_CLIENT_ID=... PAYPAL_ENV=sandbox --service tienda
   railway variable set PAYPAL_CLIENT_SECRET=... --service tienda
   ```
3. Para probar usa una cuenta *Personal* de Sandbox (*Testing Tools → Sandbox Accounts*) al iniciar sesión en la ventana de PayPal.

**Cobros reales:** crea una app en modo **Live** en developer.paypal.com, pon `PAYPAL_ENV=live` y las credenciales Live (nunca en el chat ni en Git). El monto en dólares se calcula con la tasa del día y se **congela** en el pedido (`orders.usd_total`) al crear la orden; al capturar se exige exactamente ese monto.

Flujo: `pagar.php` crea el pedido (pendiente); con tarjeta lleva a `pago-paypal.php?pedido=ID`; el botón llama a `api_paypal.php`, que crea la orden con el total leído de la base de datos, captura el pago en PayPal y solo si PayPal confirma `COMPLETED` por el monto esperado marca el pedido como `completed`. Efectivo queda pendiente.

## Migraciones y claves de demostración

Cada despliegue aplica las migraciones pendientes al arrancar (`[migrar]` en los logs). Las cuentas de demostración del seed son solo para local: tras el primer despliegue, entra como administrador y cambia las claves en *Mi cuenta*.

## Prueba de resiliencia para la demo

1. En la URL pública, registrar un usuario y hacer un pedido.
2. En el panel del servicio: *Deployments → Redeploy* (o `railway service restart --service tienda`).
3. Cuando vuelva a estar activo, iniciar sesión con el mismo usuario: el pedido y el usuario siguen ahí, porque los datos están en MySQL y las imágenes en el volumen.

## Para mostrar en la sustentación

- **Logs en tiempo real:** pestaña *Deployments → View Logs* o `railway logs --service tienda`.
- **Recursos:** pestaña *Metrics* del servicio.
- **Variables seguras:** pestaña *Variables* (no hay credenciales en el repositorio).
