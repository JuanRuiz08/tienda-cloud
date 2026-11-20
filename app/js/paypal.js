/* Botón de PayPal: pide al servidor que cree y capture la orden (api_paypal.php). El navegador nunca decide el monto. */
(() => {
   const contenedor = document.getElementById('paypal-button-container');
   if (!contenedor || typeof paypal === 'undefined') return;

   const pedido = contenedor.dataset.pedido;
   const estado = document.getElementById('pago-estado');
   const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

   const mostrar = (texto, error = false) => {
      estado.textContent = texto;
      estado.classList.toggle('error', error);
   };

   const llamar = async (accion, cuerpo = {}) => {
      const respuesta = await fetch(`api_paypal.php?accion=${accion}&pedido=${encodeURIComponent(pedido)}`, {
         method: 'POST',
         headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
         body: JSON.stringify(cuerpo),
      });
      const datos = await respuesta.json().catch(() => ({}));
      if (!respuesta.ok) throw new Error(datos.error || 'No se pudo completar el pago.');
      return datos;
   };

   paypal.Buttons({
      style: { layout: 'vertical', shape: 'pill', label: 'pay' },
      createOrder: () => llamar('crear').then((d) => d.id),
      onApprove: (datos) => {
         mostrar('Confirmando tu pago…');
         return llamar('capturar', { id: datos.orderID })
            .then(() => { mostrar('¡Pago recibido!'); window.location.href = 'pedidos.php?pagado=1'; })
            .catch((e) => mostrar(e.message, true));
      },
      onCancel: () => mostrar('Cancelaste el pago. Puedes intentarlo de nuevo.', true),
      onError: () => mostrar('No se pudo completar el pago. Intenta de nuevo.', true),
   }).render('#paypal-button-container');
})();