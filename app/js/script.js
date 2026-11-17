const header2 = document.querySelector('.header .header-2');
const navbar = header2.querySelector('.navbar');
const userBox = header2.querySelector('.usuario-caja');   // solo existe con sesión iniciada
const userBtn = document.querySelector('#user-btn');
const buscador = document.querySelector('#buscador');

/* ---------- Menús de la cabecera ---------- */
const cerrarMenus = () => {
   userBox?.classList.remove('active');
   navbar.classList.remove('active');
};

if (userBtn) {
   userBtn.onclick = (e) => {
      e.stopPropagation();
      userBox.classList.toggle('active');
      navbar.classList.remove('active');
      cerrarBuscador();
   };
}

document.querySelector('#menu-btn').onclick = (e) => {
   e.stopPropagation();
   navbar.classList.toggle('active');
   userBox?.classList.remove('active');
   cerrarBuscador();
};

document.addEventListener('click', (e) => {
   if (!header2.contains(e.target)) {
      cerrarMenus();
      cerrarBuscador();
   }
});
document.addEventListener('keydown', (e) => {
   if (e.key === 'Escape') {
      cerrarMenus();
      cerrarBuscador();
   }
});

/* ---------- Sombra de la cabecera al hacer scroll ---------- */
const alScroll = () => {
   cerrarMenus();
   header2.classList.toggle('active', window.scrollY > 8);
};
window.addEventListener('scroll', alScroll, { passive: true });
alScroll();

/* ---------- Enlace de la página actual ---------- */
const paginaActual = location.pathname.split('/').pop() || 'inicio.php';
navbar.querySelectorAll('a').forEach((a) => {
   if (a.getAttribute('href') === paginaActual) a.classList.add('activo');
});

/* ---------- Buscador desplegable con vista previa ---------- */
const entrada = document.querySelector('#buscador-input');
const lista = document.querySelector('#buscador-resultados');
let temporizador = null;
let peticion = null;
let activo = -1;

function abrirBuscador() {
   cerrarMenus();
   buscador.classList.add('abierto');
   buscador.setAttribute('aria-hidden', 'false');
   entrada.focus();
}

function cerrarBuscador() {
   if (!buscador) return;
   buscador.classList.remove('abierto');
   buscador.setAttribute('aria-hidden', 'true');
   entrada.blur();
}

document.querySelector('#search-btn').onclick = (e) => {
   e.stopPropagation();
   buscador.classList.contains('abierto') ? cerrarBuscador() : abrirBuscador();
};
buscador.querySelector('.buscador-cerrar').onclick = cerrarBuscador;

// "/" abre el buscador desde cualquier parte (si no se está escribiendo en un campo)
document.addEventListener('keydown', (e) => {
   const escribiendo = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
   if (e.key === '/' && !escribiendo) {
      e.preventDefault();
      abrirBuscador();
   }
});

/** Texto con la parte coincidente en negrita (sin interpretar HTML). */
function resaltar(nombre, q) {
   // La API devuelve nombres que empiezan por q o cuyas palabras empiezan por q: se marca esa parte.
   const bajo = nombre.toLowerCase();
   const buscado = q.toLowerCase();
   const i = bajo.startsWith(buscado) ? 0 : bajo.indexOf(' ' + buscado) + 1 || -1;
   const frag = document.createDocumentFragment();
   if (i < 0) {
      frag.append(nombre);
      return frag;
   }
   const marca = document.createElement('mark');
   marca.textContent = nombre.slice(i, i + q.length);
   frag.append(nombre.slice(0, i), marca, nombre.slice(i + q.length));
   return frag;
}

function mostrarEstado(texto) {
   lista.replaceChildren();
   const p = document.createElement('div');
   p.className = 'buscador-estado';
   p.textContent = texto;
   lista.append(p);
}

function mostrarResultados(productos, q) {
   activo = -1;
   if (!productos.length) {
      mostrarEstado(`Sin resultados para «${q}»`);
      return;
   }
   lista.replaceChildren();
   productos.forEach((p) => {
      const a = document.createElement('a');
      a.className = 'resultado';
      a.href = `tienda.php#producto-${p.id}`;

      const img = document.createElement('img');
      img.src = 'uploaded_img/' + encodeURIComponent(p.image);
      img.alt = '';
      img.addEventListener('error', () => { img.style.visibility = 'hidden'; }, { once: true });

      const nombre = document.createElement('span');
      nombre.className = 'nombre';
      nombre.append(resaltar(p.name.trim(), q));

      const precio = document.createElement('span');
      precio.className = 'precio';
      precio.textContent = '$' + String(p.price).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

      a.append(img, nombre, precio);
      lista.append(a);
   });

   const todos = document.createElement('button');
   todos.type = 'button';
   todos.className = 'buscador-todos';
   todos.textContent = 'Ver todos los resultados';
   todos.onclick = () => entrada.form.submit();
   lista.append(todos);
}

