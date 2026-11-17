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
// Avisos: se cierran solos
document.querySelectorAll('.avisos .mensaje').forEach((aviso, i) => {
   setTimeout(() => {
      aviso.classList.add('saliendo');
      aviso.addEventListener('animationend', () => aviso.remove(), { once: true });
   }, 4500 + i * 400);
});

// Ventana de edición: se cierra con Escape o al hacer clic en el fondo
const modal = document.querySelector('.edit-product-form');
if (modal) {
   const cerrar = () => { location.href = 'admin_productos.php'; };
   modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
   document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });
}

/* ---------- Actualización en vivo ----------
   Cada zona marcada con data-vivo="nombre" se vuelve a pedir al servidor cada pocos segundos y solo se
   reemplaza si cambió (así un pedido nuevo o un pago de PayPal se ven sin recargar). */
const zonasVivas = document.querySelectorAll('[data-vivo]');

if (zonasVivas.length) {
   const refrescarZonas = async () => {
      if (document.hidden) return;
      try {
         const html = await (await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })).text();
         const doc = new DOMParser().parseFromString(html, 'text/html');
         zonasVivas.forEach((zona) => {
            // No se pisa una zona mientras el admin está usando un control dentro de ella.
            if (zona !== document.body && zona.contains(document.activeElement) && document.activeElement !== document.body) return;
            const nueva = doc.querySelector(`[data-vivo="${zona.dataset.vivo}"]`);
            if (nueva && nueva.innerHTML !== zona.innerHTML) zona.innerHTML = nueva.innerHTML;
         });
      } catch (e) { /* sin red: se reintenta en el próximo ciclo */ }
   };
   setInterval(refrescarZonas, 8000);
   document.addEventListener('visibilitychange', refrescarZonas);
}

/* ---------- Contador de pendientes del menú (en todas las páginas del panel) ---------- */
const insigniaPendientes = document.querySelector('[data-pendientes]');

if (insigniaPendientes) {
   const consultarPendientes = () => {
      if (document.hidden) return;
      fetch('api_admin.php', { cache: 'no-store' })
         .then((r) => (r.ok ? r.json() : null))
         .then((datos) => {
            if (!datos) return;
            insigniaPendientes.textContent = datos.pendientes;
            insigniaPendientes.classList.toggle('cero', datos.pendientes === 0);
         })
         .catch(() => {});
   };
   setInterval(consultarPendientes, 8000);
   document.addEventListener('visibilitychange', consultarPendientes);
}
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