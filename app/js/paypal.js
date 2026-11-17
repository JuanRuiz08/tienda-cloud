// Botón de PayPal. El número de pedido está en data-pedido del contenedor (sin scripts en línea, por la CSP).
// El total y la confirmación del pago los decide el servidor (api_paypal.php); aquí solo se coordina el botón.
(() => {
   const contenedor = document.querySelector('#paypal-button-container');
   const pedido = contenedor.dataset.pedido;
   const csrf = document.querySelector('meta[name="csrf-token"]').content;
   const estado = document.querySelector('#pago-estado');

   const avisar = (texto, error = false) => {
      estado.textContent = texto;
      estado.classList.toggle('error', error);
   };

   const llamar = (accion, cuerpo) =>
      fetch(`api_paypal.php?accion=${accion}&pedido=${pedido}`, {
         method: 'POST',
         headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
         body: JSON.stringify(cuerpo || {}),
      }).then(async (r) => {
         const datos = await r.json().catch(() => ({}));
         if (!r.ok) throw new Error(datos.error || 'No se pudo completar el pago.');
         return datos;
      });

   paypal.Buttons({
      style: { layout: 'vertical', shape: 'pill', label: 'pay' },

      createOrder: () => llamar('crear').then((d) => d.id),

      onApprove: (data) => {
         avisar('Confirmando tu pago…');
         return llamar('capturar', { id: data.orderID })
            .then(() => { location.href = `pedidos.php?pagado=${pedido}`; })
            .catch((e) => avisar(e.message, true));
      },

      onCancel: () => avisar('Cancelaste el pago. Tu pedido sigue pendiente.'),
      onError: () => avisar('No se pudo iniciar el pago. Intenta de nuevo en unos segundos.', true),
   }).render('#paypal-button-container');
})();
