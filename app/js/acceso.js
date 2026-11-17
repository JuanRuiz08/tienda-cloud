/* Pantallas de acceso: mostrar/ocultar contraseña, foco inicial y avisos que se cierran solos. */
document.querySelectorAll('.ver-clave').forEach((boton) => {
   boton.addEventListener('click', () => {
      const campo = boton.parentElement.querySelector('input');
      const mostrar = campo.type === 'password';
      campo.type = mostrar ? 'text' : 'password';
      boton.querySelector('i').className = mostrar ? 'fas fa-eye-slash' : 'fas fa-eye';
      boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
   });
});

// Foco en el primer campo vacío (solo en pantallas con ratón, para no abrir el teclado en el móvil).
if (matchMedia('(hover: hover)').matches) {
   [...document.querySelectorAll('.acceso-caja input')].find((i) => !i.value)?.focus();
}
