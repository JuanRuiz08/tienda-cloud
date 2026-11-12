const navbar = document.querySelector('.header .navbar');
const accountBox = document.querySelector('.header .account-box');

document.querySelector('#menu-btn').onclick = (e) => {
   e.stopPropagation();
   navbar.classList.toggle('active');
   accountBox.classList.remove('active');
};

document.querySelector('#user-btn').onclick = (e) => {
   e.stopPropagation();
   accountBox.classList.toggle('active');
   navbar.classList.remove('active');
};

const cerrarMenus = () => {
   navbar.classList.remove('active');
   accountBox.classList.remove('active');
};

document.addEventListener('click', (e) => {
   if (!e.target.closest('.header')) cerrarMenus();
});
document.addEventListener('keydown', (e) => {
   if (e.key === 'Escape') cerrarMenus();
});
window.addEventListener('scroll', cerrarMenus, { passive: true });

// Enlace de la página actual
const paginaActual = location.pathname.split('/').pop();
navbar.querySelectorAll('a').forEach((a) => {
   if (a.getAttribute('href') === paginaActual) a.classList.add('activo');
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