function buscar() {
   const q = entrada.value.trim();
   if (!q) {
      lista.replaceChildren();
      return;
   }
   peticion?.abort();
   peticion = new AbortController();
   fetch('api_busqueda.php?q=' + encodeURIComponent(q), { signal: peticion.signal })
      .then((r) => r.json())
      .then((productos) => mostrarResultados(productos, q))
      .catch((err) => { if (err.name !== 'AbortError') mostrarEstado('No se pudo buscar. Intenta de nuevo.'); });
}

entrada.addEventListener('input', () => {
   clearTimeout(temporizador);
   temporizador = setTimeout(buscar, 150);
});

// Flechas ↑ ↓ para recorrer los resultados; Enter abre el seleccionado (o la búsqueda completa)
entrada.addEventListener('keydown', (e) => {
   const items = [...lista.querySelectorAll('.resultado')];
   if (!items.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(e.key)) return;

   if (e.key === 'Enter') {
      if (activo >= 0) {
         e.preventDefault();
         items[activo].click();
      }
      return;
   }
   e.preventDefault();
   activo = (activo + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
   items.forEach((el, i) => el.classList.toggle('activo', i === activo));
});

/* ---------- Stock en vivo: el catálogo refleja las compras sin recargar ---------- */
const tarjetasStock = document.querySelectorAll('.show-products .caja[id^="producto-"]');

if (tarjetasStock.length) {
   const aplicarStock = (mapa) => tarjetasStock.forEach((tarjeta) => {
      const stock = mapa[tarjeta.id.replace('producto-', '')];
      if (stock === undefined) return;

      const etiqueta = tarjeta.querySelector('.stock');
      const cantidad = tarjeta.querySelector('.qty');
      const boton = tarjeta.querySelector('input[type="submit"]');

      etiqueta.textContent = stock === 0 ? 'Agotado' : `Stock: ${stock}`;
      etiqueta.classList.toggle('agotado', stock === 0);
      etiqueta.classList.toggle('bajo', stock > 0 && stock <= 3);

      if (cantidad) {
         cantidad.disabled = stock === 0;
         cantidad.max = Math.max(stock, 1);
         if (Number(cantidad.value) > cantidad.max) cantidad.value = cantidad.max;
      }
      if (boton && !boton.classList.contains('cargando')) {
         boton.disabled = stock === 0;
         boton.value = stock === 0 ? 'Agotado' : 'Añadir';
      }
   });

   const consultarStock = () => {
      if (document.hidden) return;
      fetch('api_stock.php', { cache: 'no-store' }).then((r) => r.json()).then(aplicarStock).catch(() => {});
   };
   setInterval(consultarStock, 8000);
   document.addEventListener('visibilitychange', consultarStock);
}

/* ---------- Aparición suave al entrar en pantalla ---------- */
const objetivos = document.querySelectorAll(
   '.show-products .caja-container .caja, .carrito-de-compras .caja-container .caja, .pedidos .caja,' +
   '.acerca .flex > *, .total-carrito, .checkout, .display-order, .categoria-title'
);

if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
   const observador = new IntersectionObserver((entradas) => {
      entradas.forEach((entrada) => {
         if (entrada.isIntersecting) {
            entrada.target.classList.add('visible');
            observador.unobserve(entrada.target);
         }
      });
   }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

   objetivos.forEach((el) => {
      // Retraso escalonado entre hermanos (máx. 8 pasos) para que las tarjetas entren en cascada.
      el.style.setProperty('--i', Math.min([...el.parentElement.children].indexOf(el), 8));
      el.classList.add('reveal');
      observador.observe(el);
   });
}

/* ---------- Avisos: se cierran solos ---------- */
document.querySelectorAll('.avisos .mensaje').forEach((aviso, i) => {
   setTimeout(() => {
      aviso.classList.add('saliendo');
      aviso.addEventListener('animationend', () => aviso.remove(), { once: true });
   }, 4500 + i * 400);
});

/* ---------- Imagen de producto que no carga: se muestra un marcador ---------- */
document.querySelectorAll('.media img, .carrito-de-compras .caja img').forEach((img) => {
   const marcar = () => img.classList.add('sin-imagen');
   if (img.complete && img.naturalWidth === 0) marcar();
   else img.addEventListener('error', marcar, { once: true });
});

/* ---------- Estado de carga al enviar ---------- */
document.querySelectorAll('form.caja').forEach((form) => {
   form.addEventListener('submit', () => {
      const boton = form.querySelector('input[type="submit"]');
      if (!boton) return;
      boton.classList.add('cargando');
      boton.value = 'Añadiendo…';
   });
});
