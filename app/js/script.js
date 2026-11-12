const header2 = document.querySelector('.header .header-2');
const navbar = header2.querySelector('.navbar');
const userBox = header2.querySelector('.usuario-caja');

/* ---------- Menú y caja de usuario ---------- */
document.querySelector('#user-btn').onclick = (e) => {
   e.stopPropagation();
   userBox.classList.toggle('active');
   navbar.classList.remove('active');
};

document.querySelector('#menu-btn').onclick = (e) => {
   e.stopPropagation();
   navbar.classList.toggle('active');
   userBox.classList.remove('active');
};

const cerrarMenus = () => {
   userBox.classList.remove('active');
   navbar.classList.remove('active');
};

document.addEventListener('click', (e) => {
   if (!header2.contains(e.target)) cerrarMenus();
});
document.addEventListener('keydown', (e) => {
   if (e.key === 'Escape') cerrarMenus();
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
