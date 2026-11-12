// Botón de PayPal. El total llega en window.PAGO_TOTAL (lo define pago-paypal.php).
if (typeof paypal !== 'undefined') {
   paypal.Buttons({
      createOrder: (data, actions) => actions.order.create({
         purchase_units: [{ amount: { value: window.PAGO_TOTAL } }]
      }),
      onApprove: (data, actions) => actions.order.capture().then((details) => {
         alert('Gracias por tu compra, ' + details.payer.name.given_name);
         window.location.href = 'inicio.php';
      })
   }).render('#paypal-button-container');
}
