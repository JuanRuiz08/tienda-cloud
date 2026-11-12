<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/vistas.php';

iniciar_sesion();

if (!isset($_SESSION['order'])) {
   redirigir('inicio.php');
}

$total = $_SESSION['order']['total_price'];
$paypal_client_id = getenv('PAYPAL_CLIENT_ID') ?: '';

render_head('Pago con PayPal', 'style.css', [
   'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css',
   'pago-paypal.css',
]);
?>
   <div class="payment-container">
       <h1>¡Estás a un paso de completar tu compra!</h1>
       <p class="total-price">Total a pagar: $<?= number_format($total, 2) ?></p>
       <div id="paypal-button-container"></div>
       <div class="footer">
           <p>Tu compra está protegida y garantizada por PayPal.</p>
       </div>
   </div>

   <?php if ($paypal_client_id !== ''): ?>
   <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode($paypal_client_id) ?>"></script>
   <?php else: ?>
   <p>El pago con PayPal no está configurado (falta PAYPAL_CLIENT_ID).</p>
   <?php endif; ?>
   <script>window.PAGO_TOTAL = '<?= number_format($total, 2, '.', '') ?>';</script>
   <script src="js/paypal.js"></script>

</body>
</html>
