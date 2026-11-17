/* Comportamientos compartidos por todas las páginas (tienda, panel de admin y acceso).
   No hay scripts ni manejadores en línea (onclick…): la política de contenido (CSP) no los permite. */

// Formularios con data-confirmar="…": piden confirmación antes de enviarse (borrar, cancelar…).
document.addEventListener('submit', (e) => {
   const formulario = e.target.closest ? e.target.closest('form[data-confirmar]') : null;
   if (formulario && !window.confirm(formulario.dataset.confirmar)) e.preventDefault();
});

// Cruz de los avisos.
document.addEventListener('click', (e) => {
   const cruz = e.target.closest('[data-cerrar]');
   if (cruz) cruz.closest('.mensaje')?.remove();
});

// Los avisos se cierran solos.
document.querySelectorAll('.avisos .mensaje').forEach((aviso, i) => {
   setTimeout(() => {
      aviso.classList.add('saliendo');
      aviso.addEventListener('animationend', () => aviso.remove(), { once: true });
   }, 4500 + i * 400);
});