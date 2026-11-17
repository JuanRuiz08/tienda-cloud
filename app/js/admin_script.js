/* ---------- Menú lateral desplegable ---------- */
const botonMenu = document.querySelector('#menu-btn');
const menuLateral = document.querySelector('#menu-lateral');
const scrim = document.querySelector('#scrim');

function abrirMenu() {
   menuLateral.classList.add('abierto');
   scrim.classList.add('abierto');
   document.body.classList.add('menu-abierto');
   menuLateral.setAttribute('aria-hidden', 'false');
   botonMenu.setAttribute('aria-expanded', 'true');
   menuLateral.querySelector('.menu-nav a.activo, .menu-nav a')?.focus({ preventScroll: true });
}

function cerrarMenu() {
   menuLateral.classList.remove('abierto');
   scrim.classList.remove('abierto');
   document.body.classList.remove('menu-abierto');
   menuLateral.setAttribute('aria-hidden', 'true');
   botonMenu.setAttribute('aria-expanded', 'false');
}

botonMenu.addEventListener('click', () => (menuLateral.classList.contains('abierto') ? cerrarMenu() : abrirMenu()));
scrim.addEventListener('click', cerrarMenu);
menuLateral.querySelector('.menu-cerrar').addEventListener('click', () => { cerrarMenu(); botonMenu.focus(); });
document.addEventListener('keydown', (e) => {
   if (e.key === 'Escape' && menuLateral.classList.contains('abierto')) {
      cerrarMenu();
      botonMenu.focus();
   }
});
// Ventana de edición: se cierra con Escape o al hacer clic en el fondo
const modal = document.querySelector('.edit-product-form');
if (modal) {
   const cerrar = () => { location.href = 'admin_productos.php'; };
   modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
   document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });
}

/* ---------- Actualización en vivo ----------
   Cada 8 s se consulta api_admin.php (un JSON diminuto con una "versión" de los datos y el contador de
   pendientes). Solo si la versión cambió, las zonas marcadas con data-vivo="nombre" se vuelven a pedir al
   servidor y se reemplazan si cambió su contenido (un pedido nuevo o un pago de PayPal se ven sin recargar). */
const zonasVivas = document.querySelectorAll('[data-vivo]');
const insigniaPendientes = document.querySelector('[data-pendientes]');
let versionPanel = null;

async function refrescarZonas() {
   try {
      const html = await (await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })).text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      zonasVivas.forEach((zona) => {
         // No se pisa una zona mientras el admin está usando un control dentro de ella.
         if (zona.contains(document.activeElement) && document.activeElement !== document.body) return;
         const nueva = doc.querySelector(`[data-vivo="${zona.dataset.vivo}"]`);
         if (nueva && nueva.innerHTML !== zona.innerHTML) zona.innerHTML = nueva.innerHTML;
      });
   } catch (e) { /* sin red: se reintenta en el próximo ciclo */ }
}

async function consultarEstadoPanel() {
   if (document.hidden) return;
   try {
      const r = await fetch('api_admin.php', { cache: 'no-store', credentials: 'same-origin' });
      if (!r.ok) return;
      const datos = await r.json();
      if (insigniaPendientes) {
         insigniaPendientes.textContent = datos.pendientes;
         insigniaPendientes.classList.toggle('cero', datos.pendientes === 0);
      }
      if (versionPanel !== null && datos.version !== versionPanel && zonasVivas.length) refrescarZonas();
      versionPanel = datos.version;
   } catch (e) { /* se reintenta en el próximo ciclo */ }
}

consultarEstadoPanel();
setInterval(consultarEstadoPanel, 8000);
document.addEventListener('visibilitychange', consultarEstadoPanel);
/* ---------- Stock en vivo en la lista de productos ---------- */
const tarjetasProducto = document.querySelectorAll('.show-products .caja[id^="producto-"]');

if (tarjetasProducto.length) {
   const consultarStock = () => {
      if (document.hidden) return;
      fetch('api_stock.php', { cache: 'no-store' })
         .then((r) => r.json())
         .then((mapa) => tarjetasProducto.forEach((tarjeta) => {
            const stock = mapa[tarjeta.id.replace('producto-', '')];
            const etiqueta = tarjeta.querySelector('.stock');
            if (stock !== undefined && etiqueta) etiqueta.textContent = `Stock: ${stock}`;
         }))
         .catch(() => {});
   };
   setInterval(consultarStock, 8000);
   document.addEventListener('visibilitychange', consultarStock);
}