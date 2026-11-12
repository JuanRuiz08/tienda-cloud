<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/paypal.php';

$user_id = requerir_usuario();

$pedido = pedido_pendiente($conn, $user_id, (int) ($_GET['pedido'] ?? 0));
if ($pedido === null) {
   redirigir('pedidos.php');
}

$disponible = paypal_configurado();
$usd = total_en_usd((int) $pedido['total_price']);

render_head('Pago con PayPal');
include 'header.php';
render_encabezado('Pago', 'Pago');
?>

<section class="pago">
   <div class="pago-tarjeta">
      <p class="pago-etiqueta">Pedido #<?= (int) $pedido['id'] ?></p>
      <h1>Completa tu pago</h1>
      <p class="pago-productos"><?= e($pedido['total_products']) ?></p>

      <div class="pago-total">
         <span>$<?= e($pedido['total_price']) ?> Mil</span>
         <small>PayPal lo cobra como USD <?= e($usd) ?></small>
      </div>

      <?php if ($disponible): ?>
         <div id="paypal-button-container"></div>
         <div id="pago-estado" role="status" aria-live="polite"></div>
      <?php else: ?>
         <p class="pago-aviso">El pago en línea no está disponible por ahora. Tu pedido quedó registrado como pendiente.</p>
         <a href="pedidos.php" class="boton">Ver mis pedidos</a>
      <?php endif; ?>

      <p class="pago-nota"><i class="fas fa-lock"></i> Pago seguro con PayPal. Puedes pagar más tarde desde <a href="pedidos.php">Mis pedidos</a>.</p>
   </div>
</section>

<?php if ($disponible): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(paypal_config()['client_id']) ?>&amp;currency=USD&amp;intent=capture"></script>
<script>window.PAGO = { pedido: <?= (int) $pedido['id'] ?> };</script>
<script src="<?= e(url_con_version('js/paypal.js')) ?>"></script>
<?php endif; ?>

<?php render_cierre(); ?>
