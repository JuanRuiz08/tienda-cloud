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

/* ---------- Pedidos en vivo: un pago nuevo (p. ej. PayPal) cambia la tarjeta sin recargar ---------- */
const listaPedidos = document.querySelector('.pedidos .caja-container[data-vivo]');

if (listaPedidos) {
   const refrescarPedidos = async () => {
      // No se pisa la lista si el admin está eligiendo un estado en ese momento.
      if (document.hidden || listaPedidos.contains(document.activeElement)) return;
      try {
         const html = await (await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })).text();
         const doc = new DOMParser().parseFromString(html, 'text/html');
         const nueva = doc.querySelector('.pedidos .caja-container');
         if (nueva && nueva.innerHTML !== listaPedidos.innerHTML) listaPedidos.innerHTML = nueva.innerHTML;
         const insignia = doc.querySelector('[data-pendientes]');
         const insigniaActual = document.querySelector('[data-pendientes]');
         if (insignia && insigniaActual) {
            insigniaActual.textContent = insignia.textContent;
            insigniaActual.classList.toggle('cero', insignia.classList.contains('cero'));
         }
         doc.querySelectorAll('.filtros [data-total]').forEach((n) => {
            const actual = document.querySelector(`.filtros [data-total="${n.dataset.total}"]`);
            if (actual) actual.textContent = n.textContent;
         });
      } catch (e) { /* sin red: se reintenta en el próximo ciclo */ }
   };
   setInterval(refrescarPedidos, 8000);
   document.addEventListener('visibilitychange', refrescarPedidos);
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