/* Venta en tienda: total en vivo, buscador de productos y botón activo solo si hay algo que vender. */
(() => {
   const formulario = document.getElementById('form-venta');
   if (!formulario) return;

   const campos = [...formulario.querySelectorAll('input[name^="cantidad["]')];
   const total = document.getElementById('venta-total');
   const unidades = document.getElementById('venta-unidades');
   const boton = document.getElementById('venta-registrar');
   const buscador = document.getElementById('venta-buscar');
   const dinero = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

   const recalcular = () => {
      let suma = 0;
      let cantidad = 0;
      campos.forEach((campo) => {
         const tope = Number(campo.max) || 0;
         let n = Math.max(0, Math.floor(Number(campo.value) || 0));
         if (n > tope) { n = tope; campo.value = tope; }   // nunca más de lo que hay en stock
         suma += n * Number(campo.dataset.precio);
         cantidad += n;
         campo.closest('.venta-fila').classList.toggle('elegido', n > 0);
      });
      total.textContent = dinero.format(suma).replace(/\s/g, '');
      unidades.textContent = cantidad === 1 ? '1 producto' : `${cantidad} productos`;
      boton.disabled = cantidad === 0;
   };

   formulario.addEventListener('input', (e) => { if (e.target.matches('input[name^="cantidad["]')) recalcular(); });
   formulario.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.id === 'venta-buscar') e.preventDefault(); });

   buscador.addEventListener('input', () => {
      const texto = buscador.value.trim().toLowerCase();
      formulario.querySelectorAll('.venta-fila').forEach((fila) => {
         fila.hidden = texto !== '' && !fila.dataset.nombre.includes(texto);
      });
   });

   recalcular();
})();