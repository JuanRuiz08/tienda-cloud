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
| `WOMPI_PUBLIC_KEY` | Llave pública de Wompi (`pub_test_…` pruebas, `pub_prod_…` producción) |
| `WOMPI_INTEGRITY_SECRET` | Secreto de integridad (no se sube a Git ni se muestra en pantalla) |
| `WOMPI_EVENTS_SECRET` | Opcional: secreto de eventos para validar el webhook |
| `APP_URL` | Opcional: `https://papeleriaucp.up.railway.app` (o el dominio propio) |
| `CSP_MODO` | `enforce` (defecto), `report` u `off`: si el pago en línea fallara por la política de seguridad, pon `report` y avisa |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` | Opcionales: correo de confirmación de pedidos (Gmail: `smtp.gmail.com`, 587, clave de aplicación) |

Sin las variables de Wompi la tienda funciona: el pago en línea muestra "no disponible", el pedido queda pendiente y se puede pagar en efectivo.

## Pago con PayPal (activo en la demo)

La variable `PASARELA` elige la pasarela: `paypal` (por defecto) o `wompi`. Para la demo se usa PayPal Sandbox:

```bash
railway variable set PAYPAL_CLIENT_ID=... PAYPAL_ENV=sandbox --service tienda
railway variable set PAYPAL_CLIENT_SECRET=... --service tienda
```

Para probar, inicia sesión en la ventana de PayPal con una cuenta *Personal* de Sandbox (developer.paypal.com → *Testing Tools → Sandbox Accounts*). PayPal cobra en dólares: el total se convierte con la tasa real COP/USD (se guarda 6 h) y el monto en USD se **congela** en el pedido (`orders.usd_total`) al crear la orden; al capturar se exige exactamente ese monto. El pedido se paga en 30 minutos o se cancela solo.

## Pago en línea con Wompi (pendiente de activar)

La integración está lista pero **no está activa**: falta crear la cuenta de comercio y cargar las llaves. Para activarla, además de las variables de abajo, pon `PASARELA=wompi`.

Wompi (Bancolombia) cobra en pesos con tarjetas, PSE, Nequi y más.

1. Crea la cuenta en https://comercios.wompi.co y entra al panel en modo **Pruebas**.
2. En *Desarrolladores → Llaves* copia la **llave pública** (`pub_test_…`) y el **secreto de integridad**; en *Secretos para integración técnica* copia también el **secreto de eventos**.
3. Guárdalos en Railway:

   ```bash
   railway variable set WOMPI_PUBLIC_KEY=pub_test_... APP_URL=https://papeleriaucp.up.railway.app --service tienda
   railway variable set WOMPI_INTEGRITY_SECRET=... WOMPI_EVENTS_SECRET=... --service tienda
   ```
4. En *Desarrolladores → URL de eventos* pega `https://TU-DOMINIO/api_wompi.php` (así el pago se confirma aunque el cliente cierre la ventana).
5. Para probar, usa las tarjetas y cuentas de prueba de la documentación de Wompi (docs.wompi.co → *Datos de prueba*): una tarjeta aprobada, una rechazada y PSE.

**Producción:** completa la verificación del comercio en Wompi, cambia a las llaves `pub_prod_…` / `prod_integrity_…` / `prod_events_…` y vuelve a pegar la URL de eventos en el panel de producción.

Flujo: `pagar.php` crea el pedido (pendiente); con pago en línea lleva a `pago-wompi.php?pedido=ID`, que arma el enlace firmado (el monto sale de la base de datos) hacia el checkout de Wompi. Al volver, `pago-resultado.php` consulta la transacción a la API de Wompi, y el webhook `api_wompi.php` hace lo mismo; el pedido pasa a `completed` solo si Wompi confirma `APPROVED` por el monto exacto del pedido en COP. Efectivo queda pendiente.
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
